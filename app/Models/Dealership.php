<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Dealership extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'ruc', 'fiscal_address','legal_address', 'phone', 'email'];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function addendums(): HasMany
    {
        return $this->hasMany(Addendum::class);
    }
    
    public function subdealerships(): HasMany
    {
        return $this->hasMany(Subdealership::class);
    }
    public function mines(): HasMany
    {
        return $this->hasMany(Mine::class);
    }
}
