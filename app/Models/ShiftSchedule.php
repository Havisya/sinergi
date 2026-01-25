<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model {

    // Nama tabel di database
    protected $table = 'shift_schedules';

    // Kolom yang dapat diisi
    protected $fillable = [
        'unique_key',
        'data'
    ];

    // Kolom yang harus di-cast ke tipe data lain (penting untuk menyimpan JSON/array)
    protected $casts = [
        'data' => 'array',
    ];
}
