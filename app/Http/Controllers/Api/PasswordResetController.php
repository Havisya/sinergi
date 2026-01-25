<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PasswordResetController extends Controller
{
    /**
     * 📧 Memproses permintaan untuk mengirimkan link/kode reset password.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $response = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($response === Password::RESET_LINK_SENT) {
            Log::info("✅ Password reset link request successful for: {$request->email}.");

            return response()->json([
                'status' => 'success',
                'message' => 'Link reset password telah dikirim ke email Anda.',
            ], 200);
        }

        Log::warning("❌ Password reset link failed for: {$request->email}.");
        $message = trans($response);

        throw ValidationException::withMessages([
            'email' => [$message],
        ]);
    }

    /**
     * 🔄 Mengubah password menggunakan email, password baru, dan token.
     */
    public function reset(Request $request)
    {
        // 1. Validasi input dasar
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        try {
            // 2. Panggil PasswordBroker untuk mereset password
            $response = \Illuminate\Support\Facades\Password::broker()->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (\App\Models\User $user = null, string $password) { // <-- Amankan user dengan default null

                    // --- BARIS 80 ---
                    if (!$user) {
                        // Jika user NULL, kita lempar exception untuk ditangkap oleh catch
                        \Illuminate\Support\Facades\Log::error("KRITIS: User di Password Reset NULL. Email/Token tidak valid.");
                        throw new \Exception("User data is invalid or token mismatch.");
                    }

                    // --- BARIS 84 (Fokus Error) --- KITA SIMPLIFIKASI PROSES SAVE
                    // 3. Simpan password baru
                    $user->password = \Illuminate\Support\Facades\Hash::make($password);
                    $user->setRememberToken(null);
                    $user->save(); // <--- PANGGIL SAVE SECARA LANGSUNG

                    \Illuminate\Support\Facades\Log::info("💾 User ID: {$user->id} - Password berhasil disimpan ke DB.");
                }
            );

            // 4. Periksa hasil respons sukses
            if ($response === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
                \Illuminate\Support\Facades\Log::info("✅ Password successfully reset for: {$request->email}.");
                return response()->json([
                    'status' => 'success',
                    'message' => 'Password Anda berhasil direset.'
                ], 200);
            }

            // 5. Tangani Kegagalan Token/Email (Jika broker mengembalikan kode gagal, misal 'passwords.token')
            \Illuminate\Support\Facades\Log::warning("❌ Password reset failed for: {$request->email}. Response code: {$response}");

            $message = trans($response);
            if ($message === $response) {
                $message = "Link reset tidak valid, hilang, atau sudah kedaluwarsa.";
            }

            // Mengembalikan response 422 JSON secara eksplisit
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'token' => [$message],
                ]
            ], 422);

        } catch (\Throwable $e) {
            // 6. TANGANI ERROR INTERNAL (Jika user NULL, error DB, dll.)

            // Cek apakah error berasal dari masalah NULL di callback
            $message = $e->getMessage();
            if (str_contains($message, 'User data is invalid or token mismatch')) {
                // Jika error adalah user NULL, kita kembalikan 422 agar frontend tahu token/email salah
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'token' => ['Token atau Email tidak cocok atau sudah kedaluwarsa.'],
                    ]
                ], 422);
            }

            // Jika error lain yang tidak terduga (misalnya masalah DB/PHP config)
            \Illuminate\Support\Facades\Log::error("🔥 Fatal Error Reset Password: {$message}", [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan teknis server yang tidak terduga. Mohon hubungi administrator.',
                'internal_error' => $e->getMessage()
            ], 500);
        }
    }
}
