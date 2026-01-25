<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah kolom func dari ENUM menjadi STRING (VARCHAR) yang fleksibel.
     */
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            // Mengubah tipe kolom func menjadi string dengan panjang 255
            $table->string('func', 255)->nullable()->change();
        });
    }

    /**
     * Rollback: Mengembalikan ke ENUM lama jika diperlukan.
     */
    public function down(): void
    {
        // Mendefinisikan ulang ENUM dengan nilai-nilai lama (penting untuk rollback)
        Schema::table('karyawan', function (Blueprint $table) {
            $table->enum('func', [
                'Fuel Terminal Manager',
                'Receiving, Storage & Distribution',
                'Maintenance, Planning & Serv',
                'HSSE',
                'Quality & Quantity',
                'Sales Service & General Affairs',
                'Loading Master'
            ])->nullable()->change();
        });
    }
};
