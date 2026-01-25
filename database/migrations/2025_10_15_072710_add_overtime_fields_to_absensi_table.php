<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            // Menandakan apakah absensi ini terkait dengan lembur
            $table->boolean('is_overtime')->default(false)->after('status_koreksi');

            // Menyimpan ID jadwal lembur yang terkait (jika ada)
            // Sesuaikan tipe data 'overtime_id' dengan tipe primary key tabel 'overtimes'
            $table->unsignedBigInteger('overtime_id')->nullable()->after('is_overtime');

            // Menyimpan jenis lembur: Normal, Lembur Terjadwal, atau Lembur Otomatis
            $table->string('overtime_type', 50)->default('Normal')->after('overtime_id');

            // Opsional: Foreign Key Constraint (jika diperlukan)
            // $table->foreign('overtime_id')->references('id')->on('overtimes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropColumn('overtime_type');
            $table->dropColumn('overtime_id');
            $table->dropColumn('is_overtime');
        });
    }
};
