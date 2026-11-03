<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Activity\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Super Admin only: who did what, when — every add, edit, delete, status change, view of sensitive data, download and sign-in. */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('view-activity');
        $logs = $this->query($request)->paginate(50)->withQueryString();

        return view('activity.index', [
            'logs' => $logs, 'filters' => $request->only(['user', 'action', 'section', 'from', 'to', 'q']),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
            'sections' => ActivityLog::query()->whereNotNull('section')->distinct()->orderBy('section')->pluck('section'),
            'today' => ActivityLog::query()->where('created_at', '>=', now()->startOfDay())->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('view-activity');
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('Activity log');
        $ws->fromArray(['When (IST)', 'User', 'Role', 'Action', 'Section', 'Record', 'Changes', 'Note', 'IP'], null, 'A1');
        $ws->getStyle('A1:I1')->getFont()->setBold(true);
        $r = 2;
        foreach ($this->query($request)->limit(20000)->cursor() as $log) {
            $ws->fromArray([
                $log->created_at->timezone('Asia/Kolkata')->format('d-m-Y H:i:s'), $log->user_name, User::ROLES[$log->user_role] ?? $log->user_role,
                ActivityLog::ACTIONS[$log->action][0] ?? $log->action, $log->section, $log->subject_label, self::changesText($log->changes), $log->note, $log->ip,
            ], null, "A{$r}");
            $r++;
        }
        foreach (['A' => 18, 'B' => 20, 'C' => 12, 'D' => 14, 'E' => 16, 'F' => 34, 'G' => 70, 'H' => 40, 'I' => 15] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        Activity::log('downloaded', null, [], 'Activity log export ('.($r - 2).' rows)', section: 'Activity log');

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'activity-log-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public static function field(string $key): string
    {
        return ucfirst(str_replace(['_id', '_'], ['', ' '], $key));
    }

    public static function changesText(?array $changes): string
    {
        return collect($changes ?? [])->map(fn ($v, $k) => self::field((string) $k).': '.(is_array($v) ? ($v[0] ?? '—').' → '.($v[1] ?? '—') : $v))->implode('; ');
    }

    private function query(Request $request): Builder
    {
        return ActivityLog::query()
            ->when($request->integer('user'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->input('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->input('section'), fn ($q, $s) => $q->where('section', $s))
            ->when($request->input('from'), fn ($q, $d) => $q->where('created_at', '>=', CarbonImmutable::parse($d, 'Asia/Kolkata')->startOfDay()->utc()))
            ->when($request->input('to'), fn ($q, $d) => $q->where('created_at', '<=', CarbonImmutable::parse($d, 'Asia/Kolkata')->endOfDay()->utc()))
            ->when(trim((string) $request->input('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('subject_label', 'like', "%{$s}%")
                ->orWhere('note', 'like', "%{$s}%")->orWhere('user_name', 'like', "%{$s}%")))
            ->orderByDesc('id');
    }
}
