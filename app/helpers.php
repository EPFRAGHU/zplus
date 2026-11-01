<?php

if (! function_exists('inr')) {
    /** Indian grouping: 105387 => "₹1,05,387"; with $paise => "₹1,05,387.00". */
    function inr(mixed $amount, bool $paise = false): string
    {
        $value = (float) $amount;
        $neg = $value < 0;
        $fixed = number_format(abs($value), 2, '.', '');
        [$int, $dec] = explode('.', $fixed);
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $grouped = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3 : $last3;

        return ($neg ? '-' : '').'₹'.$grouped.($paise ? '.'.$dec : '');
    }
}
