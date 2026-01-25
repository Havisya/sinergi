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
    Schema::table('schedules', function (Blueprint $table) {
        // Menambahkan kolom schedule_name bertipe string, boleh kosong (nullable)
        $table->string('schedule_name', 255)->nullable()->after('schedule');
    });
}

public function down(): void
{
    Schema::table('schedules', function (Blueprint $table) {
        $table->dropColumn('schedule_name');
    });
}};
