<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log; // Pastikan Log di-import
use App\Traits\LogsActivity;

class AdminController extends Controller
{
    use LogsActivity;

    // GET /api/settings/admins (DIBIARKAN SAMA)
    public function index()
    {
        try {
            $valid_roles = ['Super Admin', 'HR Admin', 'Lokasi Admin'];

            $admins = User::whereIn('role', $valid_roles)
                ->select('id', 'name', 'email', 'role')
                ->get();

            return response()->json($admins, 200);
        } catch (\Exception $e) {
            Log::error("Admin Fetch Error: " . $e->getMessage());
            return response()->json(['message' => 'Gagal memuat data admin.'], 500);
        }
    }

    // GET /api/settings/admins/{id} (DIBIARKAN SAMA)
    public function show($id)
    {
        try {
            $admin = User::select('id', 'name', 'email', 'role')->findOrFail($id);
            return response()->json($admin, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Admin tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            Log::error("Admin Show Error: " . $e->getMessage());
            return response()->json(['message' => 'Gagal memuat detail admin.'], 500);
        }
    }

    // POST /api/settings/admins
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'role' => ['required', Rule::in(['Super Admin', 'HR Admin', 'Lokasi Admin'])],
                'password' => 'required|string|min:8',
            ]);

            $admin = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'password' => Hash::make($request->password),
            ]);

            // --- LOG AKTIVITAS (TETAP AKTIF) ---
            $this->logActivity(
                'CREATE',
                "Menambah akun admin baru: " . $admin->name . " ({$admin->role})",
                'Pengaturan'
            );
            // ---------------------

            return response()->json([
                'message' => 'Admin baru berhasil ditambahkan!',
                'admin' => $admin->only(['id', 'name', 'email', 'role'])
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) { // <-- MENGGANTI \Exception menjadi \Throwable untuk menangkap FATAL ERROR
            // Ini adalah jaring pengaman terakhir yang menangkap dan mencatat sumber 500 yang tidak terlihat
            Log::error("CRITICAL ERROR AdminController@store: " . $e->getMessage(), [
                 'file' => $e->getFile(),
                 'line' => $e->getLine(),
                 'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Terjadi kesalahan server kritis. Periksa log Laravel.'], 500);
        }
    }

    // PUT /api/settings/admins/{id}
    public function update(Request $request, $id)
    {
        try {
            $admin = User::findOrFail($id);
            $oldName = $admin->name;

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => [
                    'required', 'string', 'email', 'max:255',
                    Rule::unique('users')->ignore($admin->id)
                ],
                'role' => ['required', Rule::in(['Super Admin', 'HR Admin', 'Lokasi Admin'])],
                'password' => 'nullable|string|min:8',
            ]);

            $admin->name = $request->name;
            $admin->email = $request->email;
            $admin->role = $request->role;

            if ($request->filled('password')) {
                $admin->password = Hash::make($request->password);
            }

            $admin->save();

            // --- LOG AKTIVITAS (TETAP AKTIF) ---
            $this->logActivity(
                'UPDATE',
                "Memperbarui akun admin: {$oldName} menjadi {$admin->name} (ID: {$id}).",
                'Pengaturan'
            );
            // ---------------------

            return response()->json(['message' => 'Data admin berhasil diperbarui!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Admin tidak ditemukan.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) { // <-- MENGGANTI \Exception menjadi \Throwable
            Log::error("CRITICAL ERROR AdminController@update: " . $e->getMessage(), [
                 'file' => $e->getFile(),
                 'line' => $e->getLine(),
                 'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Gagal memperbarui admin.'], 500);
        }
    }

    // DELETE /api/settings/admins/{id}
    public function destroy($id)
    {
        try {
            $admin = User::findOrFail($id);
            $adminName = $admin->name;
            $admin->delete();

            // --- LOG AKTIVITAS (TETAP AKTIF) ---
            $this->logActivity(
                'DELETE',
                "Menghapus akun admin: " . $adminName . " (ID: {$id})",
                'Pengaturan'
            );
            // ---------------------

            return response()->json(['message' => 'Akun admin berhasil dihapus!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Admin tidak ditemukan.'], 404);
        } catch (\Throwable $e) { // <-- MENGGANTI \Exception menjadi \Throwable
            Log::error("CRITICAL ERROR AdminController@destroy: " . $e->getMessage(), [
                 'file' => $e->getFile(),
                 'line' => $e->getLine(),
                 'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Gagal menghapus admin.'], 500);
        }
    }
}
