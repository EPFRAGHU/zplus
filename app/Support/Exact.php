<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

/**
 * Exact arithmetic for bills, the way the agencies' Excel sheets do it: no figure is rounded along the way;
 * a figure is only shown to 2 decimals, and the grand total is rounded to rupees at the end ("Rounding off").
 */
final class Exact
{
    public static function of(string|int $value): BigRational
    {
        return BigRational::of((string) $value);
    }

    /** a × b ÷ c, exactly. */
    public static function mulDiv(BigRational $a, BigRational|string $b, BigRational|string|int $c): BigRational
    {
        return $a->multipliedBy($b instanceof BigRational ? $b : self::of($b))->dividedBy($c instanceof BigRational ? $c : self::of($c));
    }

    /** Shown to 2 decimals, half up (Excel). */
    public static function money(BigRational $v): string
    {
        return (string) $v->toScale(2, RoundingMode::HalfUp);
    }

    /** Rounded to whole rupees, as "12345.00". */
    public static function rupees(BigRational $v): string
    {
        return (string) BigDecimal::of((string) $v->toScale(0, RoundingMode::HalfUp))->toScale(2);
    }

    public static function zero(): BigRational
    {
        return BigRational::of(0);
    }
}
