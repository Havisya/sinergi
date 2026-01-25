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
        Schema::table('attendance_requests', function (Blueprint $table) {
            // Menambahkan kolom baru 'quota_deducted' dengan nilai default FALSE
            $table->boolean('quota_deducted')->default(false)->after('status');
            // Catatan: Jika kolom 'reviewer_notes' belum ada, tambahkan baris di bawah ini
            // $table->text('reviewer_notes')->nullable()->after('quota_deducted');
        });
    }

    /**
     * Balikkan migrasi.
     */
    public function down(): void
    {
        Schema::table('attendance_requests', function (Blueprint $table) {
            $table->dropColumn('quota_deducted');
        });
    }
};
