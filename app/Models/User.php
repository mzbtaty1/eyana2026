<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
//use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 
use App\Support\Permissions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_id',
        'status',
        'account_type',
        'commission',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /** Admin («محاسب / مسئول», account_type 2): has every ability. */
    public function isAdmin(): bool
    {
        return (int) $this->account_type === 2;
    }

    /**
     * This user's abilities (App\Support\Permissions). Admin: all. Employee: the stored
     * per-employee list once that column exists (later step), otherwise the defaults --
     * never an admin-only ability.
     */
    public function permissionList(): array
    {
        if ($this->isAdmin()) {
            return array_keys(Permissions::ALL);
        }
        $stored = $this->getAttributes()['permissions'] ?? null;
        $list = is_string($stored) ? json_decode($stored, true) : $stored;
        $list = is_array($list) ? $list : Permissions::EMPLOYEE_DEFAULT;
        return array_values(array_diff($list, Permissions::ADMIN_ONLY));
    }

    public function hasPermission(string $ability): bool
    {
        return (string) $this->status === '1' && in_array($ability, $this->permissionList(), true);
    }
}
