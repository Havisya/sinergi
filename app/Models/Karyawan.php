<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    // Tentukan nama tabel secara eksplisit
    protected $table = 'karyawan';

    // Tentukan kolom mana yang bisa diisi (mass assignment)
    protected $fillable = [
        'nopek',
        'name',
        'position',
        'status',
        // Kolom Baru
        'category',
        'func',
        'work_system',
        'schedule', // <-- TAMBAH INI
    ];
}
