<?php

namespace App\Http\Controllers;

use App\Models\Overtime;
use App\Models\Karyawan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Traits\LogsActivity;


class OvertimeController extends Controller
{
    // FIX PENTING 1: Gunakan Trait LogsActivity agar method logActivity tersedia
    use LogsActivity;

    /**
     * [READ] Mengambil semua jadwal lembur dengan filter.
     */
    public function index(Request $request)
    {
        try {
            $query = Overtime::query();

            // Contoh sederhana implementasi filter pencarian
            if ($request->filled('search')) {
                $searchTerm = strtolower($request->input('search'));
                $query->where(function($q) use ($searchTerm) {
                    $q->where('reason', 'like', "%{$searchTerm}%")
                      ->orWhere('approver', 'like', "%{$searchTerm}%")
                      // FIX: Pencarian di JSON field 'employees' harus menggunakan JSON_EXTRACT atau diubah ke string
                      // Kita asumsikan 'employees' adalah kolom JSON yang menyimpan array karyawan
                      // Perbaikan sederhana: Cari string JSON yang mengandung searchTerm
                      ->orWhere('employees', 'like', "%{$searchTerm}%");
                });
            }

            $overtimes = $query->orderBy('date', 'desc')->get();

            return response()->json($overtimes->map(function ($ot) {
                // FIX: Gunakan null-safe operator untuk date dan time parsing jika memungkinkan,
                // meskipun Overtime model harusnya menggunakan casts untuk Carbon.
                return [
                    'id' => $ot->id,
                    'date' => $ot->date?->toDateString(), // Tambahkan null-safe operator
                    'approver' => $ot->approver,
                    'employees' => $ot->employees,
                    'reason' => $ot->reason,
                    // Pastikan $ot->start_time dan $ot->end_time adalah format waktu valid atau objek Carbon
                    'startTime' => $ot->start_time ? Carbon::parse($ot->start_time)->format('H:i') : null,
                    'endTime' => $ot->end_time ? Carbon::parse($ot->end_time)->format('H:i') : null,
                ];
            }));
        } catch (\Exception $e) {
            \Log::error("Error fetching overtime data: " . $e->getMessage());
            return response()->json(['message' => 'Gagal memuat data lembur.'], 500);
        }
    }

    /**
     * [CREATE] Menyimpan jadwal lembur baru.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'startTime' => 'required|date_format:H:i',
                'endTime' => 'required|date_format:H:i',
                'reason' => 'required|string|max:500',
                'approver' => 'required|string|max:100',
                'employees' => 'required|array|min:1',
                'employees.*.id' => 'required|string',
                'employees.*.name' => 'required|string',
                'employees.*.jobTitle' => 'nullable|string',
            ]);

            $overtime = Overtime::create([
                'date' => $validated['date'],
                'start_time' => $validated['startTime'],
                'end_time' => $validated['endTime'],
                'reason' => $validated['reason'],
                'approver' => $validated['approver'],
                'employees' => $validated['employees'],
            ]);

            // Hitung dan siapkan variabel untuk LOG AKTIVITAS
            $employeeCount = count($validated['employees']);
            // Ambil 3 nama pertama untuk detail log
            $employeeNames = implode(', ', array_map(fn($e) => $e['name'], array_slice($validated['employees'], 0, 3)));

            // Opsional: Cek apakah karyawan terdaftar di tabel Karyawan
            foreach ($validated['employees'] as $employee) {
                if (!Karyawan::where('nopek', $employee['id'])->exists()) {
                    // Log warning jika NIP tidak ditemukan, tapi tetap simpan lembur
                    \Log::warning("NIP Lembur tidak ditemukan di data Karyawan: " . $employee['id']);
                }
            }

            // --- LOG AKTIVITAS ---
            // FIX PENTING 2: Variabel $employeeCount dan $employeeNames sekarang didefinisikan sebelum dipanggil
            $this->logActivity(
                'CREATE',
                "Menambah jadwal lembur untuk {$employeeCount} karyawan pada tgl {$overtime->date->toDateString()} ({$employeeNames}...).",
                'Lembur'
            );

            return response()->json(['message' => 'Jadwal lembur berhasil disimpan.', 'data' => $overtime], 201);

        } catch (\Exception $e) {
            \Log::error("Error storing overtime: " . $e->getMessage() . " on line " . $e->getLine());
            return response()->json(['message' => 'Gagal menyimpan jadwal lembur.'], 500);
        }
    }

    /**
     * [UPDATE] Memperbarui jadwal lembur yang ada.
     */
    public function update(Request $request, $id)
    {
        try {
            $overtime = Overtime::findOrFail($id);

            $validated = $request->validate([
                'date' => 'required|date',
                'startTime' => 'required|date_format:H:i',
                'endTime' => 'required|date_format:H:i',
                'reason' => 'required|string|max:500',
                'approver' => 'required|string|max:100',
                'employees' => 'required|array|min:1',
                'employees.*.id' => 'required|string',
                'employees.*.name' => 'required|string',
                'employees.*.jobTitle' => 'nullable|string',
            ]);

            $overtime->update([
                'date' => $validated['date'],
                'start_time' => $validated['startTime'],
                'end_time' => $validated['endTime'],
                'reason' => $validated['reason'],
                'approver' => $validated['approver'],
                'employees' => $validated['employees'],
            ]);

            // Hitung dan siapkan variabel untuk LOG AKTIVITAS
            // FIX PENTING 3: Variabel $employeeCount sekarang didefinisikan sebelum dipanggil
            $employeeCount = count($validated['employees']);

            $this->logActivity(
                'UPDATE',
                "Memperbarui jadwal lembur tgl {$overtime->date->toDateString()} untuk {$employeeCount} karyawan.",
                'Lembur'
            );


            return response()->json(['message' => 'Jadwal lembur berhasil diperbarui.', 'data' => $overtime], 200);

        } catch (\Exception $e) {
            \Log::error("Error updating overtime ID {$id}: " . $e->getMessage() . " on line " . $e->getLine());
            return response()->json(['message' => 'Gagal memperbarui jadwal lembur.'], 500);
        }
    }

    /**
     * [DELETE] Menghapus jadwal lembur.
     */
    public function destroy($id)
    {
        try {
            $overtime = Overtime::findOrFail($id); // Ambil objek sebelum dihapus
            // FIX PENTING 4: Definisikan variabel untuk logging sebelum delete
            $overtimeDate = $overtime->date->toDateString();

            Overtime::destroy($id);

            // --- LOG AKTIVITAS ---
            // FIX PENTING 5: Variabel $overtimeDate sekarang terdefinisi.
            $this->logActivity(
                'DELETE',
                "Menghapus jadwal lembur tgl {$overtimeDate} (ID: {$id}).",
                'Lembur'
            );

            return response()->json(['message' => 'Jadwal lembur berhasil dihapus.'], 204);

        } catch (\Exception $e) {
            \Log::error("Error deleting overtime ID {$id}: " . $e->getMessage() . " on line " . $e->getLine());
            return response()->json(['message' => 'Gagal menghapus jadwal lembur.'], 500);
        }
    }
}
