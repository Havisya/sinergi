<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('Nama Perusahaan Default');
            $table->string('standard_working_hours')->default('06:00 - 00:00');
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(15);
            $table->time('daily_check_in_limit')->default('08:00:00');
            $table->timestamps();
        });

        // Seeding awal, pastikan hanya ada satu baris data pengaturan
        \DB::table('company_settings')->insert([
            'company_name' => 'PT Pertamina Patra Niaga Fuel Terminal Sei Siak',
            'standard_working_hours' => '06:00 - 00:00',
            'late_tolerance_minutes' => 15,
            'daily_check_in_limit' => '08:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
