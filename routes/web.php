<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redirect;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1. Mengarahkan halaman utama patra.test langsung ke aplikasi React (Vite)
Route::get('/', function () {
    // Karena Anda sedang dalam mode pengembang, arahkan ke port Vite
    return Redirect::to('http://localhost:5173');
});

// 2. Rute untuk Reset Password yang sudah Anda buat
Route::get('/reset-password/{token}', function ($token) {

    // Mengambil URL Frontend dari .env (Pastikan di .env Backend nilainya http://localhost:5173)
    $frontendBaseUrl = config('app.url_frontend') ?? 'http://localhost:5173';

    // Redirect ke URL frontend React untuk proses reset
    return redirect("{$frontendBaseUrl}/reset-password?token={$token}");

})->name('password.reset');

// 3. Tambahkan rute fallback jika user mengakses URL yang tidak ada di Laravel
// Agar diarahkan kembali ke Frontend React
Route::fallback(function () {
    return Redirect::to('http://localhost:5173');
});
