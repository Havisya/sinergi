<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_quotas', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel karyawan menggunakan kolom nopek sebagai foreign key
            // Pastikan kolom 'nopek' di tabel 'karyawan' diindeks (index/unique)
            $table->string('nopek')->unique();
            $table->foreign('nopek')->references('nopek')->on('karyawan')->onDelete('cascade');

            $table->integer('annual_quota')->default(12)->comment('Jatah cuti tahunan total');
            $table->integer('leave_taken')->default(0)->comment('Total cuti yang sudah diambil');
            $table->integer('sick_leave_taken')->default(0)->comment('Total cuti sakit yang sudah diambil');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_quotas');
    }
};
