<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    // ...
    protected $fillable = [
        'company_name',
        'standard_working_hours',
        'late_tolerance_minutes',
        'daily_check_in_limit',
    ];
}
