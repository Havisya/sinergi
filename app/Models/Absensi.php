<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absensi extends Model
{
    use HasFactory;

    protected $table = 'absensi';

    // Kolom yang dapat diisi
    protected $fillable = [
        'nip',
        'clock_in_time',
        'clock_out_time',
        'status',
        'device_id',
        'status_koreksi',
        'alasan_koreksi',
    ];

    // Kolom yang harus diubah menjadi instance Carbon (date/time)
    protected $casts = [
        'clock_in_time' => 'datetime',
        'clock_out_time' => 'datetime',
    ];

    /**
     * Relasi: Setiap Absensi dimiliki oleh satu Karyawan.
     */
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'nip', 'nopek');
    }
}
