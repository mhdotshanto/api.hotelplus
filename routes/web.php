<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
Route::get('/', function () {
    return 'This is the API for Hotel Plus CV.';
});
Route::get('/c-clean', function () {
    Artisan::call('optimize:clear');
    session()->flush();
    $messages =  "Hello " . env('APP_NAME') . " All cache cleared.";
    return response([
        'status' => true,
        'message' => $messages,
    ]);
});

Route::get('/db-migrate', function () {
    Artisan::call('app:migrate-old-data');
    session()->flush();
    $messages =  "Hello " . env('APP_NAME') . " Database Migration Successfully Done.";
    return response([
        'status' => true,
        'message' => $messages,
    ]);
});


Route::get('/test-sale-room-migration', function () {
    $sales_record_from_source = DB::connection('mariadb_source')
        ->table('room_sales_details')
        ->select(
            'refid',
            DB::raw(
                'GROUP_CONCAT(
                    DISTINCT roomno ORDER BY roomno SEPARATOR ","
                ) as rooms'
            ),
            'created_at',
            'isxb',
        )
        ->groupBy('refid')
        ->get();

    $insertable = [];

    foreach ($sales_record_from_source as $record){
        $names = explode(',', $record->rooms);
        $rooms_from_target_db = DB::connection('mariadb_target')
            ->table('room_numbers')
            ->join('room_names', 'room_names.id', '=', 'room_numbers.room_name_id')
            ->join('room_categories', 'room_categories.id', '=', 'room_names.category_id')
            ->whereIn('room_numbers.name', $names)
            ->select(
                'room_numbers.id as room_number_id',
                'room_numbers.name as room_number',
                'room_names.id as room_name_id',
                'room_names.name as room_name',
                'room_names.bed',
                'room_names.rate',
                'room_names.rate_xb',
                'room_categories.id as category_id',
                'room_categories.name as category_name',
            )
            ->get();

        $grouped = $rooms_from_target_db->groupBy(function ($item) {
            return "{$item->category_name} | {$item->bed}";
        });

        foreach ($grouped as $rooms) {
            $item = $rooms[0];
            $qty = count($rooms);
            $insertable[] = [
                'sale_id' => $record->refid,
                'category_id' => $item->category_id,
                'bed' => $item->bed,
                'date' => $record->created_at,
                'qty' => $qty,
                'rate' => 0,
                'gross_rate' => 0,
                'is_xb' => $record->isxb,
                'discount_per' => 0,
                'discount_amount' => 0,
                'adjustment_per' => 0,
                'adjustment_amount' => 0,
                'rate_sale' => 0,
                'tip_per' => 0,
                'tip_amount' => 0,
                'rate_net' => 0,
            ];
        }

        $insertable[] = $grouped;
    }

    return response()->json($insertable);
});

Route::get('/test-method', function () {
    return DB::connection('mariadb_target')
        ->table('sale_rooms')
        ->where('oid', 2000003562)
        ->select(
            'discount_per',
            DB::raw('CASE WHEN price_gross != 0 THEN (adjusted_amount / price_gross) * 100 ELSE 0 END AS adjusted_per'),
            DB::raw('CASE WHEN price_gross != 0 THEN (tip_amount / price_gross) * 100 ELSE 0 END AS tip_per')
        )
        ->first();
});
