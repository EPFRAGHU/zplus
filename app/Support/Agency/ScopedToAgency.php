<?php

namespace App\Support\Agency;

/** Records that belong to one agency: a single-agency login only ever sees its own. */
trait ScopedToAgency
{
    public static function bootScopedToAgency(): void
    {
        static::addGlobalScope(new AgencyScope);
    }
}
