<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRequest extends Model
{
    use HasFactory;

    protected $table = 'attendance_requests';

    // Model ini diasumsikan menggunakan kunci utama default (id auto-increment)

    protected $fillable = [
        'nopek',
        'employee_name',
        'date_from',
        'date_to',
        'type',
        'reason',
        'details',
        'status',
        'reviewer_notes',
        'quota_deducted'
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'quota_deducted' => 'boolean',
    ];
}
