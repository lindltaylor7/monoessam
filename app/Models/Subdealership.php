<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subdealership extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'ruc', 'fiscal_address', 'legal_address', 'phone', 'email', 'dealership_id'];

    public function dealership(): BelongsTo
    {
        return $this->belongsTo(Dealership::class);
    }
    public function dinners(): HasMany
    {
        return $this->hasMany(Dinner::class);
    }
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'subdealership_unit');
    }
    public function mines(): BelongsToMany
    {
        return $this->belongsToMany(Mine::class, 'mine_subdealerships', 'subdealership_id', 'mine_id');
    }
}
