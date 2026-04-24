<?php
if (!function_exists('customRound')) {
    function customRound($amount) {
        $decimal = $amount - floor($amount);
        if ($decimal >= 0.50) {
            return ceil($amount);
        }
        return round($amount, 2);
    }
}
