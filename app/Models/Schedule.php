<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Schedule extends Model
{
    protected $table = 'schedules';

    protected $fillable = [
        'start_date',
        'end_date',
        'shift_type',
        'work_system',
        'supervisor',
        'schedule',
        'schedule_name',
        'employee_nipeks',
        'status',
    ];

    protected $casts = [
        'data' => 'array',
        'employee_nipeks' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Mendapatkan jadwal yang valid untuk seorang karyawan berdasarkan tanggal tertentu.
     */
    public static function getValidScheduleForEmployee($nopek, $date)
    {
        $targetDate = Carbon::parse($date)->toDateString();

        // Prioritas 1: Cari Roster Harian (Quick Shift)
        // (start_date dan end_date sama, dan mengandung nipek)
        $dailyRoster = self::where('start_date', $targetDate)
            ->where('end_date', $targetDate)
            ->whereJsonContains('employee_nipeks', $nopek)
            ->first();

        if ($dailyRoster) {
            return $dailyRoster; // Ditemukan Quick Shift
        }

        // Prioritas 2: Cari Roster Block (Periode)
        // (tanggal berada di antara start_date dan end_date, dan mengandung nipek)
        $periodRoster = self::where('start_date', '<=', $targetDate)
            ->where('end_date', '>=', $targetDate)
            ->whereJsonContains('employee_nipeks', $nopek)
            ->orderBy('created_at', 'desc') // Ambil yang terbaru jika ada tumpang tindih
            ->first();

        if ($periodRoster) {
            return $periodRoster; // Ditemukan Roster Block Periode
        }

        return null; // Tidak ada jadwal ditemukan
    }
}
