<?php
date_default_timezone_set("Asia/Dhaka");
return [
    'property'=>[
        "name"=>"Cox Vacation",
        "location"=>"Cox's Bazar",
        "email"=> "info@dhakahome.com",
        "mobile"=> "tel:BD:01877-721579(Sales)&01742-839596(Rent)|US:702-202-8214"
        ],
	'serverid'=>2,
    'servicecharge'=>10,
    'vat'=>15,
	'mysqlopeningdate'=>'2019-01-01',
	'openingdate'=>'01/01/2019',
	'yesnoarr'=>[0=>'NO',1=>'YES'],
	'statusarr'=>[0=>'INACTIVE',1=>'ACTIVE', 2=>'PENDING'],
	'year'=>date('Y'),
	'dateformat'=>'d/m/Y',
	'datetimeformat'=>'d/m/Y H:i:s',
	'mysqldateformat'=>'Y-m-d',
	'mysqldatetimeformat'=>'Y-m-d H:i:s',
    'mdateformat'=>'%d/%m/%Y',
    'mdatetimeformat'=>'%d/%m/%Y %T',
//	'partytypearr'=>['USUAL','GUEST', 'AGENT', 'SUPPLIER'],
    'partytypearr'=>['GUEST'],
	'paymentby'=>['DUE','CASH','BANK','MOBILE BANK','CREDIT CARD','ROOM TRANSFER','SUSPENSION'],
	'banktype'=>['DEPOSIT','CC','FC'],
	'roomsalesstatusarr'=>['TENTATIVE','GUARANTEED','CHECKEDIN','CHECKEDOUT','CLOSED','CANCELLED','POSTPONED'],
	'servicetypearr'=>['RESTAURANT','TOUR','SERVICE'],
	'foodtypearr'=>['FOOD', 'BEVERAGE'],
	'servicesalesstatusarr'=>['TENTATIVE', 'RUNNING','CLOSED'],
	'cuisinearr'=>['BANGLADESHI', 'THAI', 'TANDOORI', 'PENTRY'],
	'foodtypearr'=>['FOOD', 'BEVERAGE'],
	'vatregno'=>'000428752',
	'modulesarr' =>['Room Sales', 'Restaurant Sales', 'Tour and Travels', 'Others Service', 'Account'],
    'minid' => 9999999,
    'weeklypeak' => ['FRI','SAT'],
    'xbrate' => 500,
    'checkintime' => '12:00 PM',
    'checkouttime' => '11:00 AM',
    'roomsoldstatus'=>['TENTATIVE','GUARANTEED','CHECKEDIN'],
    'bookingsessiontime'=>'00:15:00 min',   //FORMAT: hh:mm:ss
    'system_idle_time' => '00:30:00 min',    //FORMAT: hh:mm:ss
    'homearr'=>[
        'admin'=>'DASHBOARD',
        'room/sales/create'=>'ROOM SALES',
        'restaurant/sales/form'=>'RESTAURANT SALES',
        'tour/sales/form'=>'TOUR SALES',
        'service/sales/form'=>'SERVICE SALES'
        ],
    'userrolearr'=>[1=>'SUPER ADMIN',2=>'ADMIN',3=>'USER'],
    'emailsender'=>['email'=>'info@dhakahome.com', 'name'=>'Cox Vacation'],
    'notsave'=>'SOMETHING WENT WRONG. PLEASE TRY AGAIN LATER',
    'isRoomCategory'=>false,
    'isNationalityReport'=>true,
    'isMarketReport'=>false,
    'isSourceReport'=>false,
    'isGroupReport'=>false,
    'isRemoteLogin'=>true,
    'remoteLoginLink'=>'https://dh.ahpms.net/api',
    'email'=>[
        'reservation_confirmation'=>[
            'subject'=> 'Reservation Confirmation - RS_CODE',
            'message'=> 'Your Reservation RS_CODE is Confirmed! Please Check  Attachment'
        ],
        'ledger' => [
            'subject' => 'Ledger Report - RS_CODE',
            'message' => 'Ledger Generated For RS_CODE! Please Check  Attachment'
        ],
        'money_receipt' => [
            'subject' => 'Money Receipt - MR_CODE',
            'message' => 'Money Receipt Generated For MR_CODE! Please Check  Attachment'
        ]
    ],
    'migration_status' => "pending", // pending or done
    'truncate_before_migration' => true
];
