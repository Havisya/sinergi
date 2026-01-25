<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungan_dinas', function (Blueprint $table) {
            // Tambahkan kolom 'nopek'
            if (!Schema::hasColumn('kunjungan_dinas', 'nopek')) {
                $table->string('nopek')->after('id');
            }

            // Tambahkan kolom 'name' (jika masih hilang)
            if (!Schema::hasColumn('kunjungan_dinas', 'name')) {
                $table->string('name')->after('nopek');
            }

            // Tambahkan kolom 'position' (jika masih hilang)
            if (!Schema::hasColumn('kunjungan_dinas', 'position')) {
                $table->string('position')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan_dinas', function (Blueprint $table) {
            if (Schema::hasColumn('kunjungan_dinas', 'nopek')) {
                $table->dropColumn('nopek');
            }
            if (Schema::hasColumn('kunjungan_dinas', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('kunjungan_dinas', 'position')) {
                $table->dropColumn('position');
            }
        });
    }
};
