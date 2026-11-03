<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Makes one Admin the Super Admin (the only reader of the activity log). There is always exactly one. */
class MakeSuperAdmin extends Command
{
    protected $signature = 'users:super-admin {email : E-mail of an existing Admin user}';

    protected $description = 'Make this Admin the Super Admin (moves the role from anyone else)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user || $user->role !== 'admin') {
            $this->error('No Admin user with that e-mail.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user) {
            User::query()->where('is_super_admin', true)->where('id', '!=', $user->id)->get()->each(fn ($u) => $u->forceFill(['is_super_admin' => false])->save());
            $user->forceFill(['is_super_admin' => true])->save();
        });
        $this->info("{$user->name} ({$user->email}) is now the Super Admin.");

        return self::SUCCESS;
    }
}
