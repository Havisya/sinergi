<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Traits\LogsActivity; // <<< TAMBAHKAN

class AdminProfileController extends Controller
{
    use LogsActivity; // <<< GUNAKAN TRAIT

    // ... (getProfile dan debugBypass methods DIBIARKAN SAMA)

    public function getProfile(Request $request)
    {
        $bearerToken = $request->bearerToken();
        Log::info('Bearer token: ' . $request->bearerToken());

        try {
            $admin = auth()->user();

            if (!$admin) {
                Log::warning('Attempt to access /api/admin/profile without authentication.');
                return response()->json([
                    'message' => 'User tidak terautentikasi. Pastikan token valid.',
                ], 401);
            }

            Log::info('Admin profile accessed', ['id' => $admin->id, 'email' => $admin->email]);

            return response()->json([
                'message' => 'Berhasil mengambil data profil',
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role ?? 'Administrator',
                    // Memastikan last_login_at diakses dengan aman
                    'last_login' => $admin->last_login_at ? $admin->last_login_at->toDateTimeString() : null,
                    'system_status' => [
                        'machine_connection' => 'Online',
                        'last_sync' => now()->toDateTimeString(),
                    ],
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Error di getProfile: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Terjadi error pada server',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Asumsi fungsi broadcastMessage ada di sini (sesuai rute api.php)
    public function broadcastMessage(Request $request)
    {
        $request->validate(['message' => 'required|string|max:500']);

        // Simulasi pengiriman pesan broadcast ke sistem/TV

        // --- LOG AKTIVITAS ---
        $this->logActivity(
            'BROADCAST',
            "Mengirim pesan broadcast: \"" . substr($request->message, 0, 50) . "...\"",
            'Sistem'
        );
        // ---------------------

        return response()->json(['message' => 'Pesan broadcast berhasil dikirim.'], 200);
    }
    public function debugBypass(Request $request)
{
    try {
        // COBA ambil SATU user secara PAKSA, tanpa auth()->user()
        $admin = User::where('role', 'Super Admin')->first();

        // Cek 1: Pastikan code sampai di sini (jika perlu)
        // dd('Controller Terpanggil, Mencoba ambil user pertama.');

        if (!$admin) {
            return response()->json([
                'message' => 'User Admin tidak ditemukan di database. Cek data seeding Anda.',
            ], 404);
        }

        // Cek 2: Tampilkan data user yang diambil (termasuk last_login_at)
        return response()->json([
            'message' => 'Berhasil mengambil data DARI DATABASE.',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role ?? 'Administrator',
                // Pastikan kolom ini ada dan bisa diakses
                'last_login' => $admin->last_login_at ? $admin->last_login_at->toDateTimeString() : null,
            ]
        ], 200);

    } catch (\Throwable $e) {
        // Ini akan MENGHASILKAN pesan error di log Laravel (storage/logs/laravel.log)
        \Log::error('DEBUG BYPASS ERROR TERTANGKAP: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json([
            'message' => 'Terjadi Error Fatal di Model/Database',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}
