<?php

namespace App\Helpers;

class MathHelper
{
    public static function fixDecimal( $number, $decimal_places = 2, $round_type = PHP_ROUND_HALF_UP) {
        return round($number, $decimal_places, $round_type);
    }

}
