<?php namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\AttendanceRequest; // Untuk data Cuti/Izin
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Endpoint BARU: Mendapatkan semua statistik yang dibutuhkan dashboard.
     * Endpoint: GET /api/dashboard/stats
     */
    public function getDashboardStats()
    {
        $today = Carbon::today()->toDateString();

        try {
            // --- 1. Total Karyawan ---
            $totalKaryawan = Karyawan::count();

            // --- 2. Metrik Kehadiran Hari Ini ---
            // Ambil semua presensi hari ini
            $presensiHariIni = Absensi::whereDate('clock_in_time', $today)->get();

            $tepatWaktu = $presensiHariIni->filter(fn($a) => str_contains($a->status, 'Tepat Waktu'))->count();
            $terlambat = $presensiHariIni->filter(fn($a) => str_contains($a->status, 'Terlambat'))->count();

            // Logika Belum Hadir (Total Karyawan Aktif - Karyawan yang sudah presensi hari ini)
            $totalKaryawanAktif = Karyawan::where('status', 'Aktif')->count();
            $sudahAbsen = $presensiHariIni->pluck('nip')->unique()->count();

            // Karyawan yang Cuti/Izin Hari Ini (Tidak dihitung 'Belum Hadir')
            $cutiHariIni = AttendanceRequest::where('status', 'Approved')
                ->whereDate('date_from', '<=', $today)
                ->whereDate('date_to', '>=', $today)
                ->pluck('nopek')->unique()->count();

            $belumHadir = max(0, $totalKaryawanAktif - $sudahAbsen - $cutiHariIni);

            // --- 3. Perlu Perhatian (Notifikasi) ---

            // Lupa Checkout (clock_in ada, clock_out null)
            $lupaCheckout = Absensi::whereDate('clock_in_time', '<', $today)
                ->whereNull('clock_out_time')
                ->count();

            // Tidak Aktif / Alfa (Ini harus dicek di laporan absensi, tapi disederhanakan)
            // Asumsi: Kita cek karyawan yang seharusnya masuk hari ini (roster) tapi tidak ada presensi
            // Untuk penyederhanaan, kita gunakan metrik karyawan yang seharusnya hadir (terdaftar aktif) tapi belum hadir
            $tidakAktif = $belumHadir;

            return response()->json([
                'total' => $totalKaryawan,
                'tepatWaktu' => $tepatWaktu,
                'terlambat' => $terlambat,
                'belumHadir' => $belumHadir, // Belum Hadir dari total karyawan aktif
                'lupaCheckout' => $lupaCheckout, // Presensi yang 'menggantung' kemarin
                'tidakAktif' => $tidakAktif, // Karyawan yang seharusnya ada tapi belum absensi
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Stats Error: " . $e->getMessage() . " on line " . $e->getLine());
            return response()->json(['message' => 'Gagal memuat statistik dashboard.'], 500);
        }
    }
}
