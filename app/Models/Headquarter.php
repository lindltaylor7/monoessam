<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Headquarter extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'business_id', 'latitude', 'longitude', 'address'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_headquarter', 'headquarter_id', 'area_id');
    }
}
