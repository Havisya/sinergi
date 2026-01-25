<?php namespace App\Http\Controllers;

use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Traits\LogsActivity; // <<< TAMBAHKAN INI

class KaryawanController extends Controller
{
    use LogsActivity; // <<< TAMBAHKAN INI

    /**
     * Tampilkan daftar semua karyawan.
     */
    public function index()
    {
        $karyawan = Karyawan::all();
        return response()->json($karyawan);
    }

    /**
     * Mengembalikan daftar ringkas NIP, Nama, dan Posisi karyawan aktif.
     */
    public function getNamesList()
    {
        try {
            $karyawanList = Karyawan::where('status', 'Aktif')
                                            ->select('nopek', 'name', 'position', 'func', 'category', 'work_system')
                                            ->orderBy('name')
                                            ->get();

            return response()->json($karyawanList, 200);

        } catch (\Exception $e) {
            Log::error("Error fetching Karyawan names list: " . $e->getMessage());
            return response()->json(['message' => 'Gagal memuat daftar karyawan. Cek log server.'], 500);
        }
    }

    /**
     * Simpan karyawan baru ke database.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'nopek' => 'required|unique:karyawan|max:255',
                'name' => 'required|max:255',
                'position' => 'required|max:255',
                'status' => 'required|in:Aktif,Cuti,Non-Aktif,Dinas',

                'category' => 'nullable|in:PWTT,PWT,TAD,Security',
                'func' => 'nullable|string|max:255',
                'work_system' => 'nullable|in:Shift,Non-Shift',
                'schedule' => 'nullable|max:100',
            ]);

            $karyawan = Karyawan::create($validatedData);

            // --- LOG AKTIVITAS (CREATE) ---
            $this->logActivity(
                'CREATE',
                "Menambah data karyawan baru: {$karyawan->name} (NIP: {$karyawan->nopek}).",
                'Karyawan'
            );
            // -----------------------------

            return response()->json($karyawan, 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Perbarui data karyawan yang spesifik.
     */
    public function update(Request $request, $id)
    {
        $karyawan = Karyawan::findOrFail($id);
        $oldName = $karyawan->name; // Ambil nama lama untuk log

        try {
            $validatedData = $request->validate([
                'nopek' => 'required|max:255|unique:karyawan,nopek,' . $id,
                'name' => 'required|max:255',
                'position' => 'required|max:255',
                'status' => 'required|in:Aktif,Cuti,Non-Aktif,Dinas',

                'category' => 'nullable|in:PWTT,PWT,TAD,Security',
                'func' => 'nullable|string|max:255',
                'work_system' => 'nullable|in:Shift,Non-Shift',
                'schedule' => 'nullable|max:100',
            ]);

            $karyawan->update($validatedData);

            // --- LOG AKTIVITAS (UPDATE) ---
            $this->logActivity(
                'UPDATE',
                "Memperbarui data karyawan: {$oldName} menjadi {$karyawan->name} (NIP: {$karyawan->nopek}).",
                'Karyawan'
            );
            // -----------------------------

            return response()->json($karyawan, 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Hapus karyawan dari database.
     */
    public function destroy($id)
    {
        $karyawan = Karyawan::findOrFail($id);
        $karyawanName = $karyawan->name;
        $karyawanNopek = $karyawan->nopek;

        $karyawan->delete();

        // --- LOG AKTIVITAS (DELETE) ---
        $this->logActivity(
            'DELETE',
            "Menghapus data karyawan: {$karyawanName} (NIP: {$karyawanNopek}).",
            'Karyawan'
        );
        // -----------------------------

        return response()->json(null, 204);
    }
}
