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


//Route::get('/test-sale-room-migration', function () {
//   $sale_payments
//   return response()->json();
//});
