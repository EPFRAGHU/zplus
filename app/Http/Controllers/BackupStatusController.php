<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Database backups reported by the backup scripts (owner, 26 Nov 2026). Developer only. */
class BackupStatusController extends Controller
{
    public function index(): View
    {
        Gate::authorize('view-activity');

        return view('backups.index', ['runs' => BackupRun::query()->latest('finished_at')->latest('id')->limit(100)->get()]);
    }
}
