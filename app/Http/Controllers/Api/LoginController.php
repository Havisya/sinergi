<?php
// app/Http/Controllers/Api/LoginController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class LoginController extends Controller
{
    /**
     * 🔐 Menangani login user dan membuat token Sanctum.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            Log::warning("❌ Login gagal: Kredensial tidak cocok untuk {$request->email}");

            throw ValidationException::withMessages([
                'email' => ['Kredensial yang Anda berikan tidak cocok.'],
            ]);
        }

        $user = Auth::user();

        // FIX: Hapus semua token lama dan buat token baru
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        try {
            $user->last_login_at = Carbon::now();
            $user->save();
            Log::info("✅ User {$user->email} berhasil login.");
        } catch (\Throwable $e) {
            Log::error("⚠️ Gagal menyimpan last_login_at untuk {$user->email}: {$e->getMessage()}");
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil.',
            'token' => $token, // Wajib dikembalikan ke frontend
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'last_login_at' => $user->last_login_at
                    ? $user->last_login_at->translatedFormat('d F Y, H:i') . ' WIB'
                    : null,
            ],
        ], 200);
    }

    /**
     * 🚪 Logout user dan cabut token Sanctum.
     */
    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        Log::info('User logged out.');

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil logout. Sesi dihapus dari sisi klien.',
        ]);
    }
}
