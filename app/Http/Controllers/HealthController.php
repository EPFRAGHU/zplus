<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Health check for Coolify and uptime monitors. Unlike /up it also confirms the database answers, so a running
 * container with a lost database is reported as down. Registered outside the web group: no session or cookies.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            Log::error('Health check: database not reachable', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'down'], 503);
        }

        return response()->json(['status' => 'ok']);
    }
}
