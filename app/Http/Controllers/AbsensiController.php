<?php namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\Schedule;
use App\Models\Overtime;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Traits\LogsActivity;

class AbsensiController extends Controller {

    use LogsActivity;

    // Batas maksimal waktu terlambat sebelum dianggap Tidak Hadir (30 menit)
    const MAX_LATE_FOR_RECORDED_ABSENCE_MINUTES = 240;

    // Toleransi Waktu Tambahan 1 Menit (Untuk kasus 14:00 vs 14:00)
    const TIME_TOLERANCE_MINUTES = 1;

    // Toleransi Pulang Lembur (Misalnya 30 menit setelah jam pulang normal, baru dicap Lembur Otomatis)
    const OVERTIME_GRACE_PERIOD_MINUTES = 30;

    // Batas waktu keterlambatan untuk warna MERAH (30 menit)
    const MAX_LATE_FOR_RED_COLOR_MINUTES = 240;

    // Batas waktu pencarian entri absensi terbuka (48 jam)
    const MAX_OPEN_ABSENCE_SEARCH_HOURS = 48;

    /**
     * DAFTAR JADWAL KERJA (Untuk keandalan parsing)
     */
    private $allSchedules = [
        'Non-Shift 07:30-16:00' => ['in' => '07:30:00', 'out_standard' => '16:00:00'],
        '07:30-16:00' => ['in' => '07:30:00', 'out_standard' => '16:00:00'],
        '08:00-16:00' => ['in' => '08:00:00', 'out_standard' => '16:00:00'],
        '06:00-14:00' => ['in' => '06:00:00', 'out_standard' => '14:00:00'],
        '14:00-22:00' => ['in' => '14:00:00', 'out_standard' => '22:00:00'],
        // Contoh Shift Malam Lintas Hari
        '22:00-06:00' => ['in' => '22:00:00', 'out_standard' => '06:00:00', 'cross_day' => true]
    ];

