<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KunjunganDinas extends Model
{
    use HasFactory;

    // FIX KRITIS: Pastikan nama tabel ini benar di database
    protected $table = 'kunjungan_dinas';

    // FIX KRITIS: Hanya kolom yang dibuat di migrasi yang diizinkan
    protected $fillable = [
        'nopek',
        'name',
        'position',
        'department',
        'date', // Digunakan di Controller
        'destination',
        'purpose',
        'status',
    ];
}
