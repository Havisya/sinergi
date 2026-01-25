<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungan_dinas', function (Blueprint $table) {

            // 1. Tambahkan kolom 'department' (yang menyebabkan migrasi sebelumnya gagal)
            if (!Schema::hasColumn('kunjungan_dinas', 'department')) {
                $table->string('department')->nullable();
            }

            // 2. Tambahkan kolom 'date' (yang menyebabkan error 500 di Controller)
            if (!Schema::hasColumn('kunjungan_dinas', 'date')) {
                $table->date('date')->nullable();
            }

            // Ulangi untuk kolom penting lain dari Model jika Anda curiga masih ada yang hilang (misal: 'destination', 'purpose', 'status')
            if (!Schema::hasColumn('kunjungan_dinas', 'destination')) {
                $table->string('destination')->nullable();
            }
            if (!Schema::hasColumn('kunjungan_dinas', 'purpose')) {
                $table->text('purpose')->nullable();
            }
            if (!Schema::hasColumn('kunjungan_dinas', 'status')) {
                 $table->enum('status', ['present', 'late', 'absent'])->default('present')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan_dinas', function (Blueprint $table) {
            if (Schema::hasColumn('kunjungan_dinas', 'department')) {
                $table->dropColumn('department');
            }
            if (Schema::hasColumn('kunjungan_dinas', 'date')) {
                $table->dropColumn('date');
            }
            if (Schema::hasColumn('kunjungan_dinas', 'destination')) {
                $table->dropColumn('destination');
            }
            if (Schema::hasColumn('kunjungan_dinas', 'purpose')) {
                $table->dropColumn('purpose');
            }
            if (Schema::hasColumn('kunjungan_dinas', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
