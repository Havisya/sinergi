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
        // Menggunakan nama tabel baru: 'activity_logs'
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // ID pengguna (tanpa foreign key yang bermasalah)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->index('user_id');

            $table->string('user_name', 150);
            $table->string('category', 50)->index(); // Untuk filter (Sistem, Keamanan, dll.)
            $table->string('action', 100);
            $table->text('details');
            $table->ipAddress('ip_address')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
