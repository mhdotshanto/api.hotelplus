<?php

namespace App\Console\Commands;

use App\Models\Config;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOldData extends Command
{
    protected $signature = 'app:migrate-old-data';
    protected $description = 'Migrate old data to the new system';

    private function fixDecimal( $number, $decimal_places = 2, $round_type = PHP_ROUND_HALF_UP) {
        return round($number, $decimal_places, $round_type);
    }

    public function applyVatAndServiceCharge($amount)
    {
        $config = Config::select('slug', 'value')->whereIn('slug', ['vat', 'service-charge'])->get()->pluck('value', 'slug')->toArray();
        $vat_amount = $amount * ($config['vat'] / 100);
        $amount = $amount + $vat_amount;
        $service_charge = $amount * ($config['service-charge'] / 100);
        return $amount + $service_charge;
    }

    // Generate short name from room name
    private function makeShortName($string) {
        $words = preg_split('/\s+/', trim($string));
        $shortName = '';
        foreach ($words as $word) {
            $shortName .= strtoupper($word[0]);
        }
        return $shortName;
    }

    // Get processed room name and bed type
    private function getBedTypeByRoomName(string $roomName) {
        if (str_contains($roomName, '2 DBL')) return [ str_replace(' 2 DBL', '', $roomName), '2 DBL'];
        if (str_contains($roomName, '3 DBL')) return [ str_replace(' 3 DBL', '', $roomName), '3 DBL'];
        if (str_contains($roomName, 'DBL')) return [ str_replace(' DBL', '', $roomName), 'DBL'];
        if (str_contains($roomName, 'SGL')) return [ str_replace(' SGL', '', $roomName), 'SGL'];
        if (str_contains($roomName, 'CPL')) return [ str_replace(' CPL', '', $roomName), 'CPL'];
        if (str_contains($roomName, 'TPL')) return [ str_replace(' TPL', '', $roomName), 'TPL'];
        if (str_contains($roomName, 'TWN')) return [ str_replace(' TWN', '', $roomName), 'TWN'];

        return [$roomName, 'N/A'];
    }

    private function getOriginalGuestId(int $oid) {
        return DB::connection('mariadb_target')
            ->table('guests')
            ->where('oid', $oid)
            ->select('id')
            ->first()->id;
    }

    private function getOriginalRefId(int $osid) {
        return DB::connection('mariadb_target')
            ->table('users')
            ->where('osid', $osid)
            ->select('id')
            ->first()->id;
    }

    private function getOriginalUserId(int $oid) {
        return DB::connection('mariadb_target')
            ->table('users')
            ->where('oid', $oid)
            ->select('id')
            ->first()->id;
    }

    private function getOriginalSaleId(int $oid) {
        return DB::connection('mariadb_target')
            ->table('sale_rooms')
            ->where('oid', $oid)
            ->select('id')
            ->first()->id;
    }

    private function getOriginalPercentageForSale(int $oid) {
        return DB::connection('mariadb_target')
            ->table('sale_rooms')
            ->where('oid', $oid)
            ->select(
                'discount_per',
                DB::raw('CASE WHEN price_gross != 0 THEN (adjusted_amount / price_gross) * 100 ELSE 0 END AS adjusted_per'),
                DB::raw('CASE WHEN price_gross != 0 THEN (tip_amount / price_gross) * 100 ELSE 0 END AS tip_per')
            )
            ->first();
    }

    public function handle()
    {
        if (config('ahits.migration_status') == 'done'){
            $this->info("migration already done");
            return;
        }
        DB::connection('mariadb_target')->statement('SET FOREIGN_KEY_CHECKS=0;');

        if (config('ahits.truncate_before_migration')){
            $tablesToTruncate = ['room_categories', 'room_floors', 'room_names', 'room_numbers', 'users', 'guests', 'sale_rooms', 'sale_room_allocations'];
            foreach ($tablesToTruncate as $table) DB::connection('mariadb_target')->table($table)->truncate();
        }

        DB::connection('mariadb_target')->statement('SET FOREIGN_KEY_CHECKS=1;');

        $oldCategories = DB::connection('mariadb_source')->table('room_categories')->get();
        foreach ($oldCategories as $category) {
            DB::connection('mariadb_target')->table('room_categories')->insert([
                'name' => $category->name,
                'is_active' => $category->isactive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $oldFloors = DB::connection('mariadb_source')->table('room_floors')->get();
        foreach ($oldFloors as $floor) {
            DB::connection('mariadb_target')->table('room_floors')->insert([
                'name' => $floor->name,
                'is_active' => $floor->isactive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $oldRoomStock = DB::connection('mariadb_source')->table('room_stock_details')->get()->unique('name');
        $counter = 0;
        foreach ($oldRoomStock as $oldRoom) {
            $counter++;
            $bedType = $this->getBedTypeByRoomName($oldRoom->name);

            $roomName = $bedType[0];

            $originalName = $roomName;
            $i = 1;
            while (DB::connection('mariadb_target')->table('room_names')->where('name', $roomName)->exists()) {
                $roomName = $originalName . '-' . $i;
                $i++;
            }

            $shortName = $this->makeShortName($bedType[0]) ?? "room-".$counter;
            $originalShortName = $shortName;
            $j = 1;
            while (DB::connection('mariadb_target')->table('room_names')->where('short_name', $shortName)->exists()) {
                $shortName = $originalShortName . $j;
                $j++;
            }

            DB::connection('mariadb_target')->table('room_names')->insert([
                'name' => $roomName,
                'short_name' => $shortName,
                'category_id' => $oldRoom->catid,
                'bed' => $bedType[1],
                'rate' => $oldRoom->rate,
                'rate_xb' => 0,
                'is_active' => $oldRoom->isactive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $roomNameArray = DB::connection('mariadb_target')->table('room_names')->pluck('id','name')->toArray();
        $roomFloorArray = DB::connection('mariadb_target')->table('room_floors')->pluck('id','name')->toArray();

        $oldRoomStockAll = DB::connection('mariadb_source')->table('room_stock_details')->get();
        foreach ($oldRoomStockAll as $oldRoom) {
            $bedType = $this->getBedTypeByRoomName($oldRoom->name);
            $roomNameKey = $bedType[0];

            if(!isset($roomNameArray[$roomNameKey])) continue;

            DB::connection('mariadb_target')->table('room_numbers')->insert([
                'room_name_id' => $roomNameArray[$roomNameKey],
                'floor_id' => $roomFloorArray[$oldRoom->floorno] ?? null,
                'name' => $oldRoom->roomno,
                'is_clean' => 1,
                'is_active' => $oldRoom->isactive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $oldUsers = DB::connection('mariadb_source')->table('reg_staffs as s')
            ->leftJoin('users as u','s.userid','=','u.id')
            ->select('s.name','u.username','s.email','s.contact','s.designation','u.password','s.isref', DB::raw('s.id as staff_id'), DB::raw('u.id as user_id'))
            ->get();

        $username_suffix = 1;
        foreach ($oldUsers as $user) {
            $username = $user->username;
            $email = $user->email;
            if (is_null($email)) $email = $user->username . "@gmail.com";
            if($username == 'arrowheadit'){
                $username = $user->username . $username_suffix;
                $username_suffix++;
            }
            DB::connection('mariadb_target')->table('users')->insert([
                'name' => $user->name,
                'user_name' => $username,
                'role' => 'admin',
                'type' => 'user',
                'status' => 'active',
                'email' => $email,
                'phone' => $user->contact,
                'designation' => $user->designation,
                'password' => $user->password,
                'is_ref' => $user->isref,
                'email_verified_at' => now(),
                'oid' => $user->user_id,
                'osid' => $user->staff_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $nameAddressArray = ['MALE'=>'Mr.','FEMALE'=>'Mrs.'];
        $newUserArray = DB::connection('mariadb_target')->table('users')->pluck('id','osid')->toArray();
        $oldGuests = DB::connection('mariadb_source')->table('room_guests')->get();

        foreach ($oldGuests as $guest) {
            $birthdate = $guest->birthdate=='1970-01-01'? null: $guest->birthdate;
            $marriagedate = $guest->marriagedate=='1970-01-01'? null: $guest->marriagedate;

            DB::connection('mariadb_target')->table('guests')->insert([
                'name_address' => $nameAddressArray[$guest->gender] ?? 'Mr.',
                'last_name' => $guest->lastname,
                'first_name' => $guest->firstname,
                'email' => $guest->email,
                'mobile' => $guest->cell,
                'secondary_mobile' => $guest->sec_cell,
                'emergency_contact' => $guest->emergency_contact ?? null,
                'dob' => $birthdate,
                'dom' => $marriagedate,
                'pres_country_id' => $guest->presentcountryid,
                'pres_city' => $guest->presentcity,
                'pres_address' => $guest->presentaddress,
                'perm_country_id' => $guest->permanentcountryid,
                'perm_city' => $guest->permanentcity,
                'perm_address' => $guest->permanentaddress,
                'source' => $guest->source,
                'profession' => $guest->profession,
                'market' => $guest->market,
                'company' => $guest->company,
                'nid' => $guest->nid,
                'passport' => $guest->passport,
                'passport_date' => $guest->passportdateplace,
                'passport_exp_date' => null,
                'nationality_id' => $guest->nationality,
                'ref_id' => $newUserArray[$guest->referenceid] ?? null,
                'oid' => $guest->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $sales_record_from_source = DB::connection('mariadb_source')->table('room_sales')->get();
        foreach ($sales_record_from_source as $record) {
            DB::connection('mariadb_target')->table('sale_rooms')->insert([
                'code'                  => $record->reservation,
                'token'                 => encrypt($record->reservation),
                'guest_id'              => $this->getOriginalGuestId(oid: $record->guestid),
                'guest_name_title'      => $record->guest_nameprefix,
                'guest_name_first'      => $record->guest_firstname,
                'guest_name_last'       => $record->guest_lastname,
                'guest_mobile'          => $record->guest_cell,
                'guest_mobile_emergency'=> $record->emergency_contact,
                'guest_email'           => $record->guest_email,
                'guest_company'         => $record->guest_company,
                'guest_address'         => null,

                'ref_id'                => $this->getOriginalRefId(osid: $record->referenceid),
                'user_id'               => $this->getOriginalUserId(oid: $record->userid),

                'master_room'           => $record->masterroom,
                'rooms'                 => $record->rooms,
                'guest_qty'             => $record->numberofpax,
                'room_qty'              => $record->room,
                'xb_qty'                => $record->xb,
                'date'                  => $record->date,
                'discount_note'         => $record->discount_note,
                'price'                 => $record->price,
                'vat_per'               => $record->vat_per,
                'vat_amount'            => $record->vat_amount,
                'servicecharge_per'     => $record->servicecharge_per,
                'servicecharge_amount'  => $record->servicecharge_amount,
                'price_gross'           => $record->grossprice,
                'discount_type'         => 'invoice',
                'discount_per'          => $record->saleprice > 0 ? $this->fixDecimal(($record->discount_amount / $record->grossprice) * 100) : 0.00,
                'discount_amount'       => $record->discount_amount,
                'price_sale'            => $record->saleprice,
                'adjusted_amount'       => $record->discount_adjustment_amount,
                'tip_amount'            => $record->amount_tip,
                'price_net'             => $record->netprice,
                'remarks'               => $record->remarks,
                'date_checkin'          => $record->checkin,
                'eta'                   => $record->estimated_time_arrival
                    ? date("H:i:s", strtotime($record->estimated_time_arrival))
                    : null,
                'date_checkout'         => $record->checkout,
                'etd'                   => $record->estimated_time_departure
                    ? date("H:i:s", strtotime($record->estimated_time_departure))
                    : null,
                'date_to_confirm'       => $record->confirmationdate,
                'status'                => strtolower($record->status),
                'is_confirmed'          => 1,
                'is_room_allocated'     => 1,
                'created_at'            => $record->created_at,
                'updated_at'            => $record->updated_at,
                'oid'                   => $record->refid,
            ]);
        }

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
                'date',
                'status'
            )
            ->groupBy('refid')
            ->get();

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

            if (in_array($record->status, ['TENTATIVE', 'GUARANTEED', 'CHECKIN'])) {
                foreach ($rooms_from_target_db as $room) {
                    DB::connection('mariadb_target')->table('sale_room_allocations')->insert([
                        "room_sale_id" => $this->getOriginalSaleId($record->refid),
                        "room_number_id" => $room->room_number_id,
                        "room_category_id" => $room->category_id,
                        "room_name_id" => $room->room_name_id,
                        "bed" => $room->bed,
                        "date" => $record->date,
                        "is_xb" => $record->isxb,
                    ]);
                }
            }

            $grouped = $rooms_from_target_db->groupBy(function ($item) {
                return "{$item->category_name} | {$item->bed}";
            });

            foreach ($grouped as $rooms) {
                $item = $rooms[0];
                $qty = count($rooms);
                $grossRate = $this->applyVatAndServiceCharge($item->rate);
                $originalPercentage = $this->getOriginalPercentageForSale($record->refid);

                $discountPerItem = $originalPercentage->discount_per;
                $discountAmountItem = $grossRate * ($discountPerItem / 100);
                $rateSale = $grossRate - $discountAmountItem;

                $adjustmentAmount = $rateSale * ($originalPercentage->adjusted_per / 100);
                $tipAmount = $rateSale * ($originalPercentage->tip_per / 100);

                DB::connection('mariadb_target')->table('sale_room_categories')->insert([
                    'sale_id' => $this->getOriginalSaleId($record->refid),
                    'category_id' => $item->category_id,
                    'bed' => $item->bed,
                    'date' => $record->created_at,
                    'qty' => $qty,
                    'rate' => $item->rate,
                    'gross_rate' => $grossRate,
                    'is_xb' => $record->isxb,

                    'discount_per' => $discountPerItem,
                    'discount_amount' => $discountAmountItem,

                    'rate_sale' => $rateSale,

                    'adjustment_per' => $originalPercentage->adjusted_per,
                    'adjustment_amount' => $adjustmentAmount,

                    'tip_per' => $originalPercentage->tip_per,
                    'tip_amount' => $tipAmount,
                    'rate_net' => ($rateSale - $tipAmount - $adjustmentAmount),
                ]);
            }
        }

        $this->info('Old data migration completed successfully!');
    }
}
