<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Server recovery / handover: makes one Admin the developer Super Admin (Users, Activity log, imports, approvals).
 * The previous developer loses both; other Super Admins (the second owner, who approves and deletes) are kept.
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'users:super-admin {email : E-mail of an existing Admin user}';

    protected $description = 'Make this Admin the developer Super Admin (moves the developer role; other Super Admins stay)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user || $user->role !== 'admin') {
            $this->error('No Admin user with that e-mail.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user) {
            User::query()->where('is_developer', true)->where('id', '!=', $user->id)->get()->each(fn ($u) => $u->forceFill(['is_super_admin' => false, 'is_developer' => false])->save());
            $user->forceFill(['is_super_admin' => true, 'is_developer' => true])->save();
        });
        $this->info("{$user->name} ({$user->email}) is now the Super Admin.");

        return self::SUCCESS;
    }
}
