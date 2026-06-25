<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff_financial extends Model
{
    /** @use HasFactory<\Database\Factories\StaffFinancialFactory> */
    use HasFactory;

    protected $fillable = ['staff_id', 'district', 'province', 'department', 'start_date', 'children', 'afp', 'onp', 'position', 'address', 'account_number', 'system_work', 'replacement', 'unit_id', 'salary', 'salary_type', 'observations','pensioncontribution','cci','contract_end_date','bank_entity'];
}
