<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dinner extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'dni', 'phone', 'subdealership_id', 'mine_id'];

    public function subdealership(): BelongsTo
    {
        return $this->belongsTo(Subdealership::class);
    }

    public function mine(): BelongsTo
    {
        return $this->belongsTo(Mine::class);
    }
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
