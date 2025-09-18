<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ReservationNotification;
use App\Models\User;
use App\Models\Customer;

class EmailServices
{
    public static function sendReservationMail($mailDetails)
    {
       
        //$user = Customer::where('email', $mailDetails['customer_email'])->first();
        //dd($user);
    //    return Notification::send($user, new QuotationNotification($mailDetails));
   // dd($mailDetails);
       return Notification::route('mail', [
            "{$mailDetails['customer_email']}" => "{$mailDetails['customer_name']}"
        ])->notify(new ReservationNotification($mailDetails));
    }

}