    // ------------------------------------------------------------------
    // HELPER FUNCTIONS (Logika Penghitungan)
    // ------------------------------------------------------------------
// Fungsi baru untuk React
    public function getFingerprintLogs()
    {
        try {
            // Mengambil log terbaru dari MySQL
            $logs = Absensi::orderBy('event_time', 'desc')->limit(100)->get();

            return response()->json([
                'status' => 'success',
                'data' => $logs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    private function timeToMinutes($carbonTime) {
        if (!$carbonTime instanceof Carbon) return 0;
        return ($carbonTime->hour * 60) + $carbonTime->minute;
    }

    private function getApprovedAttendanceRequest($nip, $date) {
        try {
            return AttendanceRequest::where('nopek', $nip)
                ->where('status', 'Approved')
                ->whereDate('date_from', '<=', $date)
                ->whereDate('date_to', '>=', $date)
                ->first();
        } catch (\Exception $e) {
            Log::error("Error finding approved request for NIP {$nip} on {$date}: " . $e->getMessage());
            return null;
        }
    }

    private function cleanScheduleString($scheduleObject) {
        if (!$scheduleObject || !isset($scheduleObject->schedule)) {
            return (object)['schedule' => 'N/A', 'work_system' => 'Shift'];
        }

        $scheduleString = $scheduleObject->schedule;

        if (str_starts_with($scheduleString, 'Non-Shift ')) {
            $scheduleObject->schedule = str_replace('Non-Shift ', '', $scheduleString);
        }

        return $scheduleObject;
    }

    /**
     * Mencari jadwal yang paling valid untuk seorang karyawan pada tanggal tertentu.
     * LOGIC FIX: Memprioritaskan Approved Overtime (Lembur) di atas Roster.
     */
    private function getRosterSchedule($nip, $date) {
        try {
            // --- Cek 1: Approved Attendance Request (Cuti/Izin) ---
            $approvedRequest = $this->getApprovedAttendanceRequest($nip, $date);
            if ($approvedRequest) {
                // Kembalikan status khusus agar tidak terjadi perhitungan absensi reguler
                return (object)['schedule' => $approvedRequest->type . ' - ' . $approvedRequest->reason, 'work_system' => 'N/A'];
            }

            // --- Cek 2: Approved Overtime (Jika ini hari lembur khusus) ---
            $approvedOvertime = $this->getApprovedOvertime($nip, $date);
            if ($approvedOvertime) {
                // Gunakan jam lembur sebagai target schedule efektif untuk hari ini
                $start = Carbon::parse($approvedOvertime->start_time)->format('H:i');
                $end = Carbon::parse($approvedOvertime->end_time)->format('H:i');
                // Format schedule string unik agar bisa di-parse di getTargetTimes
                return (object)['schedule' => "Lembur {$start}-{$end}", 'work_system' => 'Lembur Khusus'];
            }

            // --- Cek 3: Roster Jadwal Harian ---
            $schedules = Schedule::whereJsonContains('employee_nipeks', $nip)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->get();

            $bestSchedule = null;

            if ($schedules->isEmpty()) {
            } else if ($schedules->count() == 1) {
                $bestSchedule = $schedules->first();
            } else {
                $bestSchedule = $schedules->sortBy(function ($schedule) {
                    return $schedule->start_date->diffInDays($schedule->end_date);
                })->first();
            }

            if ($bestSchedule) {
                return $this->cleanScheduleString($bestSchedule);
            }

            // --- Cek 4: Default Karyawan Non-Shift ---
            $karyawan = Karyawan::where('nopek', $nip)->first();
            if ($karyawan && $karyawan->work_system === 'Non-Shift') {
                return (object)['schedule' => '07:30-16:00', 'work_system' => 'Non-Shift'];
            }

            // --- Fallback: Tidak Ada Jadwal (dianggap N/A) ---
            return (object)['schedule' => 'N/A', 'work_system' => 'Shift'];

        } catch (\Exception $e) {
            Log::error("Error in getRosterSchedule for NIP {$nip} on {$date}: " . $e->getMessage());
            return (object)['schedule' => 'N/A', 'work_system' => 'Shift'];
        }
    }

    private function getApprovedOvertime($nip, $date) {
        return Overtime::where('date', $date)
            ->whereJsonContains('employees', ['id' => $nip])
            ->first();
    }

private function getTargetTimes($scheduleObject) {
    $scheduleString = $scheduleObject->schedule ?? 'N/A';
    $isCrossDay = false;

    // 1. PRIORITAS UTAMA: Cek apakah ada format jam manual (cth: 08:00-16:00 atau Libur 08:00-12:00)
    try {
        // Bersihkan karakter aneh
        $cleanStr = str_replace(['–', '—', '−'], '-', $scheduleString);

        if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $cleanStr, $matches)) {
            $inTime = trim($matches[1]) . ':00';
            $outTime = trim($matches[2]) . ':00';
            $isCrossDay = Carbon::parse($inTime)->greaterThan(Carbon::parse($outTime)) || $outTime === '00:00:00';

            return [
                'in' => $inTime,
                'out_standard' => $outTime,
                'cross_day' => $isCrossDay,
                'error' => false
            ];
        }
    } catch (\Exception $e) {
        Log::error("Parsing Jam Gagal: " . $e->getMessage());
    }

    // 2. PRIORITAS KEDUA: Jika murni kata "Libur" tanpa angka jam
    if (str_contains($scheduleString, 'Libur')) {
        return [
            'in' => '00:00:00',
            'out_standard' => '23:59:00',
            'cross_day' => false,
            'error' => false
        ];
    }

    // 3. PRIORITAS KETIGA: Preset Schedules
    foreach ($this->allSchedules as $name => $times) {
        if (str_contains($scheduleString, $name)) {
            return array_merge($times, ['cross_day' => $times['cross_day'] ?? false, 'error' => false]);
        }
    }

    // 4. FALLBACK
    return ['in' => '00:00:00', 'out_standard' => '00:00:00', 'cross_day' => false, 'error' => true];
}
private function getCombinedStatus(array $statuses) {
    $rawStatusIn = $statuses['status_in'];
    $rawStatusOut = $statuses['status_out'];

    // 1. Bersihkan string status dari detail menit (contoh: "Terlambat (10m)" menjadi "Terlambat")
    $statusIn = trim(str_contains($rawStatusIn, '(') ? substr($rawStatusIn, 0, strpos($rawStatusIn, '(')) : $rawStatusIn);
    $statusOut = trim(str_contains($rawStatusOut, '(') ? substr($rawStatusOut, 0, strpos($rawStatusOut, '(')) : $rawStatusOut);

    // 2. PRIORITAS UTAMA: Jika status masuk adalah 'Tidak Hadir' (karena terlambat > 30 menit)
    // Sesuai permintaan, ini akan mengabaikan apa pun status pulangnya.
    if ($statusIn === 'Tidak Hadir') {
        return 'Tidak Hadir';
    }

    // 3. Penanganan status Libur
    if ($statusIn === 'Libur') {
        return 'Libur (Roster)';
    }

    // 4. Penanganan status Dinas (Biasanya dari alat/device khusus)
    if ($statusIn === 'Dinas' || $statusOut === 'Dinas') {
        return 'Dinas | Tepat Waktu';
    }

    // 5. Penanganan Error atau Jadwal Belum Diatur
    if ($statusIn === 'ERROR' || $statusOut === 'Jadwal Salah') {
        if ($rawStatusOut === 'Jadwal Salah' && $rawStatusIn === 'ERROR') {
            return 'Jadwal Belum Diatur';
        }
        return 'Terlambat | Jadwal Bermasalah';
    }

    // 6. Jika belum melakukan absen pulang
    if ($statusOut === 'Belum Absen Pulang' || $statusOut === 'Tidak Ada Absen Pulang' || $statusOut === 'Belum Clock Out') {
        // Jika masuk tepat waktu tapi belum pulang
        if ($statusIn === 'Tepat Waktu') {
            return 'Tepat Waktu | Belum Keluar';
        }
        // Jika terlambat (di bawah 30 menit) dan belum pulang
        return $rawStatusIn . ' | Belum Keluar';
    }

    // 7. Jika Masuk dan Pulang Tepat Waktu
    if ($statusIn === 'Tepat Waktu' && $statusOut === 'Tepat Waktu') {
        return 'Tepat Waktu';
    }

    // 8. Kombinasi umum lainnya (Terlambat, Pulang Cepat, Lembur)
    $displayStatusIn = $rawStatusIn;
    $displayStatusOut = $statusOut;

    // Jika ada lembur, gunakan teks yang lebih bersih
    if (str_contains($rawStatusOut, 'Lembur')) {
        $displayStatusOut = 'Lembur';
    }

    return $displayStatusIn . ' | ' . $displayStatusOut;
}private function calculateAttendanceStatus(Absensi $absensi, $targetTimes, $overtimeRecord) {
    // 1. Validasi awal jika jadwal salah atau tidak ditemukan
    if ($targetTimes['error'] ?? false) {
        return ['status_in' => 'ERROR', 'status_out' => 'Jadwal Salah', 'overtime_status' => 'Normal'];
    }

    // 2. Cek jika statusnya adalah hari Libur
    if ($targetTimes['in'] === '00:00:00' && $targetTimes['out_standard'] === '23:59:00') {
        return ['status_in' => 'Libur', 'status_out' => 'Libur', 'overtime_status' => 'Normal'];
    }

    // 3. Jika data Fingerprint/Absensi tidak memiliki clock_in
    if (!$absensi->clock_in_time) {
        return ['status_in' => 'Tidak Hadir', 'status_out' => 'N/A', 'overtime_status' => 'Normal'];
    }

    // --- LOGIKA CLOCK IN (MASUK) ---
    $clockInTime = $absensi->clock_in_time;
    $targetIn = Carbon::parse($clockInTime->toDateString() . ' ' . $targetTimes['in']);

    $targetInMinutes = $this->timeToMinutes($targetIn);
    $clockInMinutes = $this->timeToMinutes($clockInTime);

    // Batas Toleransi (misal 1 menit untuk handle detik)
    $onTimeMaxMinutes = $targetInMinutes + self::TIME_TOLERANCE_MINUTES;

    // Batas Maksimal Terlambat (30 menit) sebelum dianggap Tidak Hadir
    $lateLimitMinutes = $targetInMinutes + self::MAX_LATE_FOR_RECORDED_ABSENCE_MINUTES;

    if ($clockInMinutes <= $onTimeMaxMinutes) {
        $statusClockIn = 'Tepat Waktu';
    } elseif ($clockInMinutes > $lateLimitMinutes) {
        // Logika Sinkronisasi: Jika > 30 menit, status menjadi Tidak Hadir (Sama dengan abu-abu di Roster)
        $statusClockIn = 'Tidak Hadir';
    } else {
        // Jika terlambat tapi masih di bawah atau sama dengan 30 menit
        $minutesLate = $clockInMinutes - $targetInMinutes;
        $statusClockIn = 'Terlambat (' . $minutesLate . 'm)';
    }

    // --- LOGIKA CLOCK OUT (PULANG) ---
    $statusClockOut = 'N/A';
    $overtimeStatus = 'Normal';
    $clockOutTime = $absensi->clock_out_time;
    $targetOutTime = Carbon::parse($clockInTime->toDateString() . ' ' . $targetTimes['out_standard']);

    if (!$clockOutTime) {
        $statusClockOut = 'Belum Absen Pulang';
    } else {
        $targetOut = $targetOutTime;
        $actualOutTime = $clockOutTime->copy();

        // Penanganan Shift Malam (Cross Day)
        if ($targetTimes['cross_day']) {
            $targetOut = $targetOutTime->copy()->addDay();
            // Jika jam absen pulang lebih kecil dari jam masuk, berarti dia pulang di hari berikutnya
            if ($actualOutTime->lessThan($clockInTime)) {
                $actualOutTime->addDay();
            }
        }

        // Penentuan Lembur atau Pulang Cepat
        $overtimeStartLimit = $targetOut->copy()->addMinutes(self::OVERTIME_GRACE_PERIOD_MINUTES);

        if ($actualOutTime->greaterThan($overtimeStartLimit)) {
            // Jika ada data lembur yang disetujui di tabel Overtime
            $statusClockOut = $overtimeRecord ? 'Lembur Terjadwal' : 'Lembur Otomatis';
            $overtimeStatus = $statusClockOut;
        } elseif ($actualOutTime->lessThan($targetOut)) {
            $statusClockOut = 'Pulang Cepat';
        } else {
            $statusClockOut = 'Tepat Waktu';
        }
    }

    return [
        'status_in' => $statusClockIn,
        'status_out' => $statusClockOut,
        'overtime_status' => $overtimeStatus
    ];
}
    private function getDutyRosterStatus($nip, $date) {
        try {
            $today = Carbon::parse($date)->toDateString();
            // Pengecekan Cuti/Izin sudah ada di getRosterSchedule
            $karyawan = Karyawan::where('nopek', $nip)->first();
            if (!$karyawan) {
                return 'none';
            }

            $scheduleObject = $this->getRosterSchedule($nip, $today);
            $targetTimes = $this->getTargetTimes($scheduleObject);

            // Jika jadwal yang ditemukan adalah Cuti/Izin (scheduleObject->work_system == 'N/A')
            if ($scheduleObject->work_system === 'N/A') {
                 return 'green';
            }


            if ($targetTimes['error'] || ($targetTimes['in'] === '00:00:00' && $targetTimes['out_standard'] === '23:59:00')) {
                return 'none'; // 'none' berarti tidak ada jadwal (Libur)
            }
            $absensi = Absensi::where('nip', $nip)
                ->whereDate('clock_in_time', $today)
                ->first();

            $targetIn = Carbon::parse($today . ' ' . $targetTimes['in']);
            $now = Carbon::now();
            $targetLateLimit = $targetIn->copy()->addMinutes(self::MAX_LATE_FOR_RED_COLOR_MINUTES);

            if (!$absensi || !$absensi->clock_in_time) {
                if ($now->greaterThan($targetLateLimit)) {
                    return 'gray';
                }
                return 'none';
            }

            $clockInTime = $absensi->clock_in_time;
            if (!$clockInTime instanceof Carbon) {
                 return 'none'; // Tambahan keamanan
            }

            if ($clockInTime->lessThanOrEqualTo($targetIn->copy()->addMinutes(self::TIME_TOLERANCE_MINUTES))) {
                return 'green';
            }

            if ($clockInTime->greaterThan($targetIn) && $clockInTime->lessThanOrEqualTo($targetLateLimit)) {
                return 'red';
            }

            if ($clockInTime->greaterThan($targetLateLimit)) {
                return 'gray';
            }

            return 'none';
        } catch (\Exception $e) {
            Log::error("Error in getDutyRosterStatus for NIP {$nip} on {$date}: " . $e->getMessage());
            return 'none';
        }
    }

    // ------------------------------------------------------------------
    // ENDPOINT UNTUK FRONTEND (ROSTER STATUS)
    // ------------------------------------------------------------------

    public function getRosterStatuses(Request $request) {
        $request->validate([
            'nipeks' => 'required|array',
            'date' => 'required|date_format:Y-m-d',
        ]);

        $statuses = [];
        $today = $request->date;

        foreach ($request->nipeks as $nip) {
            $statuses[$nip] = $this->getDutyRosterStatus($nip, $today);
        }

        return response()->json($statuses);
    }

    // ------------------------------------------------------------------
    // HELPER UNTUK MENGAMBIL DATA DETAIL (Basis untuk semua Laporan)
    // ------------------------------------------------------------------

    /**
     * Helper: Mengambil data karyawan yang sudah difilter.
     */
    private function getFilteredEmployees(Request $request)
    {
        $search = $request->query('search');
        $kategoriFilter = $request->query('kategori');
        $positionFilter = $request->query('position');
        $workSystemFilter = $request->query('work_system');

        $employeeQuery = Karyawan::select('id', 'nopek', 'name', 'position', 'work_system', 'category');

        if ($search) {
            $employeeQuery->where(function($q) use ($search) {
                $q->where('nopek', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($kategoriFilter && $kategoriFilter !== 'Semua') {
            $employeeQuery->where('category', $kategoriFilter);
        }
        if ($positionFilter && $positionFilter !== 'Semua') {
            $employeeQuery->where('position', $positionFilter);
        }
        if ($workSystemFilter && $workSystemFilter !== 'Semua') {
            $employeeQuery->where('work_system', $workSystemFilter);
        }

        return $employeeQuery->get();
    }

/**
     * Helper: Mengambil data absensi detail yang sudah diformat dan difilter.
     * @param Request $request
     * @param bool $ignoreStatusFilter Jika TRUE, filter 'status' diabaikan (digunakan untuk Matriks Harian).
     * @return Collection
     */
/**
 * Helper: Mengambil data absensi detail yang sudah diformat dan difilter.
 * FIX: Menghapus filter 'continue' agar data fingerprint tetap tampil meski statusnya 'Tidak Hadir'.
 */
/**
 * Helper: Mengambil data absensi detail yang sudah diformat dan difilter.
 * Perbaikan: Menangani tapping H-1 untuk shift malam (00:00) agar terhitung Tepat Waktu.
 */
private function getFilteredAttendanceData(Request $request, $ignoreStatusFilter = false) {
        try {
            $statusFilter = $request->query('status');
            $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : Carbon::now()->subDays(30);
            $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : Carbon::now()->addDays(30);

            $filteredEmployees = $this->getFilteredEmployees($request);
            $filteredNips = $filteredEmployees->pluck('nopek')->toArray();
            $allEmployees = $filteredEmployees->keyBy('nopek');

            // Tarik data dari H-1 untuk shift malam
            $extendedStart = $startDate->copy()->subDay()->setTime(20, 0, 0);

            $absensi = Absensi::whereIn('nip', $filteredNips)
                ->where('clock_in_time', '>=', $extendedStart)
                ->where('clock_in_time', '<=', $endDate->copy()->endOfDay())
                ->get();

            $attendanceData = [];
            $processedKeys = [];

            foreach ($absensi as $record) {
                $karyawan = $allEmployees->get($record->nip);
                if (!$karyawan) continue;

                $clockIn = Carbon::parse($record->clock_in_time);

                // Tentukan Tanggal Jadwal (Logic Shift Malam)
                $scheduleDate = $clockIn->toDateString();
                if ($clockIn->hour >= 21) {
                    $scheduleDate = $clockIn->copy()->addDay()->toDateString();
                }

                // Mencegah Tapping Berulang (Hanya ambil 1 data per NIP per Hari Jadwal)
                $uniqueKey = $record->nip . '-' . $scheduleDate;
                if (isset($processedKeys[$uniqueKey])) continue;

                $scheduleObject = $this->getRosterSchedule($record->nip, $scheduleDate);
                $target = $this->getTargetTimes($scheduleObject);
                $overtimeRecord = $this->getApprovedOvertime($record->nip, $scheduleDate);

                $attendanceStatuses = $this->calculateAttendanceStatus($record, $target, $overtimeRecord);

                // Fix Status Tepat Waktu shift 00:00
                if ($target['in'] === '00:00:00' && $clockIn->hour >= 23) {
                    $attendanceStatuses['status_in'] = 'Tepat Waktu';
                }

                $finalDisplayStatus = $this->getCombinedStatus($attendanceStatuses);

                $attendanceData[] = [
                    'id' => $record->id,
                    'nopek' => $record->nip,
                    'name' => $karyawan->name,
                    'jabatan' => $karyawan->position,
                    'work_system' => $karyawan->work_system,
                    'date' => $scheduleDate,
                    'full_timestamp' => $clockIn->toDateTimeString(),
                    'time_in' => $clockIn->format('H:i'),
                    'time_out' => $record->clock_out_time ? Carbon::parse($record->clock_out_time)->format('H:i') : '-:-',
                    'schedule' => $scheduleObject->schedule ?? 'N/A',
                    'status' => $finalDisplayStatus,
                    'status_in' => $attendanceStatuses['status_in'],
                    'status_out' => $attendanceStatuses['status_out'],
                    'source' => 'ABSENSI'
                ];

                $processedKeys[$uniqueKey] = true;
            }

            // Tambahkan Data Izin/Cuti
            $requests = AttendanceRequest::whereIn('nopek', $filteredNips)
                ->where('status', 'Approved')
                ->whereDate('date_from', '<=', $endDate)
                ->whereDate('date_to', '>=', $startDate)
                ->get();

            foreach ($requests as $req) {
                $period = CarbonPeriod::create(Carbon::parse($req->date_from)->max($startDate), Carbon::parse($req->date_to)->min($endDate));
                foreach ($period as $date) {
                    $dateStr = $date->toDateString();
                    if (!isset($processedKeys[$req->nopek . '-' . $dateStr])) {
                        $karyawan = $allEmployees->get($req->nopek);
                        if (!$karyawan) continue;
                        $attendanceData[] = [
                            'id' => 'req-' . $req->id . '-' . $dateStr,
                            'nopek' => $req->nopek,
                            'name' => $karyawan->name,
                            'jabatan' => $karyawan->position,
                            'work_system' => $karyawan->work_system,
                            'date' => $dateStr,
                            'full_timestamp' => $dateStr . ' 00:00:00',
                            'time_in' => '-',
                            'time_out' => '-',
                            'schedule' => '-',
                            'status' => $req->type . ' (' . $req->reason . ')',
                            'source' => 'REQUEST'
                        ];
                        $processedKeys[$req->nopek . '-' . $dateStr] = true;
                    }
                }
            }

            $finalCollection = collect($attendanceData)->sortByDesc('full_timestamp');

            if ($ignoreStatusFilter || !$statusFilter || $statusFilter === 'Semua') {
                return $finalCollection->values();
            }

            return $finalCollection->filter(fn($r) => str_contains(strtolower($r['status']), strtolower($statusFilter)))->values();

        } catch (\Exception $e) {
            Log::error("Error filtering attendance: " . $e->getMessage());
            return collect([]);
        }
    }/**
     * Endpoint: GET /api/absensi (Data Detail Per Hari)
     */
    public function index(Request $request)
    {
        try {
            // Memanggil helper yang sudah diperbaiki filternya
            $filteredData = $this->getFilteredAttendanceData($request);
            return response()->json($filteredData);

        } catch (\Exception $e) {
            Log::error("CRITICAL ERROR in index(): " . $e->getMessage() . " on line " . $e->getLine());
            return response()->json(['message' => 'Gagal memuat data absensi. Error kritis di server.'], 500);
        }
    }

    // ------------------------------------------------------------------
    // Endpoint: GET /api/absensi/reports/recap (Laporan Rekapitulasi)
    // ------------------------------------------------------------------
    public function recapReport(Request $request)
    {
        // Memanggil helper yang sudah diperbaiki filternya (Tidak Hadir otomatis hilang di default view)
        $dataDetail = $this->getFilteredAttendanceData($request);

        $recapMap = [];
        $filteredEmployees = $this->getFilteredEmployees($request);
        $employeeNips = $filteredEmployees->pluck('nopek')->toArray();
        $allEmployees = $filteredEmployees->keyBy('nopek');

        foreach($employeeNips as $nip) {
             $karyawan = $allEmployees->get($nip);
             if (!$karyawan) continue;

             $recapMap[$nip] = [
                 'nopek' => $nip,
                 'name' => $karyawan->name,
                 'position' => $karyawan->position,
                 'work_system' => $karyawan->work_system,
                 'total_hadir' => 0,
                 'total_terlambat' => 0,
                 'total_pulang_cepat' => 0,
                 'total_alfa' => 0,
                 'total_cuti' => 0,
                 'total_izin' => 0,
                 'total_lembur' => 0,
                 'total_hari_kerja' => 0,
             ];
        }

foreach ($dataDetail as $record) {
    $nip = $record['nopek'];
    if (!isset($recapMap[$nip])) continue;

    $recap = &$recapMap[$nip];
    $status = strtolower($record['status'] ?? '');
    $isOvertime = str_contains(strtolower($record['overtime_status'] ?? ''), 'lembur');

    $isLibur = str_contains($status, 'libur');
    $isAlfa  = str_contains($status, 'tidak hadir');
    $isCuti  = str_contains($status, 'cuti');
    $isIzin  = str_contains($status, 'izin');
    $isDinas = str_contains($status, 'dinas');

    /** -------------------------------
     *  LIBUR → Tidak dihitung hari kerja
     * -------------------------------- */
    if ($isLibur) {
        continue;
    }

    /** -------------------------------
     *  HITUNG HARI KERJA
     *  (Hadir, Cuti, Izin, Dinas, Alfa)
     * -------------------------------- */
    $recap['total_hari_kerja'] += 1;

    /** -------------------------------
     *  ALFA
     * -------------------------------- */
    if ($isAlfa) {
        $recap['total_alfa'] += 1;
    }

    /** -------------------------------
     *  CUTI
     * -------------------------------- */
    elseif ($isCuti) {
        $recap['total_cuti'] += 1;
    }

    /** -------------------------------
     *  DINAS
     * -------------------------------- */
    elseif ($isDinas) {
        $recap['total_izin'] += 1;   // dinas = izin resmi
        $recap['total_hadir'] += 1;  // dianggap hadir
    }

    /** -------------------------------
     *  IZIN
     * -------------------------------- */
    elseif ($isIzin) {
        $recap['total_izin'] += 1;
    }

    /** -------------------------------
     *  HADIR NORMAL
     * -------------------------------- */
    else {
        if (str_contains($status, 'terlambat')) {
            $recap['total_terlambat'] += 1;
            $recap['total_hadir'] += 1;
        } elseif (str_contains($status, 'pulang cepat')) {
            $recap['total_pulang_cepat'] += 1;
            $recap['total_hadir'] += 1;
        } elseif (str_contains($status, 'tepat waktu')) {
            $recap['total_hadir'] += 1;
        }
    }

    /** -------------------------------
     *  LEMBUR
     * -------------------------------- */
    if ($isOvertime) {
        $recap['total_lembur'] += 1;
    }

            $recapMap[$nip] = $recap;
        }
        unset($recap);

$finalRecapData = collect($recapMap)
    ->filter(fn($recap) =>
        $recap['total_hadir'] > 0 ||
        $recap['total_cuti'] > 0 ||
        $recap['total_izin'] > 0 ||
        $recap['total_alfa'] > 0
    )
    ->values();

        return response()->json($finalRecapData);
    }

    /**
     * Endpoint: GET /api/absensi/reports/daily-matrix (Laporan Matriks Harian)
     */
    public function dailyMatrixReport(Request $request)
    {
        // 1. Ambil Karyawan yang difilter (Filter NIP/Kategori/Posisi)
        $filteredEmployees = $this->getFilteredEmployees($request);
        $employeeNips = $filteredEmployees->pluck('nopek')->toArray();
        $allEmployees = $filteredEmployees->keyBy('nopek');

        // Ambil filter status yang asli dari request
        $statusFilter = $request->query('status');

        // 2. Ambil SEMUA data kehadiran untuk NIP yang difilter, ABAIKAN filter status di helper (TRUE)
        // Kita mendapatkan semua data di sini (Libur dan Alfa)
        $dataDetail = $this->getFilteredAttendanceData($request, true);

        // --- FILTER ALFA UNTUK TAMPILAN DEFAULT MATRIKS (JIKA TIDAK ADA FILTER SPESIFIK) ---
        // Jika Matriks dipanggil TANPA filter status spesifik, buang data "Tidak Hadir" (Alfa)
        if (!$statusFilter || $statusFilter === 'Semua') {
            $dataDetail = $dataDetail->filter(function ($record) {
                $status = strtolower($record['status']);
                return !str_contains($status, 'tidak hadir');
            })->values();
        }
        // --- END FILTER ALFA ---

        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : Carbon::now()->subDays(30);
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : Carbon::now()->addDays(30);

        $matrixData = [];

        $period = CarbonPeriod::create($startDate, $endDate);
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->toDateString();
        }

        // --- INISIALISASI Matriks ---
        if (!empty($employeeNips)) {
            foreach ($employeeNips as $nip) {
                $karyawan = $allEmployees->get($nip);
                if (!$karyawan) {
                    continue;
                }

                $initialDays = [];
                foreach ($dates as $date) {
                    $dateString = is_string($date) ? $date : $date->toDateString();

                    $scheduleObject = $this->getRosterSchedule($nip, $dateString);
                    $scheduleString = $scheduleObject->schedule ?? 'N/A';

                    $initialStatus = 'N/A';
                    $initialIn = '-';
                    $initialOut = '-';

                    if (str_contains($scheduleString, 'Libur')) {
                        $initialStatus = 'Libur (Roster)';
                        $initialIn = 'L';
                        $initialOut = 'L';
                    }

                    $initialDays[$dateString] = [
                        'in' => $initialIn,
                        'out' => $initialOut,
                        'status' => $initialStatus,
                        'raw_status_in' => $initialIn,
                        'raw_status_out' => $initialOut,
                    ];
                }

                $matrixData[$nip] = [
                    'nopek' => $nip,
                    'name' => $karyawan->name ?? '-',
                    'position' => $karyawan->position ?? '-',
                    'work_system' => $karyawan->work_system ?? '-',
                    'days' => $initialDays,
                    'has_filtered_status' => false, // Flag untuk filter baris
                ];
            }
        }

        // --- PENGISIAN DATA KE MATRIKS DAN PENANDAAN FILTER BARIS ---
        if (!empty($dataDetail)) {
            foreach ($dataDetail as $record) {

                if (empty($record['nopek']) || empty($record['date']) || !isset($matrixData[$record['nopek']])) {
                    continue;
                }

                $nip = $record['nopek'];
                $date = $record['date'];

                if (!isset($matrixData[$nip]['days'][$date])) {
                    continue;
                }

                // --- PENGISIAN NILAI HARI ---
                $status = $record['status'] ?? 'N/A';
                $lowerStatus = strtolower($status);
                $initial = '-';
                $statusType = 'N/A';
                $isAbsenceCode = false;

                if (str_contains($lowerStatus, 'libur')) {
                    $initial = 'L'; $statusType = 'Libur'; $isAbsenceCode = true;
                } elseif (str_contains($lowerStatus, 'cuti')) {
                    $initial = 'C'; $statusType = 'Cuti'; $isAbsenceCode = true;
                } elseif (str_contains($lowerStatus, 'dinas')) {
                    // PERBAIKAN 2: Jika status adalah Dinas
                    $initial = 'D'; $statusType = 'Dinas'; $isAbsenceCode = true;
                } elseif (str_contains($lowerStatus, 'izin')) {
                    $initial = 'I'; $statusType = 'Izin'; $isAbsenceCode = true;
                } elseif (str_contains($lowerStatus, 'tidak hadir')) {
                    // Catatan: Karena kita sudah memfilter Alfa di atas (jika default view),
                    // ini hanya akan dijalankan jika filter status eksplisit adalah 'Tidak Hadir'
                    $initial = 'A'; $statusType = 'Alfa (Tdk Hadir)'; $isAbsenceCode = true;
                }

                if ($isAbsenceCode) {
                    $matrixData[$nip]['days'][$date] = [
                        'in' => $initial,
                        'out' => $initial,
                        'status' => $statusType,
                        'raw_status_in' => $status,
                        'raw_status_out' => $status,
                    ];
                } else {
                    $matrixData[$nip]['days'][$date] = [
                        'in' => $record['time_in'] ?? '-',
                        'out' => $record['time_out'] ?? '-',
                        'status' => $status,
                        'raw_status_in' => $record['status_in'] ?? $status,
                        'raw_status_out' => $record['status_out'] ?? $status,
                    ];
                }

                // --- PENGECEKAN KESESUAIAN DENGAN FILTER STATUS (untuk filter baris akhir) ---
                if ($statusFilter && $statusFilter !== 'Semua') {
                    $filterLower = strtolower($statusFilter);
                    $recordStatusLower = strtolower($record['status']);
                    $recordOvertimeLower = strtolower($record['overtime_status']);
                    $isMatch = false;

                    if (str_contains($filterLower, 'lembur') && str_contains($recordOvertimeLower, 'lembur')) $isMatch = true;
                    elseif (str_contains($filterLower, 'libur') && str_contains($recordStatusLower, 'libur')) $isMatch = true;
                    // Logika yang diubah agar data "Tidak Hadir" (Alfa) juga ikut terfilter jika statusnya dicari
                    elseif ((str_contains($filterLower, 'tidak hadir') || str_contains($filterLower, 'alfa')) && str_contains($recordStatusLower, 'tidak hadir')) $isMatch = true;
                    elseif (str_contains($filterLower, 'cuti') && str_contains($recordStatusLower, 'cuti')) $isMatch = true;
                    elseif (str_contains($filterLower, 'izin') && (str_contains($recordStatusLower, 'izin') || str_contains($recordStatusLower, 'dinas'))) $isMatch = true;
                    elseif (str_contains($filterLower, 'dinas') && str_contains($recordStatusLower, 'dinas')) $isMatch = true; // Tambahkan filter dinas
                    elseif (str_contains($filterLower, 'terlambat') && str_contains($recordStatusLower, 'terlambat')) $isMatch = true;
                    elseif (str_contains($filterLower, 'pulang cepat')) $isMatch = true;
                    elseif (str_contains($filterLower, 'waktu') && str_contains($recordStatusLower, 'tepat waktu')) $isMatch = true;
                    elseif (str_contains($recordStatusLower, $filterLower)) $isMatch = true;

                    if ($isMatch) {
                        $matrixData[$nip]['has_filtered_status'] = true;
                    }
                }
            }
        }


        // --- FINALISASI DAN FILTER BARIS AKHIR ---
        $finalMatrix = collect($matrixData);

        // Jika filter status AKTIF, hanya tampilkan baris yang memiliki setidaknya 1 status yang difilter
        if ($statusFilter && $statusFilter !== 'Semua') {
            $finalMatrix = $finalMatrix->filter(fn($record) => $record['has_filtered_status'])->values();
        } else {
            // Jika filter default, tidak ada filter baris tambahan yang diperlukan,
            // karena filter data harian (Alfa) sudah diterapkan di atas.
            $finalMatrix = $finalMatrix->values();
        }


        return response()->json([
            'dates' => $dates,
            'report' => $finalMatrix
        ]);
    }


    /**
     * Endpoint: GET /api/absensi/reports/export-excel (REKAPITULASI)
     * PERBAIKAN: Menggunakan Delimiter Semikolon (;) untuk kompatibilitas Excel yang lebih baik.
     */
    public function exportExcel(Request $request)
    {
        $recapData = collect($this->recapReport($request)->original);

        if ($recapData->isEmpty()) {
            return response()->json(['message' => 'Tidak ada data untuk diekspor.'], 404);
        }

        $headers = [
            'NOPEK', 'NAMA', 'JABATAN/POSISI', 'SISTEM KERJA',
            'HARI KERJA', 'HADIR', 'TERLAMBAT', 'PULANG CEPAT',
            'ALFA (Tidak Hadir)', 'CUTI', 'IZIN', 'LEMBUR'
        ];

        $delimiter = ';'; // Delimiter yang lebih kompatibel dengan Excel di Indonesia

        $callback = function() use ($recapData, $headers, $delimiter) {
            $file = fopen('php://output', 'w');

            // Tambahkan BOM untuk dukungan UTF-8 (mencegah karakter aneh)
            fputs($file, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

            // Tulis Header
            fputcsv($file, $headers, $delimiter);

            foreach ($recapData as $rec) {
                fputcsv($file, [
                    $rec['nopek'],
                    $rec['name'],
                    $rec['position'],
                    $rec['work_system'],
                    (string)$rec['total_hari_kerja'],
                    (string)$rec['total_hadir'],
                    (string)$rec['total_terlambat'],
                    (string)$rec['total_pulang_cepat'],
                    (string)$rec['total_alfa'],
                    (string)$rec['total_cuti'],
                    (string)$rec['total_izin'],
                    (string)$rec['total_lembur'],
                ], $delimiter); // Gunakan delimiter yang ditetapkan
            }
            fclose($file);
        };

        $startDate = $request->query('start_date') ?? 'Awal';
        $endDate = $request->query('end_date') ?? 'Akhir';
        $fileName = "Rekap_Absensi_{$startDate}_sd_{$endDate}.csv";

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    /**
     * Endpoint: GET /api/absensi/reports/export-daily-matrix (MATRIKS HARIAN)
     * PERBAIKAN: Menggunakan Delimiter Semikolon (;) untuk kompatibilitas Excel yang lebih baik.
     */
    public function exportDailyMatrix(Request $request)

    {
        $matrixDataResponse = $this->dailyMatrixReport($request)->original;

        if (empty($matrixDataResponse['report'])) {
            return response()->json(['message' => 'Tidak ada data matriks harian untuk diekspor.'], 404);
        }

        $dates = $matrixDataResponse['dates'];
        $report = $matrixDataResponse['report'];

        $headers = ['NOPEK', 'NAMA', 'POSISI', 'SISTEM_KERJA'];
        $subHeaders = ['', '', '', ''];

        foreach ($dates as $dateString) {
            $dayOfMonth = Carbon::parse($dateString)->day;

            $headers[] = $dayOfMonth;
            $headers[] = '';

            $subHeaders[] = 'IN';
            $subHeaders[] = 'OUT';
        }

        $delimiter = ';'; // Delimiter yang lebih kompatibel dengan Excel di Indonesia

        $callback = function() use ($report, $headers, $subHeaders, $delimiter) {
            $file = fopen('php://output', 'w');

            // Tambahkan BOM untuk dukungan UTF-8 (mencegah karakter aneh)
            fputs($file, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

            // Tulis Header Utama
            fputcsv($file, $headers, $delimiter);

            // Tulis Sub Header
            fputcsv($file, $subHeaders, $delimiter);

            foreach ($report as $emp) {
                $row = [
                    $emp['nopek'],
                    $emp['name'],
                    $emp['position'],
                    $emp['work_system'],
                ];

                foreach ($emp['days'] as $dayData) {
                    // Masukkan data IN dan OUT
                    // Perbaikan: Tidak perlu cek in_array di sini, cukup masukkan datanya
                    $row[] = $dayData['in'];
                    $row[] = $dayData['out'];
                }
                fputcsv($file, $row, $delimiter); // Gunakan delimiter yang ditetapkan
            }
            fclose($file);
        };

        $startDate = $request->query('start_date') ?? 'Awal';
        $endDate = $request->query('end_date') ?? 'Akhir';
        $fileName = "Daily_Matrix_Absensi_{$startDate}_sd_{$endDate}.csv";

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);

    }
    // ------------------------------------------------------------------
    // ENDPOINTS TRANSAKSIONAL (Clock In/Out, Koreksi)
    // ------------------------------------------------------------------

public function clockIn(Request $request)
    {
        $request->validate([
            'nip' => 'required|string|exists:karyawan,nopek',
            'absent_date' => 'required|date',
            'absent_time' => 'required|date_format:H:i:s',
        ]);

        $fullTimestamp = $request->absent_date . ' ' . $request->absent_time;
        $clockTime = Carbon::parse($fullTimestamp);

        // Logika penentuan tanggal jadwal (Shift Malam)
        $scheduleDate = $clockTime->toDateString();
        if ($clockTime->hour >= 21) {
            $scheduleDate = $clockTime->copy()->addDay()->toDateString();
        }

        try {
            $karyawan = Karyawan::where('nopek', $request->nip)->firstOrFail();

            // 1. CEK BATAS ABSENSI (Hanya boleh 1x In dan 1x Out per hari jadwal)
            $existingAbsensi = Absensi::where('nip', $request->nip)
                ->where(function($q) use ($scheduleDate) {
                    // Cek berdasarkan status gabungan atau mencocokkan logic scheduleDate
                    $q->whereDate('clock_in_time', $scheduleDate)
                      ->orWhere(DB::raw('DATE(ADDTIME(clock_in_time, "03:00:00"))'), $scheduleDate);
                })->first();

            if ($existingAbsensi) {
                // Jika sudah ada Clock In tapi BELUM Clock Out -> Lari ke fungsi clockOut
                if (is_null($existingAbsensi->clock_out_time)) {
                    return $this->clockOut($request);
                }
                // Jika SUDAH Clock In DAN SUDAH Clock Out -> TOLAK (Batas 2x terpenuhi)
                return response()->json(['message' => 'Anda sudah melakukan absensi (Masuk & Pulang) untuk jadwal hari ini.'], 403);
            }

            // 2. Cek Izin/Cuti
            $approvedRequest = $this->getApprovedAttendanceRequest($request->nip, $scheduleDate);
            if ($approvedRequest) {
                return response()->json(['message' => "Blokir: Anda sedang dalam masa {$approvedRequest->type}."], 403);
            }

            // 3. Proses Simpan Clock In
            $absensi = new Absensi(['nip' => $request->nip, 'clock_in_time' => $clockTime, 'device_id' => $request->device_id]);
            $schedule = $this->getRosterSchedule($request->nip, $scheduleDate);
            $target = $this->getTargetTimes($schedule);

            $statuses = $this->calculateAttendanceStatus($absensi, $target, null);

            // Override Tepat Waktu untuk shift 00:00 yang tapping jam 23:xx
            if ($target['in'] === '00:00:00' && $clockTime->hour >= 23) {
                $statuses['status_in'] = 'Tepat Waktu';
            }

            $absensi->status = $this->getCombinedStatus($statuses);
            $absensi->save();

            return response()->json(['message' => 'Clock-in berhasil!', 'data' => $absensi], 201);

        } catch (\Exception $e) {
            Log::error("Error clock-in: " . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function clockOut(Request $request)
    {
        $clockTime = Carbon::parse($request->absent_date . ' ' . $request->absent_time);

        $absensiEntry = Absensi::where('nip', $request->nip)
            ->whereNull('clock_out_time')
            ->orderBy('clock_in_time', 'desc')
            ->first();

        if (!$absensiEntry) {
            return response()->json(['message' => 'Tidak ada data masuk yang aktif.'], 404);
        }

        $scheduleDate = Carbon::parse($absensiEntry->clock_in_time)->hour >= 21
                        ? Carbon::parse($absensiEntry->clock_in_time)->addDay()->toDateString()
                        : Carbon::parse($absensiEntry->clock_in_time)->toDateString();

        $schedule = $this->getRosterSchedule($request->nip, $scheduleDate);
        $target = $this->getTargetTimes($schedule);
        $overtimeRecord = $this->getApprovedOvertime($request->nip, $scheduleDate);

        $absensiEntry->clock_out_time = $clockTime;
        $statuses = $this->calculateAttendanceStatus($absensiEntry, $target, $overtimeRecord);

        $absensiEntry->status = $this->getCombinedStatus($statuses);
        $absensiEntry->save();

        return response()->json(['message' => 'Clock-out berhasil!', 'data' => $absensiEntry], 200);
    }

public function ajukanKoreksi(Request $request)
{
    $request->validate([
        'nip' => 'required|string|exists:karyawan,nopek',
        'date' => 'required|date',
        'time' => 'required|date_format:H:i',
        'alasan' => 'required|string|max:500',
        'correction_type' => 'required|in:IN,OUT',
    ]);

    $fullTimestamp = $request->date . ' ' . $request->time . ':00';
    $carbonTime = Carbon::parse($fullTimestamp);

    // ============================================================
    // LOGIKA KRUSIAL: Sinkronisasi Tanggal Jadwal (Shift Malam)
    // ============================================================
    // Jika admin menginput jam 23:50 tgl 23, maka jadwalnya adalah tgl 24.
    // Jika admin menginput jam 00:35 tgl 24, maka jadwalnya tetap tgl 24.

    $scheduleDate = $carbonTime->toDateString();

    // Jika koreksi dilakukan pada jam 21:00 ke atas, anggap ini untuk jadwal BESOK
    if ($carbonTime->hour >= 21) {
        $scheduleDate = $carbonTime->copy()->addDay()->toDateString();
    }
    // Jika koreksi dilakukan pada jam 00:00 - 04:00, anggap ini tetap jadwal HARI INI
    // (Karena shift malam dimulai dari jam 23:xx kemarin s/d jam 07:xx hari ini)

    // Dapatkan Admin yang Login
    $admin = $request->user();
    $adminName = $admin->name ?? 'Admin';

    try {
        $karyawan = Karyawan::where('nopek', $request->nip)->firstOrFail();

        // Cek Izin/Cuti pada tanggal jadwal tersebut
        $approvedRequest = $this->getApprovedAttendanceRequest($request->nip, $scheduleDate);
        if ($approvedRequest) {
            return response()->json(['message' => "Koreksi ditolak: Karyawan sedang {$approvedRequest->type} pada tgl jadwal {$scheduleDate}."], 403);
        }

        // Cari record absensi yang sudah ada berdasarkan scheduleDate
        // Kita gunakan logika yang sama dengan clockIn agar record yang ditemukan benar
        $absensi = Absensi::where('nip', $request->nip)
            ->where(function($q) use ($scheduleDate) {
                $q->whereDate('clock_in_time', $scheduleDate)
                  ->orWhere(DB::raw('DATE(ADDTIME(clock_in_time, "04:00:00"))'), $scheduleDate);
            })->first();

        if (!$absensi) {
            // Jika tidak ada record, hanya boleh buat baru jika tipenya IN
            if ($request->correction_type !== 'IN') {
                return response()->json(['message' => 'Gagal: Record Masuk tidak ditemukan untuk tanggal jadwal ini.'], 404);
            }
            $absensi = new Absensi(['nip' => $request->nip]);
        }

        // Update waktu berdasarkan tipe koreksi
        if ($request->correction_type === 'IN') {
            $absensi->clock_in_time = $carbonTime;
        } else {
            $absensi->clock_out_time = $carbonTime;
        }

        // Hitung ulang status berdasarkan jadwal (scheduleDate)
        $schedule = $this->getRosterSchedule($request->nip, $scheduleDate);
        $target = $this->getTargetTimes($schedule);
        $overtimeRecord = $this->getApprovedOvertime($request->nip, $scheduleDate);

        $statuses = $this->calculateAttendanceStatus($absensi, $target, $overtimeRecord);

        // Override Tepat Waktu untuk Shift 00:00 jika tapping/koreksi di jam 23:xx
        if ($target['in'] === '00:00:00' && $carbonTime->hour >= 23) {
            $statuses['status_in'] = 'Tepat Waktu';
        }

        $absensi->status = $this->getCombinedStatus($statuses);
        $absensi->alasan_koreksi = $request->alasan;
        $absensi->status_koreksi = 'Diterima';
        $absensi->save();

        // Log Aktivitas
        $this->logActivity('KOREKSI', "Admin {$adminName} mengubah jam {$request->correction_type} {$karyawan->name} menjadi {$fullTimestamp}. Status: {$absensi->status}", 'Presensi');

        return response()->json([
            'message' => 'Koreksi berhasil disimpan! Status diperbarui menjadi: ' . $absensi->status,
            'data' => $absensi
        ], 200);

    } catch (\Exception $e) {
        Log::error("Error koreksi: " . $e->getMessage());
        return response()->json(['message' => 'Terjadi kesalahan di server.'], 500);
    }
}
    public function getActiveShifts(Request $request) {
        $today = Carbon::today()->toDateString();

        try {
            $schedules = Schedule::where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->get();

            $shiftCounts = [];

            foreach ($schedules as $schedule) {
                $shiftType = $schedule->shift_type;
                $employeeCount = is_array($schedule->employee_nipeks) ? count($schedule->employee_nipeks) : 0;

                if ($employeeCount > 0) {
                    if (!isset($shiftCounts[$shiftType])) {
                        $shiftCounts[$shiftType] = [
                            'shiftType' => $shiftType,
                            'workSystem' => $schedule->work_system,
                            'schedule' => $schedule->schedule,
                            'jumlahKaryawan' => 0,
                        ];
                    }
                    $shiftCounts[$shiftType]['jumlahKaryawan'] += $employeeCount;
                }
            }

            if (isset($shiftCounts['Libur']) && count($shiftCounts) > 1) {
                 unset($shiftCounts['Libur']);
            }

            return response()->json(array_values($shiftCounts));

        } catch (\Exception $e) {
            Log::error("Error fetching active shifts for dashboard: " . $e->getMessage());
            return response()->json([], 500);
        }
    }

}
