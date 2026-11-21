<?php

namespace App\Services\Approvals;

use App\Models\Approval;
use App\Models\Employee;
use App\Models\Post;
use App\Models\User;
use App\Support\Activity\Activity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owner approval, release 2 (owner, 17 Nov 2026): staff and pay changes wait for the owner (Super Admin).
 *  - a new employee is saved Inactive (so not on attendance or bills) until approved, then becomes Active
 *  - changes to post, own rate, bank details and the EPF / ESIC / TDS switches of an employee are held, and applied
 *    only when approved (other details such as name or mobile are saved at once)
 *  - a change of a post's rate is held the same way
 * Strict four-eyes as for bills: whoever made the change can never approve it.
 */
final class StaffChanges
{
    public const WATCHED = ['post_id', 'rate_override', 'bank_name', 'account_holder', 'account_number', 'ifsc', 'pf_applicable', 'esic_applicable', 'tds_applicable'];

    public const LABELS = ['post_id' => 'Post', 'rate_override' => 'Own rate', 'bank_name' => 'Bank', 'account_holder' => 'Name as per bank',
        'account_number' => 'Account no.', 'ifsc' => 'IFSC', 'pf_applicable' => 'EPF', 'esic_applicable' => 'ESIC', 'tds_applicable' => 'TDS', 'rate' => 'Rate', 'status' => 'Status'];

    public static function applies(): bool
    {
        return (bool) config('approvals.staff', true);
    }

    /** Adds an employee; while approvals apply it is kept Inactive and sent to the owner. */
    public function create(array $data, User $maker): Employee
    {
        if (! self::applies()) {
            return Employee::query()->create($data);
        }

        return DB::transaction(function () use ($data, $maker) {
            $employee = Employee::query()->create(['status' => 'inactive'] + $data);
            $employee->forceFill(['status' => 'inactive'])->save();
            // Fingerprint the record as stored: switches left out of $data are filled by database defaults.
            $this->sendNew($employee->refresh(), $maker);

            return $employee;
        });
    }

    /** Is this a new employee whose first approval has not been given yet? */
    public function awaitingFirstApproval(Employee $employee): bool
    {
        $latest = $this->latest('new_employee', $employee->id);

        return $latest !== null && $latest->status !== 'approved';
    }

    /**
     * Saves an edit. Watched fields that change are held for approval (one open change per employee at a time);
     * everything else is saved at once. @return list<string> labels of the held fields
     */
    public function update(Employee $employee, array $data, User $maker): array
    {
        if (! self::applies()) {
            $employee->update($data);

            return [];
        }
        if ($this->awaitingFirstApproval($employee)) {
            // Not yet approved: still Inactive, so details may be corrected freely; the owner approves the latest version,
            // so a waiting request is brought up to date with the correction (owner, 23 Nov 2026).
            unset($data['status']);
            DB::transaction(function () use ($employee, $data, $maker) {
                $employee->update($data);
                $pending = Approval::query()->where('type', 'new_employee')->where('employee_id', $employee->id)->where('status', 'pending')->exists();
                if ($pending && $this->changedSinceSending($employee->refresh())) {
                    $this->sendNew($employee, $maker);
                }
            });

            return [];
        }
        $held = [];
        foreach (self::WATCHED as $field) {
            if (array_key_exists($field, $data) && ! self::same($employee->{$field}, $data[$field])) {
                $held[$field] = [self::plain($employee->{$field}), self::plain($data[$field])];
            }
            unset($data[$field]);
        }

        return DB::transaction(function () use ($employee, $data, $held, $maker) {
            if ($held && $this->open('employee_change', $employee->id)) {
                throw new RuntimeException('A change of this employee\'s post, rate, bank or EPF/ESIC/TDS is already waiting for the owner. Wait for the decision before changing them again.');
            }
            $employee->update($data);
            if ($held) {
                Approval::query()->create(['agency_id' => $employee->agency_id, 'type' => 'employee_change', 'employee_id' => $employee->id,
                    'month' => now()->startOfMonth()->toDateString(), 'label' => "Change: {$employee->emp_code} {$employee->name} (".implode(', ', array_map(fn ($f) => self::LABELS[$f], array_keys($held))).')',
                    'fingerprint' => $this->oldHash($held), 'changes' => $held, 'status' => 'pending', 'submitted_by' => $maker->id, 'submitted_at' => now()]);
                Activity::log('status', $employee, [], 'Sent for approval: '.implode(', ', array_map(fn ($f) => self::LABELS[$f], array_keys($held))), section: 'Approvals', label: $employee->emp_code);
            }

            return array_map(fn ($f) => self::LABELS[$f], array_keys($held));
        });
    }

