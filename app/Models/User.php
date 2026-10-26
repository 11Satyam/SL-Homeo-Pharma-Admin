<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'distributor_id',
    ];

    protected $attributes = ['status' => 'active', 'role' => 'viewer'];

    public const ROLES = ['admin', 'inventory_manager', 'accounts_manager', 'distributor', 'staff', 'viewer'];

    /** Indian mobile → 10 digits ("+91 98765-43210", "098765 43210" → "9876543210"); null if not a valid mobile. */
    public static function normalizePhone(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        } elseif (strlen($d) === 11 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $d) ? $d : null;
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Distributor-scoped roles may only see their own distributor's records. */
    public function scopedDistributorId(): ?int
    {
        return $this->hasRole('distributor', 'staff') ? (int) $this->distributor_id : null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }
}
