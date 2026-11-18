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
        if (! $user) {
            return null;
        }
        if ($user->agency_id) {
            return (int) $user->agency_id;
        }

        return self::chosen($user);
    }

    /**
     * A login for both agencies (e.g. a Super Admin) picks one company after signing in (owner, 18 Nov 2026);
     * the choice lives in the session until sign-out. Null = "Both agencies".
     */
    public static function chosen(?\App\Models\User $user = null): ?int
    {
        $user ??= auth()->hasUser() ? auth()->user() : null;
        if (! $user || $user->agency_id || $user->isPi() || ! app()->bound('request') || ! request()->hasSession()) {
            return null;
        }
        $id = request()->session()->get(self::SESSION_KEY);

        return $id ? (int) $id : null;
    }

    public const SESSION_KEY = 'working_agency_id';

    /** Logins that may switch company: both-agency staff (not PIs). */
    public static function canSwitch(?\App\Models\User $user): bool
    {
        return $user !== null && ! $user->agency_id && ! $user->isPi();
    }

    /** The agency being worked in (own agency or the one chosen), or null for both. */
    public static function agency(): ?\App\Models\Agency
    {
        $id = self::id();

        return $id ? self::unscoped(fn () => \App\Models\Agency::query()->find($id)) : null;
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
