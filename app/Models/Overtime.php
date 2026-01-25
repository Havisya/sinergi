<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    use HasFactory;
    protected $table = 'overtimes';
    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'reason',
        'approver',
        'employees',
    ];
    protected $casts = [
        'date' => 'date',
        'employees' => 'array',
    ];
}
