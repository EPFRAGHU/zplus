<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use LogsActivity;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** New accounts can sign in; only accounts made or reset on the Users screen start with a temporary password. */
    protected $attributes = ['active' => true, 'must_change_password' => false];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'is_super_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Agency staff roles, then PI (project in-charge at the client), who only sees their own projects. */
    public const ROLES = ['admin' => 'Admin', 'accounts' => 'Accounts', 'supervisor' => 'Operator', 'pi' => 'PI (project in-charge)'];

    public const STAFF_ROLES = ['admin', 'accounts', 'supervisor'];

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** The owner: the only account that can read the activity log. Set with `php artisan users:super-admin`. */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin && $this->role === 'admin';
    }

    public function isPi(): bool
    {
        return $this->role === 'pi';
    }

    /** The agency this login works for; null = both agencies. */
    public function agency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function worksForBothAgencies(): bool
    {
        return $this->agency_id === null;
    }

    /** Projects a PI is in charge of. */
    public function projects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }
}
