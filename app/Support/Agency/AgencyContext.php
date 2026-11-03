<?php

namespace App\Support\Agency;

/** The agency the signed-in user works for, or null when they work for both agencies (or no one is signed in). */
final class AgencyContext
{
    private static bool $bypass = false;

    public static function id(): ?int
    {
        if (self::$bypass) {
            return null;
        }
        $user = auth()->hasUser() ? auth()->user() : null;

        return $user?->agency_id ? (int) $user->agency_id : null;
    }

    /** Run something across both agencies (e.g. checking that a code is unique everywhere). */
    public static function unscoped(callable $callback): mixed
    {
        $before = self::$bypass;
        self::$bypass = true;
        try {
            return $callback();
        } finally {
            self::$bypass = $before;
        }
    }
}
