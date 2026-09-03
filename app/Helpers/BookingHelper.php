<?php


if(!function_exists('booking_fare_break_down')){
    function booking_fare_break_down($break_down){
        $new_break_down = [];
        foreach($break_down as $key => $value){
            $new_break_down[$key] = [
                ...$value,
                'base_fare'    => numberFormat($value['base_fare']),
                'tax'          => numberFormat($value['tax']),
                'gross_amount' => numberFormat($value['gross_amount']),
                'discount_psf' => numberFormat($value['discount_psf']),
                'total_amount' => numberFormat($value['total_amount']),
            ];
        }

        return $new_break_down;
    }
}

if(!function_exists('old_pricing')){
    function old_pricing($validate){
        unset($validate['remarks']);
        return [
            ...$validate,
            'fare_break_down'   => booking_fare_break_down($validate['fare_break_down'])
        ];
    }
}
?>