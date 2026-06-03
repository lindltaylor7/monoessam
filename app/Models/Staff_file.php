<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff_file extends Model
{
    /** @use HasFactory<\Database\Factories\StaffFileFactory> */
    use HasFactory;

    protected $fillable = ['staff_id', 'file_type', 'file_path', 'expiration_date', 'status'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
