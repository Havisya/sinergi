<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel ini menyimpan Roster/Jadwal Kerja per Periode
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('shift_type', ['Pagi', 'Sore', 'Malam', 'Non-Shift', 'Libur']);
            $table->enum('work_system', ['Shift', 'Non-Shift']);
            $table->string('supervisor', 255);
            $table->string('schedule', 255);
            $table->json('employee_nipeks'); // Menyimpan daftar NIP sebagai JSON
            $table->enum('status', ['Aktif', 'Mendatang', 'Selesai'])->default('Mendatang');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
