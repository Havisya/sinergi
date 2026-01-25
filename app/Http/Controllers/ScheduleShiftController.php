<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShiftSchedule; // Pastikan Model ini sudah ada dan benar
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
// use App\Traits\LogsActivity; // Pastikan Trait ini ada di file Traits Anda
use Illuminate\Database\QueryException;

/**
 * Controller ini bertanggung jawab untuk mengelola CRUD (Create, Read, Update, Delete)
 * data Jadwal Shift yang akan ditampilkan di Duty Roster (Struktur Shift).
 */
class ScheduleShiftController extends Controller {

    // use LogsActivity; // Aktifkan jika Trait sudah benar-benar ada

    /**
     * Mengambil semua jadwal shift yang telah disimpan.
     * Endpoint: GET /api/shifts/schedules (RUTE PUBLIK)
     */
    public function index() {
        try {
            $schedules = ShiftSchedule::all();

            $formattedShifts = $schedules->map(function ($schedule) {
                // Pastikan kolom 'data' di-cast sebagai array/objek di model ShiftSchedule
                $data = $schedule->data ?? [];

                // Menambahkan ID dokumen (dari database SQL) dan kunci tanggal untuk frontend
                return array_merge($data, [
                    'id' => $schedule->id,
                ]);
            });

            return response()->json($formattedShifts);

        } catch (\Exception $e) {
            Log::error("Error fetching shift schedules: " . $e->getMessage());
            // Mengembalikan 401 di sini akan menyebabkan error, gunakan 500 untuk error server
            return response()->json(['message' => 'Gagal memuat jadwal shift.'], 500);
        }
    }

    /**
     * Menyimpan atau memperbarui jadwal shift.
     * Endpoint: POST /api/shifts/schedules (RUTE PUBLIK)
     * KARENA RUTE INI PUBLIK, JANGAN LAKUKAN AUTH CHECK DI SINI!
     */
public function storeOrUpdate(Request $request) {
    // 1. Validasi yang diperbaiki
    $request->validate([
        'id' => 'nullable|integer|exists:shift_schedules,id',
        'dateKey' => 'required|string',
        'shiftName' => 'required|string',
        'shiftTime' => 'required|string',
        // Hapus 'url' untuk mencegah error saat string kosong, biarkan dicek di level aplikasi
        'photoUrl' => 'nullable|string',
        'roles' => 'required|array',
    ]);

    // 2. Pastikan sub-properti roles (name, nopek, members) ikut terbawa
    $dataToSave = [
        'dateKey' => $request->dateKey,
        'shiftName' => $request->shiftName,
        'shiftTime' => $request->shiftTime,
        'photoUrl' => $request->photoUrl ?: null, // Jika kosong jadi null
        'roles' => $request->roles, // Simpan array utuh dari React
    ];

    try {
        if ($request->filled('id')) {
            $schedule = ShiftSchedule::findOrFail($request->id);
            $schedule->data = $dataToSave;
            $schedule->save();
            $message = 'Jadwal shift berhasil diperbarui.';
        } else {
            // Gunakan updateOrCreate untuk mencegah duplikasi jika tabel database mendukung unique_key
            $schedule = ShiftSchedule::updateOrCreate(
                ['unique_key' => $request->dateKey . '-' . $request->shiftName],
                ['data' => $dataToSave]
            );
            $message = 'Jadwal shift berhasil disimpan.';
        }

        return response()->json([
            'message' => $message,
            'data' => array_merge($schedule->data, ['id' => $schedule->id])
        ], 201);

    } catch (\Exception $e) {
        Log::error("Error saving shift: " . $e->getMessage());
        return response()->json(['message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
    /**
     * Menghapus jadwal shift berdasarkan ID.
     * Endpoint: DELETE /api/shifts/schedules/{id} (RUTE PUBLIK)
     */
    public function destroy($id) {
        try {
            $schedule = ShiftSchedule::findOrFail($id);

            $shiftName = $schedule->data['shiftName'] ?? 'N/A';
            $dateKey = $schedule->data['dateKey'] ?? 'N/A';

            $schedule->delete();

            // Log Aktivitas (jika LogsActivity diaktifkan)
            /*
            if (method_exists($this, 'logActivity')) {
                 $this->logActivity(
                    'DELETE',
                    "Menghapus Struktur Shift Harian: {$shiftName} untuk tgl {$dateKey}.",
                    'Jadwal'
                 );
            }
            */

            return response()->json(['message' => 'Jadwal shift berhasil dihapus.'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Jadwal shift tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            Log::error("Error deleting shift schedule: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menghapus jadwal shift.'], 500);
        }
    }
}
