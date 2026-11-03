<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_active',
        'sales_location_id',
    ];

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
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class, 'access_group_user');
    }

    public function salesLocation()
    {
        return $this->belongsTo(Location::class, 'sales_location_id');
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    public function hasPermission(string $key): bool
    {
        if (! $this->is_active) return false;
        if ($this->is_super_admin) return true;

        return $this->directPermissions()->where('key', $key)->exists()
            || $this->groups()->whereHas('permissions', fn ($query) => $query->where('key', $key))->exists();
    }

    public function canViewSalesProfit(): bool
    {
        if (! $this->is_active) return false;
        if ($this->is_super_admin) return true;

        return $this->directPermissions()->where('key', 'sales.profit.view')->exists();
    }
}
