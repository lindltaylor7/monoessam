<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Unit extends Model
{
    protected $fillable = ['name', 'mine_id'];


    public function mine(): BelongsTo
    {
        return $this->belongsTo(Mine::class);
    }

    public function cafes(): HasMany
    {
        return $this->HasMany(Cafe::class);
    }
    public function services(): MorphToMany
    {
        return $this->morphToMany(Service::class, 'serviceable');
    }
    public function subdealerships(): BelongsToMany
    {
        return $this->belongsToMany(Subdealership::class, 'subdealership_unit');
    }
   public function businesses(): MorphToMany
    {
        return $this->morphToMany(Business::class, 'businessable');
    }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_units', 'unit_id', 'user_id');
    }
}
