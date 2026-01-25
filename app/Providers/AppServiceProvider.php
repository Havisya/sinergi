<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // // FIX UTAMA: Memaksa API merespons dengan JSON 401 Unauthorized
        // // ketika otentikasi gagal, mencegah respons HTML/redirect.

        // // Menggunakan mekanisme extend Guard Sanctum untuk memastikan respons JSON
        // Auth::extend('sanctum', function ($app, $name, array $config) {
        //     return tap(Auth::guard('sanctum'), function ($guard) {
        //         // Terapkan respons JSON untuk semua request ke /api/*
        //         $guard->shouldRespond(function (Request $request) {
        //             // Cek apakah request berasal dari endpoint API
        //             if ($request->is('api/*')) {
        //                 // Jika unauthenticated, kembalikan respons JSON 401
        //                 return Response::json(['message' => 'Unauthenticated.'], 401);
        //             }
        //         });
        //     });
        // });

        // Catatan: Jika ini tidak berhasil, masalah ada di file 'app/Exceptions/Handler.php'
    }
}
