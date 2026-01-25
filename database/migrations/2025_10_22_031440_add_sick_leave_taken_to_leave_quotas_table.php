<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        Schema::table('leave_quotas', function (Blueprint $table) {
            // Menambahkan kolom baru 'sick_leave_taken' dengan nilai default 0
            // Pemeriksaan untuk menghindari error jika kolom sudah ada
            if (!Schema::hasColumn('leave_quotas', 'sick_leave_taken')) {
                $table->integer('sick_leave_taken')->default(0)->after('leave_taken');
            }
        });
    }

    /**
     * Balikkan migrasi.
     */
    public function down(): void
    {
        Schema::table('leave_quotas', function (Blueprint $table) {
            $table->dropColumn('sick_leave_taken');
        });
    }
};
