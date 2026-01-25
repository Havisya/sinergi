<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveQuota extends Model
{
    use HasFactory;

    protected $table = 'leave_quotas';

    // *** PERBAIKAN KRITIS UNTUK firstOrCreate/updateOrCreate ***
    // Menggunakan 'nopek' sebagai kunci utama
    protected $primaryKey = 'nopek';
    // Mencegah Laravel mencoba mengelola kolom 'id' auto-increment
    public $incrementing = false;
    protected $keyType = 'string'; // Kunci utama adalah string (nopek)
    // *********************************************************

    protected $fillable = [
        'nopek',
        'annual_quota',
        'leave_taken',
        'sick_leave_taken',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'nopek', 'nopek');
    }
}
