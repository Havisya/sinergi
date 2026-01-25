<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Absensi;
use Carbon\Carbon;

class GetFingerprintLogs extends Command
{
    /**
     * Nama dan tanda tangan dari command console.
     */
    protected $signature = 'fingerprint:pull';

    /**
     * Deskripsi command.
     */
    protected $description = 'Tarik data log fingerprint hari ini secara real-time dan akurat';

    /**
     * Eksekusi command console.
     */
    public function handle()
    {
        $ip = '172.20.10.3'; // Alamat IP mesin fingerprint
        $user = 'admin';     // Username mesin
        $pass = '12345678adg'; // Password mesin

        $this->info("Memulai penarikan data real-time dari mesin $ip...");

        try {
            // Mengambil seluruh log dari awal hari sampai akhir hari ini agar data tidak terlewat
            $startTime = now()->startOfDay()->format('Y-m-d\TH:i:s');
            $endTime   = now()->endOfDay()->format('Y-m-d\TH:i:s');

            $response = Http::withDigestAuth($user, $pass)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("http://$ip/ISAPI/AccessControl/AcsEvent?format=json", [
                    "AcsEventCond" => [
                        "searchID" => (string) str_replace('.', '', microtime(true)),
                        "searchResultPosition" => 0,
                        "maxResults" => 3500, // Kapasitas log ditingkatkan
                        "major" => 5,
                        "minor" => 38,
                        "startTime" => $startTime,
                        "endTime"   => $endTime,
                        "sort" => "asc" // Memproses dari waktu paling awal ke terbaru
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $logs = $data['AcsEvent']['InfoList'] ?? [];

                if (empty($logs)) {
                    $this->warn("Koneksi sukses, tetapi tidak ada log ditemukan untuk hari ini.");
                    return;
                }

                $countNew = 0;
                $countUpdate = 0;

                foreach ($logs as $log) {
                    if (empty($log['employeeNoString'])) continue;

                    // Normalisasi NIP menjadi 3 digit (contoh: 50 menjadi 050)
                    $nipRaw = trim($log['employeeNoString']);
                    $nip = str_pad($nipRaw, 3, "0", STR_PAD_LEFT);

                    $eventTime = $log['time'];
                    $tanggal = Carbon::parse($eventTime)->format('Y-m-d');
                    $jamAbsen = Carbon::parse($eventTime)->format('Y-m-d H:i:s');

                    // Cari apakah karyawan sudah memiliki catatan absensi pada tanggal tersebut
                    $attendance = Absensi::where('nip', $nip)
                        ->whereDate('clock_in_time', $tanggal)
                        ->first();

                    if (!$attendance) {
                        // --- LOGIKA INSERT (CLOCK-IN) ---
                        $jamHanya = Carbon::parse($eventTime)->format('H:i');
                        // Logika status awal jika belum diatur jadwal spesifik di Roster
                        $statusAwal = ($jamHanya > '08:00') ? 'Terlambat' : 'Tepat Waktu';

                        Absensi::create([
                            'nip' => $nip,
                            'clock_in_time' => $jamAbsen,
                            'status' => "$statusAwal | Belum Keluar", // Status default saat baru masuk
                        ]);

                        $this->line("<info>BERHASIL:</info> NIP $nip Clock-in pada $jamAbsen");
                        $countNew++;
                    } else {
                        // --- LOGIKA UPDATE (CLOCK-OUT) ---
                        $jamTappingBaru = Carbon::parse($jamAbsen);
                        $jamMasukDB = Carbon::parse($attendance->clock_in_time);

                        /** * PERBAIKAN UTAMA:
                         * Menggunakan gt (greater than) agar setiap tapping yang lebih baru dari jam masuk
                         * langsung tercatat sebagai clock-out, meskipun selisihnya hanya hitungan detik.
                         */
                        if ($jamTappingBaru->gt($jamMasukDB)) {

                            // Ambil status awal (Tepat Waktu/Terlambat) dan hapus keterangan "Belum Keluar"
                            $currentStatus = $attendance->status;
                            $cleanStatus = trim(explode('|', $currentStatus)[0]);

                            $attendance->update([
                                'clock_out_time' => $jamAbsen,
                                'status' => $cleanStatus // Menjadi status bersih untuk diolah AbsensiController
                            ]);

                            $this->line("<comment>UPDATE:</comment> NIP $nip Clock-out diperbarui ke $jamAbsen");
                            $countUpdate++;
                        }
                    }
                }

                $this->info("Sinkronisasi Selesai. ($countNew data baru, $countUpdate update jam pulang)");

            } else {
                $this->error("Gagal terhubung ke mesin. Status: " . $response->status());
                Log::error("Fingerprint API Error: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("Terjadi kesalahan: " . $e->getMessage());
            Log::error("Fingerprint Command Crash: " . $e->getMessage());
        }
    }
}
