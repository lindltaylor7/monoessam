<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role;

class Staff extends Model
{
    /** @use HasFactory<\Database\Factories\StaffFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'dni',
        'cell',
        'birthdate',
        'age',
        'sex',
        'email',
        'country',
        'civilstatus',
        'contactname',
        'contactcell',
        'status',
        'cafe_id',
        'role_id',
        'user_id',
    ];

    public function photo()
    {
        return $this->hasOne(StaffPhoto::class);
    }

    public function staff_files(): HasMany
    {
        return $this->hasMany(Staff_file::class);
    }

    public function staff_financial(): HasOne
    {
        return $this->hasOne(Staff_financial::class);
    }

    public function staff_clothes(): HasMany
    {
        return $this->hasMany(Staff_clothes::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function staffable(): MorphTo
    {
        return $this->morphTo();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
    public function periods(): BelongsToMany
    {
        return $this->belongsToMany(Period::class, 'period_staffs')->withPivot('status');
    }

    public function guardRole(): HasOne
    {
        return $this->hasOne(Guard_role::class, 'staff_id', 'id');
    }
}
