<?php

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;


    function seoURL($string, $wordLimit = 150){
        $separator = '-';
        if($wordLimit != 0){
         $wordArr = explode(' ', $string);
         $string = implode(' ', array_slice($wordArr, 0, $wordLimit));
        }

        $quoteSeparator = preg_quote($separator, '#');
        $trans = [
           '&.+?;'                  => '',
           '[^\w\d _-]'             => '',
           '\s+'                    => $separator,
           '('.$quoteSeparator.')+' => $separator
          ];

        $string = strip_tags($string);
        foreach ($trans as $key => $val){
        $string = preg_replace('#'.$key.'#i'.('UTF8_ENABLED' ? 'u' : ''), $val, $string);
        }

        $string = strtolower($string);
        return trim(trim($string, $separator));
    }
    function getDateTime($format,$datetime){
        if($datetime == '1970-01-01' || $datetime == '1970-01-01 00:00:00'){
            return '';
        }
        if(date('Y', strtotime($datetime)) < 1970){
            return false;
        }
        $datetime = str_replace('/','-',$datetime);
        return date($format, strtotime($datetime));
    }

    function getPrevDay($date)
    {
        $odate = new DateTime($date);
        $odate->sub(new DateInterval('P1D'));
        return $odate->format(config('arrowheadit.mysqldateformat'));
    }


    function excludeFromArr($arr,$exclude=''){
       if($exclude == ''){
           return $arr;
       }
       if(!is_array($exclude)){
           $exclude = [$exclude];
       }
       return array_diff($arr, $exclude);
    }


    function includeWithArr($arr,$include=''){
       if($include == ''){
           return $arr;
       }
       if(!is_array($include)){
           $include = [$include];
       }
       return array_merge($arr, $include);
    }


    function bdt_format($nStr,$decimal = 2) {
        if($nStr == 0 || $nStr == '0'){
            $nStr = '0.00';
        }
        $nStr = (string)$nStr;
        $sign = substr($nStr,0,1);
        $sign = ($sign == '-')?$sign:'';
        $nStr = ltrim($nStr,'-');

        $x = explode('.',$nStr);
        $x1 = $x[0];
        $x2 = count($x) > 1?$x[1]:0;

        $rgx = '/(\d+)(\d{3})/';
        preg_match($rgx,$x1,$matches);
        $arr_length = count($matches);

        $new_str = '';
        if($arr_length > 0) {
            $last_index = count($matches) - 1;
            $last_thr = $matches[$last_index];
            $other = $matches[$last_index - 1];
            $arr = str_split(strrev($other), '2'); // break string in 2 character sets
            if(count($arr) > 0) {
                krsort($arr);
                $new_arr = array();
                foreach($arr as $val):
                $new_arr[] = strrev($val);
                endforeach;
                $new_str = implode(",", $new_arr);  // implode array with comma echo number_format($other,2);
                $new_str = $new_str.','.$last_thr;
            } else
                $new_str = $other.','.$last_thr;
        } else
            $new_str = $x1;

        if(strlen($x2) > $decimal && $decimal > 0) {
            $x2 = $x2.substr(0,$decimal+1);
            $lastdigit = $x2.substr($decimal,1);
            $x2 = $x2.substr(0,$decimal);
            if($lastdigit > 5)
                $x2 = (int)($x2) + 1;
        }
        $x2 = str_pad($x2,$decimal,'0',STR_PAD_RIGHT);

        $num = '';
        if($decimal == 0)
            $num = $new_str;
        else {
            if($new_str != '0') {
                $new_str = ltrim($new_str, "0");
            }
            if(strlen($x2) > $decimal) {
                $floatVal = number_format(floatval('0.'.$x2),2);
                $arr = explode('.',$floatVal);
                $num = $new_str .'.'. end($arr);
            } else
                $num = $new_str .'.'. $x2;
        }
        return $sign.$num;
    }

    function singleActivate($link = '',$length=0){
        $url = ltrim($_SERVER['REQUEST_URI'],'/');
        $link = rtrim(ltrim($link,'/'),'/');

        if($length > 0){
            $arr = explode('/',$url);
            $length = (count($arr) < $length)?count($arr):$length;
            $narr = [];
            for($i=0;$i<$length;$i++){
               $narr[] = $arr[$i];
            }
            $url = implode('/',$narr);
        }
        return ($link == $url)?'active':'';
    }

    function linkActivate($links = '',$length=0){
        if(is_array($links)){
           foreach($links as $row){
               $length = (isset($row['length']))?$row['length']:0;
               $link = (isset($row['link']))?$row['link']:'';
               if(singleActivate($link,$length) == 'active'){
                    return 'active';
               }
           }
        } else{
            return singleActivate($links,$length);
        }
    }

    function fill_chunck($array, $parts) {
        $t = 0;
        $result = array_fill(0, $parts - 1, array());
        $max = ceil(count($array) / $parts);
        foreach($array as $v) {
            count($result[$t]) >= $max and $t ++;
            $result[$t][] = $v;
        }
        return $result;
    }




    function getChildAll($object, $key, $order='DESC', $glue=', '){
        $array = array_keys($object->groupBy($key)->toArray());
        if($order=='DESC'){
            rsort($array);
        }
        //dd(array_keys($array));
        return implode($glue, $array);
    }



    function is_json($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }


    function numToWord(float $number)
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $hundred = null;
        $digits_length = strlen($no);
        $i = 0;
        $str = array();
        $words = array(0 => '', 1 => 'one', 2 => 'two',
            3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
            7 => 'seven', 8 => 'eight', 9 => 'nine',
            10 => 'ten', 11 => 'eleven', 12 => 'twelve',
            13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
            16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
            19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
            40 => 'forty', 50 => 'fifty', 60 => 'sixty',
            70 => 'seventy', 80 => 'eighty', 90 => 'ninety');
        $digits = array('', 'hundred','thousand','lakh', 'crore');
        while( $i < $digits_length ) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += $divider == 10 ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
            } else $str[] = null;
        }
        $taka = implode('', array_reverse($str));
        //$paisa = ($decimal > 0) ? "." . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paisa' : '';
        $paisa = '';
        return strtoupper( ($taka ? 'taka '.$taka: '') . $paisa);
    }

    function getJobPaymentBy($row){
        if(!is_object($row)){
            return false;
        }

        if(isset($row->paymentmode)){
            if(!is_json($row->paymentmode)){
                return '';
            }
            $payment_arr = json_decode($row->paymentmode);
            if(empty($payment_arr)){
                return '';
            }
            $str = [];
            foreach($payment_arr as $r){
                $str[] = $r->paymendby .': '.$r->amount;
            }
            return implode(', ',$str);
        }
        switch($row->paymentby){
            case'BANK':
            case'MOBILE BANK':
            case'CREDIT CARD':
                return $row->paymentby.': '.$row->bank->short_name;
                break;
            case'DUE':
            case'PARTY':
                return 'PARTY: '.$row->party->name;
                break;
            case'SUSPENSION':
                return 'SUSPENSION: '.$row->suspension->name;
                break;
            default:
                return $row->paymentby;
        }
    }


    function getJobDetails($row){
        if(!is_object($row)){
            return false;
        }

        switch($row->table) {
            case'Voucher_debit':
                $data = $row->debit;
                $data->amount = bdt_format($data->amount);
                $data->paymentby = getJobPaymentBy($data);
                $data->link = 'debit/voucher';
                return $data;
                break;
            case'Voucher_credit':
                $data = $row->credit;
                $data->amount = bdt_format($data->amount);
                $data->paymentby = getJobPaymentBy($data);
                $data->link = 'credit/voucher';
                return $data;
                break;
            case'Voucher_asset':
                $data = $row->asset;
                $data->amount = bdt_format($data->amount);
                $data->paymentby = getJobPaymentBy($data);
                $data->link = 'asset/voucher';
                return $data;
                break;
            case'Voucher_loan':
                $data = $row->loan;
                $data->amount = bdt_format($data->amount);
                $data->paymentby = getJobPaymentBy($data);
                $data->link = 'loan/voucher';
                return $data;
                break;
            case'Voucher_party':
                $data = $row->party;
                $data->amount = 'DEBIT: '.bdt_format($data->debit).' CREDIT: '.bdt_format($data->credit);
                $data->paymentby = getJobPaymentBy($data);
                $data->link = 'part/voucher';
                return $data;
                break;
            case'Voucher_suspension':
                $data = $row->suspension;
                $data->amount = 'DEBIT: '.bdt_format($data->debit).' CREDIT: '.bdt_format($data->credit);
                $data->paymentby = getJobPaymentBy($data);
                $data->link = 'suspension/voucher';
                return $data;
                break;
            case'Voucher_outstanding':
                $data = $row->outstand;
                $data->amount = 'DEBIT: '.bdt_format($data->debit).' CREDIT: '.bdt_format($data->credit);
                $data->paymentby = '';
                $data->link = 'outstanding/voucher';
                return $data;

        }
    }

    function withAmountType($amount){
        if($amount == '0')
            return '0';
        $sign = substr($amount,0,1);


        //Need to work
        return ($sign != '-')?"DR $amount":"CR ".ltrim($amount,'-');
    }

    function getName($nameprefix, $firstname, $lastname){
        return $nameprefix. ' ' . $lastname .' '.$firstname;
    }


    function get_hours_range( $start = 0, $end = 86400, $step = 3600, $format = 'h:i A' ) {
        $times = array();
        foreach ( range( $start, $end, $step ) as $timestamp ) {
            $hour_mins = gmdate( $format, $timestamp );
            if ( ! empty( $format ) )
                $times[$hour_mins] = gmdate( $format, $timestamp );
            else $times[$hour_mins] = $hour_mins;
        }
        return $times;
    }


    function timeToMiliseconds($timestring){
        $timearr = explode(' ',$timestring);
        $timearr = explode(':',$timearr[0]);
        $hh = $mm = $ss = 0;
        if(count($timearr) == 3) {
            $hh = (int)$timearr[0] * 60 * 60 * 1000;
            $mm = (int)$timearr[1] * 60 * 1000;
            $ss = (int)$timearr[2] * 1000;
        }elseif(count($timearr) == 2) {
            $mm = (int)$timearr[0] * 60 * 1000;
            $ss = (int)$timearr[1] * 1000;
        }else {
            $ss = (int)$timearr[0] * 1000;
        }
        return $hh + $mm + $ss;
    }

    function milisecondsToMinute($miloseconds){
        return $miloseconds / (60 * 1000);
    }

    function timeToMinute(){
        return milisecondsToMinute(timeToMiliseconds(config('arrowheadit.bookingsessiontime')));
    }

    function showTab($shown,$tab=true)
    {
        if($shown)
            return '';
        if(!$tab)
            return 'active show';
        return 'active';
    }


    function makeOption($object, $field = 'name')
    {
        $option = '';
        if(is_object($object)) {
            foreach ($object as $row) {
                if(isset($row->$field)){
                    if(isset($row->id)){
                        if(isset($row->loss) && isset($row->inper))
                            $option .= '<option data-loss="'.$row->loss.'" data-inper="'.$row->inper.'" value="' . $row->id . '">' . $row->$field . '</option>';
                        elseif(isset($row->loss))
                            $option .= '<option data-loss="'.$row->loss.'" value="' . $row->id . '">' . $row->$field . '</option>';
                        elseif(isset($row->inper))
                            $option .= '<option data-inper="'.$row->inper.'" value="' . $row->id . '">' . $row->$field . '</option>';
                        elseif(isset($row->inkg))
                            $option .= '<option data-inkg="'.$row->inkg.'" value="' . $row->id . '">' . $row->$field . '</option>';
                        else
                            $option .= '<option value="' . $row->id . '">' . $row->$field . '</option>';
                    }
                    else
                        $option .= '<option value="' . $row->$field . '">' . $row->$field . '</option>';
                }

                else
                    $option .= '<option value="' . $row . '">' . $row . '</option>';
            }
        } elseif(is_array($object)){
            foreach ($object as $row) {
                if(isset($row[$field])) {
                    if (isset($row['id']))
                        $option .= '<option value="' . $row['id'] . '">' . $row[$field] . '</option>';
                    else
                        $option .= '<option value="' . $row[$field] . '">' . $row[$field] . '</option>';
                }
                else
                    $option .= '<option value="' . $row . '">' . $row . '</option>';
            }
        }
//        if($print)
//            dd($option);
        return $option;
    }


    function getUniqueJson($array)
    {
        return json_encode(array_values(getUnique($array)));
    }

    function getUnique($array)
    {
        if(!is_array($array)){
            $array = $array->toArray();
        }
        return array_unique(explode(',',str_replace(', ',',',implode(',',$array))));
    }


    function getRef($obj)
    {
        return "$obj->empid [$obj->name]";
    }

    function getBreak($string, $length = 4){
        // Get all numbers
        $array = explode(',', $string);
// Split into groups of 5 (n)
        $lines = array_chunk($array, $length);
// Format each line as comma delimited
        $formattedLines = array_map(function ($row) { return implode(',', $row); }, $lines);
// Format groups into new lines with commas at the end of each line (except the last)
        return implode(",<br>", $formattedLines);
    }

    function fixDecimal( $number, $decimal_places = 2, $round_type = PHP_ROUND_HALF_UP) {
        return round($number, $decimal_places, $round_type);
    }



