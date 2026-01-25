<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KaryawanController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/reset-password/{token}', function ($token) {

    // Gunakan APP_URL yang baru dari .env
    $frontendBaseUrl = env('APP_URL');

    // Redirect ke URL frontend tanpa port ganda
    return redirect("{$frontendBaseUrl}/reset-password?token={$token}");

})->name('password.reset');


