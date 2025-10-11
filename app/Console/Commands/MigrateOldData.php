<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function PHPUnit\Framework\isEmpty;

class MigrateOldData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-old-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    
    function makeShortName($string) {
        // Split into words by space
        $words = preg_split('/\s+/', trim($string));
        $shortName = '';

        foreach ($words as $word) {
            // Skip if word is only numbers
            // if (ctype_digit($word)) {
            //     continue;
            // }
            // Take first character of word
            $shortName .= strtoupper($word[0]);
        }

        return $shortName;
    }

    function getRoomNameId()
    {
        $data = DB::connection('mariadb_target')->table('room_names')->pluck('id', 'name')->toArray();
        return $data;
    }
    function getFloorId($floorName)
    {
        $data = DB::connection('mariadb_target')->table('room_floors')->pluck('id', 'name')->toArray();
        return $data;
    }
    public function handle()
    {
        ////////// Table: room_categories /////////////////////////
        $oldCategories = DB::connection('mariadb_source')->table('room_categories')->get();

        foreach ($oldCategories as $category) {
            DB::connection('mariadb_target')->table('room_categories')->insert([
                'name'=>$category->name,
                'is_active'=>$category->isactive,
                'created_at'=>now(),
                'updated_at'=>now()
            ]);
        }
        ////////// Table: room_floors /////////////////////////
        $oldFloors = DB::connection('mariadb_source')->table('room_floors')->get();

        foreach ($oldFloors as $oldFloor) {
            DB::connection('mariadb_target')->table('room_floors')->insert([
                'name' => $oldFloor->name,
                'is_active' => $oldFloor->isactive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        ////////// Table: room_names /////////////////////////
        $oldRoomStock = DB::connection('mariadb_source')->table('room_stock_details')->get()->unique('name');
        //dd($oldRoomStock);
        $counter = 0;
        foreach ($oldRoomStock as $oldRoomStock) {
        $counter++;
            DB::connection('mariadb_target')->table('room_names')->insert([
                'name' => $oldRoomStock->name,
                'short_name' => $this->makeShortName($oldRoomStock->name)?? "room-".$counter,
                'category_id' => $oldRoomStock->catid,
                'bed' => 'DBL',
                'rate' => $oldRoomStock->rate,
                'rate_xb' =>0,
                'is_active' => $oldRoomStock->isactive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
            //////////// Table: room_numbers /////////////////////////
            $roomNameArray = DB::connection('mariadb_target')->table('room_names')->pluck('id', 'name')->toArray();
           $roomFloorArray =  DB::connection('mariadb_target')->table('room_floors')->pluck('id', 'name')->toArray();
            $oldRoomStock = DB::connection('mariadb_source')->table('room_stock_details')->get();
            foreach ($oldRoomStock as $oldRoomStock) {
                DB::connection('mariadb_target')->table('room_numbers')->insert([
                    'room_name_id' => $roomNameArray[$oldRoomStock->name],
                    'floor_id' => $roomFloorArray[$oldRoomStock->floorno] ,
                    'name' => $oldRoomStock->roomno,
                    'is_clean' => 1,                
                    'is_active' => $oldRoomStock->isactive,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

        ////////// Table: users /////////////////////////
       
        $oldUsers = DB::connection('mariadb_source')->table('reg_staffs as s')->leftJoin('users as u','s.userid','=','u.id')->select('s.name','u.username','s.email','s.contact','s.designation','u.password','s.isref','s.id')->get();
        //dd($oldUsers);
        foreach ($oldUsers as $oldUser) {
            $userName = $oldUser->username;
            if(in_array($oldUser->id, [2000000012, 2000000013, 2000000018])){
                $userName = explode('@', $oldUser->email)[0];
            }
            $email = $oldUser->email;
            if($oldUser->id == 2000000017){
                $email = 'info@arrowheadit.com';
            }
           DB::connection('mariadb_target')->table('users')->insert([
                'name' => $oldUser->name,
                'user_name' => $userName,
                'role' => 'admin',
                'type' => 'user',
                'status' => 'active',
                'email' => $email,
                'phone' => $oldUser->contact,
                'designation' => $oldUser->designation,
                'password' => $oldUser->password,
                'is_ref' => $oldUser->isref,
                'email_verified_at' => now(),
                'osid' => $oldUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        //////////// Table: guests /////////////////////////
        $nameAddressArray = [
            'MALE' => 'Mr.',
            'FEMALE' => 'Mrs.'
        ];
        $newUserArray = DB::connection('mariadb_target')->table('users')->pluck('id', 'osid')->toArray();
        //dd($newUserArray);
        $oldGuests = DB::connection('mariadb_source')->table('room_guests')->get();
        foreach ($oldGuests as $oldGuest) {
            $birthdate = $oldGuest->birthdate;
            if($birthdate== '1970-01-01'){
                $birthdate = NULL;
            }
            $marriagedate = $oldGuest->marriagedate;
            if($marriagedate== '1970-01-01'){
                $marriagedate = NULL;
            }

            DB::connection('mariadb_target')->table('guests')->insert([
                'name_address'      => $nameAddressArray[$oldGuest->gender],
                'last_name'          => $oldGuest->lastname,
                'first_name'         => $oldGuest->firstname,
                'email'              => $oldGuest->email,
                'mobile'             => $oldGuest->cell,
                'secondary_mobile'   => $oldGuest->sec_cell,
                'emergency_contact'  => $oldGuest->emergency_contact??NULL,
                'dob'                => $birthdate,
                'dom'                => $marriagedate,
                'pres_country_id'    => $oldGuest->presentcountryid,
                'pres_city'          => $oldGuest->presentcity,
                'pres_address'       => $oldGuest->presentaddress,
                'perm_country_id'    => $oldGuest->permanentcountryid,
                'perm_city'          => $oldGuest->permanentcity,
                'perm_address'       => $oldGuest->permanentaddress,
                'source'             => $oldGuest->source,
                'profession'         => $oldGuest->profession,
                'market'             => $oldGuest->market,
                'company'            => $oldGuest->company,
                'nid'                => $oldGuest->nid,
                'passport'           => $oldGuest->passport,
                'passport_date'      => $oldGuest->passportdateplace,
                'passport_exp_date'  => NULL,
                'nationality_id'     => $oldGuest->nationality,
                'ref_id'             => $newUserArray[$oldGuest->referenceid],
                'oid'                => $oldGuest->id,
                'created_at'         => now(),
                'updated_at'         => now()
            ]);
        }

    }
}