    /** A post's rate change waits for the owner; designation and sanctioned number are saved at once. @return bool held */
    public function updatePost(Post $post, array $data, User $maker): bool
    {
        if (! self::applies() || ! array_key_exists('rate', $data) || self::same($post->rate, $data['rate'])) {
            $post->update($data);

            return false;
        }
        $held = ['rate' => [self::plain($post->rate), self::plain($data['rate'])]];
        unset($data['rate']);

        return DB::transaction(function () use ($post, $data, $held, $maker) {
            if ($this->open('post_rate', $post->id, 'post_id')) {
                throw new RuntimeException('A rate change of this post is already waiting for the owner.');
            }
            $post->update($data);
            $post->loadMissing('project.contract');
            Approval::query()->create(['agency_id' => $post->project->contract->agency_id, 'type' => 'post_rate', 'post_id' => $post->id,
                'month' => now()->startOfMonth()->toDateString(), 'label' => "Rate of {$post->designation} ({$post->project->code}): ₹{$held['rate'][0]} → ₹{$held['rate'][1]}",
                'fingerprint' => $this->oldHash($held), 'changes' => $held, 'status' => 'pending', 'submitted_by' => $maker->id, 'submitted_at' => now()]);
            Activity::log('status', $post, [], "Sent for approval: rate ₹{$held['rate'][0]} → ₹{$held['rate'][1]}", section: 'Approvals', label: $post->designation);

            return true;
        });
    }

    /** "Send again" for a new employee that was returned or changed after sending. */
    public function sendNew(Employee $employee, User $maker): Approval
    {
        $fingerprint = $this->newHash($employee);
        $open = Approval::query()->where('type', 'new_employee')->where('employee_id', $employee->id)->where('status', 'pending')->get();
        if ($open->contains(fn ($a) => $a->fingerprint === $fingerprint)) {
            throw new RuntimeException('Already sent with these same details.');
        }
        $open->each(fn ($a) => $a->update(['status' => 'superseded']));
        $approval = Approval::query()->create(['agency_id' => $employee->agency_id, 'type' => 'new_employee', 'employee_id' => $employee->id,
            'month' => now()->startOfMonth()->toDateString(), 'label' => "New employee {$employee->emp_code} {$employee->name}",
            'fingerprint' => $fingerprint, 'changes' => $this->snapshot($employee), 'status' => 'pending', 'submitted_by' => $maker->id, 'submitted_at' => now()]);
        Activity::log('status', $employee, [], 'Sent for approval: new employee', section: 'Approvals', label: $employee->emp_code);

        return $approval;
    }

    /** Applies an approved change. Called by Approvals::approve (four-eyes already checked). @return string message */
    public function apply(Approval $approval): string
    {
        return match ($approval->type) {
            'new_employee' => (function () use ($approval) {
                $employee = $approval->employee;
                if ($changed = $this->changedFields($approval, $employee)) {
                    throw new RuntimeException('The employee\'s details were changed after sending ('.implode(', ', $changed).'). Return it, or ask for it to be sent again with the employee\'s "Send again for approval" button.');
                }
                $employee->forceFill(['status' => 'active'])->save();

                return "{$employee->name} ({$employee->emp_code}) is now Active.";
            })(),
            'employee_change' => (function () use ($approval) {
                $employee = $approval->employee;
                $changes = $approval->changes;
                if ($this->oldHash(array_map(fn ($f) => [self::plain($employee->{$f}), null], array_combine(array_keys($changes), array_keys($changes)))) !== $approval->fingerprint) {
                    throw new RuntimeException('These details were changed in another way after sending. Ask for the change to be made again.');
                }
                $employee->update(array_map(fn ($c) => $c[1], $changes));

                return "Change of {$employee->name} ({$employee->emp_code}) applied.";
            })(),
            'post_rate' => (function () use ($approval) {
                $post = $approval->post;
                if ($this->oldHash(['rate' => [self::plain($post->rate), null]]) !== $approval->fingerprint) {
                    throw new RuntimeException('The rate was changed in another way after sending.');
                }
                $post->update(['rate' => $approval->changes['rate'][1]]);

                return "New rate of {$post->designation} applied.";
            })(),
        };
    }

