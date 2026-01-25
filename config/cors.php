<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

   'paths' => ['api/*', 'login', 'logout', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins (Asal yang Diizinkan)
    |--------------------------------------------------------------------------
    | Kami mengizinkan semua port localhost yang mungkin digunakan oleh React/Vite.
    */

    'allowed_origins' => [
        // Asal Frontend Pengembangan
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5174',
        'http://localhost:5175',
        'http://127.0.0.1:5175',
        'http://localhost:5176',
        'http://127.0.0.1:5176',

        // Origin lama/default (jika masih digunakan)
        'http://localhost:3000',
        'http://127.0.0.1:3000',

        // Origin Backend Laravel (jika backend memanggil dirinya sendiri)
        // Umumnya tidak perlu disertakan, tapi tidak ada salahnya jika disimpan.
        'http://127.0.0.1:8000',
        'http://localhost:8000',

        // Tambahkan pola wildcard untuk pengembangan yang lebih fleksibel (Opsi)
        // 'http://localhost:*',
        // 'http://127.0.0.1:*',
    ],

    // Hapus duplikasi 'http://127.0.0.1:5174' yang kedua.
    // Pastikan tidak ada karakter tersembunyi (seperti spasi di akhir baris yang dicantumkan sebagai komentar).

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers (Header yang Diizinkan)
    |--------------------------------------------------------------------------
    */
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
