<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'unit_id',
        'user_id'
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
        ];
    }
    public function cafes(): BelongsToMany
    {
        return $this->belongsToMany(Cafe::class, 'cafe_user');
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'user_role_area', 'user_id', 'area_id');
    }
    public function roleAssignments(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_area', 'user_id', 'role_id')->withPivot('area_id');
    }

    public function roleAreas(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_area', 'user_id', 'role_id')
            ->withPivot('area_id')
            ->using(UserRoleArea::class);
    }
    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'user_units', 'user_id', 'unit_id');
    }
}
