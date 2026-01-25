<?php
// app/Traits/LogsActivity.php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    /**
     * Mencatat aktivitas admin atau sistem.
     */
    protected function logActivity(string $action, string $details, string $category = 'Umum'): void
    {
        // Default data jika otentikasi gagal
        $userId    = null;
        $userName  = 'Sistem/Unknown';
        $ipAddress = Request::ip() ?? null;

        try {
            // 🎯 Perbaikan: Prioritaskan Auth Sanctum untuk API, baru Auth::user() untuk sesi web/default
            // Ini memastikan Trait selalu melihat pengguna yang dilampirkan oleh token Sanctum.
            $user = Auth::guard('sanctum')->user() ?? Auth::user();

            if ($user) {
                $userId    = $user->id;
                // Pastikan user_name diambil, jika null, pakai fallback.
                $userName  = $user->name ?? "User #{$user->id}";
            }

            // Log::warning('LOG_AUTH_FAIL: ...'); // DEBUGGING: Hapus warning ini di production

            // Batasi panjang detail (pastikan kolom 'details' di DB cukup besar)
            $safeDetails = substr($details, 0, 10000);

            // 2. Jalankan INSERT database
            DB::table('activity_logs')->insert([
                'user_id'    => $userId,
                'user_name'  => $userName, // <-- Kolom ini sekarang diisi nama Admin yang terotentikasi
                'action'     => strtoupper($action),
                'details'    => $safeDetails,
                'category'   => $category,
                'ip_address' => $ipAddress,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log sukses insert (hanya untuk verifikasi)
            // Log::info("LOG_INSERT_SUCCESS: Aktivitas {$action} berhasil dicatat oleh {$userName}."); // Hapus di production

        } catch (\Throwable $e) {
            // ... (Logika error DB) ...
             Log::error('LOG_DB_FATAL_FAIL: Gagal insert ke activity_logs.', [
                'exception_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'attempted_user' => $userName,
                'attempted_action' => $action,
             ]);
        }
    }
}
