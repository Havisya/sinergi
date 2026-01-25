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
        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id(); // Ini setara dengan BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->string('unique_key')->unique()->comment('Kunci unik gabungan (dateKey-shiftName)');
            $table->json('data')->comment('Data jadwal shift lengkap (roles, time, url, dll)');
            $table->timestamps(); // Ini membuat created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_schedules');
    }
};
