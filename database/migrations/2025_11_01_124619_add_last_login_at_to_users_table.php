<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // PENTING: Tambahkan pengecekan kolom sebelum mencoba menambahkannya (Fix: Error Duplicate Column)
            if (!Schema::hasColumn('users', 'last_login_at')) {
                // Gunakan timestamp() untuk kolom tanggal-waktu
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // PENTING: Tambahkan pengecekan kolom sebelum mencoba menghapusnya (Fix: Error Column Not Found saat rollback)
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
        });
    }
};
