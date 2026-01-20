<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Mine extends Model
{
    /** @use HasFactory<\Database\Factories\MineFactory> */
    use HasFactory;

    protected $fillable = ['name','dealership_id'];

    public function units(): HasMany
    {

        return $this->HasMany(Unit::class);
    }
    public function services(): MorphToMany
    {
        return $this->morphToMany(Service::class, 'serviceable');
    }
    public function businesses(): MorphToMany
    {
        return $this->morphToMany(Business::class, 'businessable');
    }
    public function dealership()
    {
        return $this->belongsTo(Dealership::class);
    }
    public function subdealerships(): BelongsToMany
    {
        return $this->BelongsToMany(Subdealership::class, 'mine_subdealerships', 'mine_id', 'subdealership_id');
    }
}
