<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mengubah kolom 'status_koreksi' di tabel 'absensi' untuk menyertakan nilai 'Diterima'.
     */
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            // PENTING: Menggunakan change() untuk memodifikasi kolom yang sudah ada.
            // Nilai ENUM yang baru: Diajukan, Diterima, Ditolak
            $table->enum('status_koreksi', ['Diajukan', 'Diterima', 'Ditolak'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     * Mengembalikan kolom 'status_koreksi' ke nilai ENUM sebelumnya.
     */
    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            // Kembalikan ke nilai ENUM sebelumnya: Diajukan, Disetujui, Ditolak
            $table->enum('status_koreksi', ['Diajukan', 'Disetujui', 'Ditolak'])->nullable()->change();
        });
    }
};
