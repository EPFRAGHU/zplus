<?php

namespace App\Support\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Writes activity log entries. Logging never breaks the action being logged. */
final class Activity
{
    /** Fields never shown in full: only "changed" plus the last 4 digits. */
    public const SECRET = ['account_number', 'aadhaar', 'bank_account_no'];

    /** Fields that are noise in the log. */
    public const IGNORED = ['created_at', 'updated_at', 'remember_token', 'last_login_at', 'account_last4', 'aadhaar_last4', 'paid_days'];

    /** Readable names of the logged models. */
    public const SECTIONS = [
        \App\Models\Agency::class => 'Agency', \App\Models\Client::class => 'Client', \App\Models\GemContract::class => 'GeM contract',
        \App\Models\Project::class => 'Project', \App\Models\Post::class => 'Post', \App\Models\Employee::class => 'Employee',
        \App\Models\EmployeeDocument::class => 'Employee document', \App\Models\AttendanceSheet::class => 'Attendance',
        \App\Models\Invoice::class => 'Sub-bill', \App\Models\TaxInvoice::class => 'Tax invoice', User::class => 'User',
    ];

    public static function log(string $action, ?Model $subject = null, array $changes = [], ?string $note = null, ?User $user = null,
        ?string $section = null, ?string $label = null): void
    {
        try {
            $user ??= auth()->user();
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
            ActivityLog::query()->create([
                'user_id' => $user?->id, 'user_name' => $user?->name ?? 'System', 'user_role' => $user?->role,
                'action' => $action, 'section' => $section ?? ($subject ? (self::SECTIONS[$subject::class] ?? class_basename($subject)) : null),
                'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(),
                'subject_label' => $label ?? ($subject ? self::label($subject) : null),
                'changes' => $changes ?: null, 'note' => $note ? mb_substr($note, 0, 500) : null,
                'ip' => $request?->ip(), 'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** A short name for a record, e.g. "EMP-1001 · Amit Kumar". */
    public static function label(Model $m): string
    {
        $a = $m->getAttributes();
        $parts = array_filter([
            $a['emp_code'] ?? $a['code'] ?? $a['short_code'] ?? $a['contract_no'] ?? $a['invoice_no'] ?? $a['number'] ?? null,
            $a['name'] ?? $a['designation'] ?? $a['original_name'] ?? null,
        ]);
        if ($m instanceof \App\Models\AttendanceSheet) {
            $parts = [$m->project?->name, $m->month?->format('M Y')];
        }
        if ($m instanceof \App\Models\User) {
            $parts = [$a['name'] ?? null, $a['email'] ?? null];
        }

        return implode(' · ', array_filter($parts)) ?: '#'.$m->getKey();
    }

    /** Old → new for the fields that changed, with secrets masked. */
    public static function diff(Model $m): array
    {
        $out = [];
        foreach ($m->getDirty() as $field => $new) {
            if (in_array($field, self::IGNORED, true)) {
                continue;
            }
            $before = self::show($field, $m->getOriginal($field));
            $after = self::show($field, $m->getAttribute($field));
            if ($before === $after || (($before === null || $before === '') && ($after === null || $after === ''))) {
                continue;   // set again to the same value: not a change
            }
            $out[$field] = [$before, $after];
        }

        return $out;
    }

    /** Values of a new or deleted record. */
    public static function snapshot(Model $m): array
    {
        $out = [];
        foreach ($m->getAttributes() as $field => $value) {
            if (in_array($field, self::IGNORED, true) || $field === 'id' || $value === null || $value === '') {
                continue;
            }
            $out[$field] = self::show($field, $m->getAttribute($field));
        }

        return $out;
    }

    private static function show(string $field, mixed $value): mixed
    {
        if ($field === 'password' || $field === 'pin_hash') {
            return $value === null ? null : '(set)';
        }
        if (in_array($field, self::SECRET, true)) {
            $digits = preg_replace('/\D/', '', (string) $value);

            return $digits === '' ? null : '••••'.substr($digits, -4);
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return is_string($value) ? mb_substr($value, 0, 300) : $value;
    }
}
