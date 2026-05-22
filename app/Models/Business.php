<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Business extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function headquarters(): HasMany
    {
        return $this->hasMany(Headquarter::class);
    }
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
    public function mines(): MorphToMany
    {
        return $this->morphedByMany(Mine::class, 'businessable');
    }
    public function units(): MorphToMany
    {
        return $this->morphedByMany(Unit::class, 'businessable');   
    }
    public function cafes(): MorphToMany
    {
        return $this->morphedByMany(Cafe::class, 'businessable');
    }
}
