<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_quotas', function (Blueprint $table) {
            $table->string('nopek')->primary();
            $table->integer('permit_quota')->default(5); // Default 5 hari
            $table->integer('permit_taken')->default(0);
            $table->foreign('nopek')->references('nopek')->on('karyawan')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_quotas');
    }
};
