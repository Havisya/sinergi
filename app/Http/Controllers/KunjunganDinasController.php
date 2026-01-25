<?php

namespace App\Http\Controllers;

use App\Models\KunjunganDinas;
use App\Models\Karyawan;
use App\Models\Absensi;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Traits\LogsActivity;


class KunjunganDinasController extends Controller
{
    // FIX PENTING 1: Gunakan Trait LogsActivity agar method logActivity tersedia
    use LogsActivity;

    /**
     * Menampilkan daftar semua kunjungan dinas (index).
     */
    public function index()
    {
        try {
            // Titik kritis: Memastikan tabel ada dan kolom 'date' ada.
            $data = KunjunganDinas::orderBy('date', 'desc')->get();
            return response()->json($data);

        } catch (\Exception $e) {
            // FIX: Logging lebih detail, termasuk baris file
            Log::error("CRASH PADA KUNJUNGAN DINAS INDEX", [
                'exception_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Kembalikan status 500 dengan pesan error yang informatif
            return response()->json([
                'message' => 'Gagal memuat data kunjungan dinas. Crash Controller.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data baru (Store).
     */
    public function store(Request $request)
    {
        try {
            // PERUBAHAN VALIDASI: Mengganti 'date' dengan 'start_date' dan 'end_date'
            $validatedData = $request->validate([
                'nopek' => 'required|string|max:255',
                'name' => 'required|string|max:255',
                'position' => 'required|string|max:255',
                'department' => 'required|string|max:255',
                'start_date' => 'required|date', // Tanggal Mulai
                'end_date' => 'required|date|after_or_equal:start_date', // Tanggal Selesai
                'destination' => 'required|string|max:255',
                'purpose' => 'required|string',
                // FIX: Status Absensi harus sesuai dengan skema Absensi, bukan 'present/late/absent'
                // Karena ini dinas, status yang paling aman adalah 'Dinas',
                // tetapi jika status di UI tidak diubah, kita biarkan saja.
                // Jika status ini mengacu pada status Presensi, maka harus disesuaikan.
                // Kita asumsikan status ini adalah status internal Kunjungan Dinas.
                'status' => 'required|string|max:50',
            ]);

            $startDate = Carbon::parse($validatedData['start_date']);
            $endDate = Carbon::parse($validatedData['end_date']);

            $daysInDuty = $startDate->diffInDays($endDate) + 1;

            $records = [];
            $currentDate = $startDate->copy();

            // 1. Simpan Kunjungan Dinas per hari
            while ($currentDate->lte($endDate)) {

                // Simpan record Kunjungan Dinas
                $kunjungan = KunjunganDinas::create([
                    'nopek' => $validatedData['nopek'],
                    'name' => $validatedData['name'],
                    'position' => $validatedData['position'],
                    'department' => $validatedData['department'],
                    'date' => $currentDate->toDateString(), // Gunakan kolom 'date' untuk setiap hari
                    'destination' => $validatedData['destination'],
                    'purpose' => $validatedData['purpose'],
                    'status' => $validatedData['status'],
                ]);
                $records[] = $kunjungan;

                // 2. INTEGRASI OTOMATIS KE ABSENSI (Hanya jika itu hari kerja)
                // Anda mungkin ingin menambahkan pengecekan hari kerja di sini.
                // Contoh: if ($currentDate->isWeekday()) { ... }

                // Hapus absensi yang mungkin sudah ada pada hari itu
                Absensi::where('nip', $validatedData['nopek'])
                        ->whereDate('clock_in_time', $currentDate->toDateString())
                        ->delete();

                // Buat entri baru di tabel Absensi (Presensi Otomatis)
                Absensi::create([
                    'nip' => $validatedData['nopek'],
                    'clock_in_time' => $currentDate->format('Y-m-d') . ' 07:30:00', // Asumsi Jam Masuk Otomatis 07:30
                    'clock_out_time' => $currentDate->format('Y-m-d') . ' 16:00:00', // Asumsi Jam Pulang Otomatis 16:00
                    'device_id' => 'Kunjungan Dinas', // Tanda pengenal
                    'status' => 'Dinas | Tepat Waktu', // Status Presensi
                    'status_koreksi' => 'Diterima', // Langsung dianggap valid
                    // Tambahkan kolom lain yang relevan seperti 'is_overtime', 'overtime_type', dll jika diperlukan
                ]);

                $currentDate->addDay();
            }

            // --- LOG AKTIVITAS ---
            $this->logActivity(
                'CREATE',
                "Menambah Kunjungan Dinas untuk {$validatedData['name']} ({$daysInDuty} hari) ke {$validatedData['destination']}.",
                'Dinas'
            );


            return response()->json([
                'message' => "Data kunjungan dinas untuk {$daysInDuty} hari berhasil ditambahkan dan presensi otomatis dicatat!",
                'records_count' => $daysInDuty
            ], 201);

        } catch (ValidationException $e) {
            Log::warning("Validation Error during KunjunganDinas store: " . json_encode($e->errors()));
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Error storing KunjunganDinas and integrating Absensi: " . $e->getMessage() . " on line " . $e->getLine());
            return response()->json(['message' => 'Gagal menyimpan data dan mencatat presensi. Cek log server.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $kunjungan = KunjunganDinas::findOrFail($id);
            // FIX PENTING 2: Simpan nilai lama sebelum update untuk logging
            $oldDestination = $kunjungan->destination;

            $validatedData = $request->validate([
                'nopek' => 'required|string|max:255',
                'name' => 'required|string|max:255',
                'position' => 'required|string|max:255',
                'department' => 'required|string|max:255',
                'date' => 'required|date',
                'destination' => 'required|string|max:255',
                'purpose' => 'required|string',
                'status' => 'required|string|max:50', // Diubah menjadi string untuk fleksibilitas
            ]);

            $kunjungan->update($validatedData);

            // --- LOG AKTIVITAS ---
            // FIX PENTING 3: Variabel $oldDestination sekarang terdefinisi.
            $this->logActivity(
                'UPDATE',
                "Memperbarui Kunjungan Dinas untuk {$kunjungan->name} dari {$oldDestination} ke {$kunjungan->destination}.",
                'Dinas'
            );

            return response()->json(['message' => 'Data kunjungan dinas berhasil diperbarui!', 'data' => $kunjungan], 200);


        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Error updating KunjunganDinas:", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Gagal memperbarui data.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $kunjungan = KunjunganDinas::findOrFail($id);
            // FIX PENTING 4: Definisikan variabel untuk logging sebelum delete
            $kunjunganName = $kunjungan->name;
            $kunjunganDestination = $kunjungan->destination;

            // Hapus juga record Absensi yang terkait jika diperlukan.
            // Absensi::where('nip', $kunjungan->nopek)
            //         ->whereDate('clock_in_time', $kunjungan->date)
            //         ->delete();

            $kunjungan->delete();

            // --- LOG AKTIVITAS ---
            // FIX PENTING 5: Variabel $kunjunganName dan $kunjunganDestination sekarang terdefinisi.
            $this->logActivity(
                'DELETE',
                "Menghapus Kunjungan Dinas dari {$kunjunganName} ke {$kunjunganDestination}.",
                'Dinas'
            );

            return response()->json(['message' => 'Data kunjungan dinas berhasil dihapus!'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            Log::error("Error deleting KunjunganDinas:", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Gagal menghapus data.'], 500);
        }
    }
}
