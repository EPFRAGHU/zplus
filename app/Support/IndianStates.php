<?php

namespace App\Support;

/** States / UTs of India with GST state codes and districts (config/india.php). */
final class IndianStates
{
    /** @return array<string, string> code => "Odisha (21)" for dropdowns */
    public static function options(): array
    {
        return collect(config('india'))->mapWithKeys(fn ($s, $code) => [(string) $code => $s['name'].' ('.$code.')'])->sortBy(fn ($v) => $v)->all();
    }

    public static function name(?string $code): ?string
    {
        return $code === null ? null : (config('india.'.$code.'.name'));
    }

    /** @return string[] */
    public static function districts(?string $code): array
    {
        return $code === null ? [] : (config('india.'.$code.'.districts') ?? []);
    }

    /** code => [districts] for the form script */
    public static function districtMap(): array
    {
        return collect(config('india'))->map(fn ($s) => $s['districts'])->all();
    }

    /** Validation: one of the GST state codes. */
    public static function rule(): string
    {
        return 'in:'.implode(',', array_map('strval', array_keys(config('india'))));
    }
}