    /** Is the waiting "new employee" request of this employee out of date with the saved details? */
    public function changedSinceSending(Employee $employee): bool
    {
        $open = Approval::query()->where('type', 'new_employee')->where('employee_id', $employee->id)->where('status', 'pending')->latest('id')->first();

        return $open !== null && $this->changedFields($open, $employee) !== [];
    }

    /** Labels of the watched details that differ from what was sent; [] when unchanged. */
    public function changedFields(Approval $approval, Employee $employee): array
    {
        if ($this->newHash($employee) === $approval->fingerprint || $this->unchangedApartFromDefaults($approval, $employee)) {
            return [];
        }
        $sent = is_array($approval->changes) ? $approval->changes : [];
        $labels = self::LABELS + ['name' => 'Name', 'emp_code' => 'Employee ID'];
        $out = [];
        foreach ($this->snapshot($employee) as $field => $now) {
            if (($sent[$field] ?? null) !== $now) {
                $out[] = $labels[$field] ?? $field;
            }
        }

        return $out ?: ['details'];
    }

    /**
     * Requests sent before 23 Nov 2026 could record a switch the form left out as blank, while the database stored its
     * default. Such a request still counts as unchanged when the stored snapshot is the one that was fingerprinted and every
     * difference is a blank switch that now holds its database default.
     */
    private function unchangedApartFromDefaults(Approval $approval, Employee $employee): bool
    {
        $sent = $approval->changes;
        if (! is_array($sent) || hash('sha256', json_encode($sent)) !== $approval->fingerprint) {
            return false;
        }
        $defaults = ['pf_applicable' => '0', 'esic_applicable' => '0', 'tds_applicable' => '1'];
        foreach ($this->snapshot($employee) as $field => $now) {
            $then = $sent[$field] ?? null;
            if ($then !== $now && ! ($then === null && array_key_exists($field, $defaults) && $now === $defaults[$field])) {
                return false;
            }
        }

        return true;
    }

    public function latest(string $type, int $id, string $column = 'employee_id'): ?Approval
    {
        return Approval::query()->with('submitter', 'decider')->where('type', $type)->where($column, $id)->latest('id')->first();
    }

    private function open(string $type, int $id, string $column = 'employee_id'): bool
    {
        return Approval::query()->where('type', $type)->where($column, $id)->where('status', 'pending')->lockForUpdate()->exists();
    }

    /** Hash of the OLD values only (so approval can check nothing else changed them meanwhile). */
    private function oldHash(array $held): string
    {
        ksort($held);

        return hash('sha256', json_encode(array_map(fn ($c) => $c[0], $held)));
    }

    private function newHash(Employee $employee): string
    {
        return hash('sha256', json_encode($this->snapshot($employee)));
    }

    /** Details the owner approves for a new employee. */
    private function snapshot(Employee $employee): array
    {
        $out = ['name' => $employee->name, 'emp_code' => $employee->emp_code];
        foreach (self::WATCHED as $f) {
            $out[$f] = self::plain($employee->{$f});
        }

        return $out;
    }

    private static function plain(mixed $v): ?string
    {
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v) && str_contains((string) $v, '.')) {
            return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        }

        return (string) $v;
    }

    private static function same(mixed $a, mixed $b): bool
    {
        return self::plain($a) === self::plain($b);
    }
}
