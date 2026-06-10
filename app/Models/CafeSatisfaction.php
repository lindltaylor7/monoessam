<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CafeSatisfaction extends Model
{
    use HasFactory;

    protected $fillable = ['cafe_id', 'score', 'date', 'service'];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }
}
