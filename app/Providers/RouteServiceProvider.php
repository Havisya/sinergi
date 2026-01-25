<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Tentukan namespace default untuk controller aplikasi.
     * (Bergantung versi Laravel, ini mungkin tidak ada)
     */
    protected $namespace = 'App\\Http\\Controllers';

    /**
     * Tentukan batas kecepatan untuk API.
     */
    public const HOME = '/home';

    /**
     * Muat file rute aplikasi.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {

            // Pengaturan Rute API (CRUSIAL UNTUK /API/KARYAWAN)
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php')); // <-- PASTIKAN INI ADA

            // Pengaturan Rute Web (TIDAK ADA /API)
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Konfigurasi pembatasan kecepatan untuk aplikasi.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
