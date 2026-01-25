<?php

namespace Database\Migrations; // Hapus namespace jika file migrasi Anda di folder default

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Tambahkan Log untuk debugging

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mengubah skema kolom tanggal dari 'date' tunggal menjadi 'date_from' dan 'date_to'
     * dan memastikan kode aman dari error Duplicate Column.
     */
    public function up(): void
    {
        // Pengecekan kolom yang diubah harus dilakukan di dalam blok Schema::table
        Schema::table('attendance_requests', function (Blueprint $table) {

            // --- 1. Tambahkan kolom baru (date_from dan date_to) sebagai nullable ---

            // Perbaikan: Tambahkan pengecekan kolom sebelum mencoba menambahkannya.
            if (!Schema::hasColumn('attendance_requests', 'date_from')) {
                $table->date('date_from')->after('employee_name')->nullable();
            }

            if (!Schema::hasColumn('attendance_requests', 'date_to')) {
                $table->date('date_to')->after('date_from')->nullable();
            }
        });

        // --- 2. Transisi Data (Dilakukan di luar Schema::table) ---

        // Hanya pindahkan data jika kolom 'date' lama masih ada
        if (Schema::hasColumn('attendance_requests', 'date')) {
            try {
                // Salin data dari kolom 'date' tunggal ke kedua kolom baru
                // Ini menangani kasus data lama agar tidak NULL setelah drop kolom 'date'
                DB::statement('UPDATE attendance_requests SET date_from = `date`, date_to = `date` WHERE `date` IS NOT NULL');
                Log::info('Migration: Data date lama berhasil disalin ke date_from dan date_to.');
            } catch (\Exception $e) {
                // Tangani error jika terjadi saat update data (misal: kolom hilang)
                Log::error('Migration Data Copy Failed: ' . $e->getMessage());
            }
        }


        // --- 3. Hapus kolom 'date' yang usang ---
        Schema::table('attendance_requests', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_requests', 'date')) {
                $table->dropColumn('date');
            }
        });

        // --- 4. Ubah kolom 'date_from' dan 'date_to' menjadi NOT NULL ---
        // Peringatan: Operasi change() di Laravel memerlukan package 'doctrine/dbal'
        // dan ini harus dilakukan setelah data disalin atau dipastikan kolom tidak NULL.

        Schema::table('attendance_requests', function (Blueprint $table) {
            // Kita hanya mengubah kolom yang sudah ada. Jika kolom yang disalin datanya
            // masih ada yang NULL, operasi ini akan gagal jika DB tidak mendukungnya.
            if (Schema::hasColumn('attendance_requests', 'date_from')) {
                 $table->date('date_from')->nullable(false)->change();
            }
             if (Schema::hasColumn('attendance_requests', 'date_to')) {
                $table->date('date_to')->nullable(false)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     * Membalikkan skema: menghapus date_from/date_to dan mengembalikan 'date'.
     */
    public function down(): void
    {
        Schema::table('attendance_requests', function (Blueprint $table) {
            // Kembalikan kolom 'date'
            if (!Schema::hasColumn('attendance_requests', 'date')) {
                $table->date('date')->after('employee_name')->nullable();
            }

            // Hapus kolom baru (dengan pengecekan)
            if (Schema::hasColumn('attendance_requests', 'date_from')) {
                $table->dropColumn('date_from');
            }
            if (Schema::hasColumn('attendance_requests', 'date_to')) {
                $table->dropColumn('date_to');
            }
        });

        // CATATAN: Operasi down() ini tidak mengembalikan data lama ke kolom 'date'.
        // Untuk mengembalikan data, perlu operasi DB::statement tambahan.
    }
};
