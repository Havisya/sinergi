<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        Schema::table('kunjungan_dinas', function (Blueprint $table) {
            // Kita harus pastikan semua kolom dari $fillable ada di tabel.
            // Gunakan conditional addColumn jika Anda tidak yakin.

            // Kolom-kolom ini SANGAT KRITIS harus ada di tabel 'kunjungan_dinas'
            if (!Schema::hasColumn('kunjungan_dinas', 'nopek')) {
                $table->string('nopek')->after('id');
            }
            if (!Schema::hasColumn('kunjungan_dinas', 'name')) {
                $table->string('name')->after('nopek');
            }
            if (!Schema::hasColumn('kunjungan_dinas', 'position')) {
                $table->string('position')->after('name');
            }
            // Kolom 'department' (sesuai 'func' dari frontend)
            if (!Schema::hasColumn('kunjungan_dinas', 'department')) {
                $table->string('department')->nullable()->after('position');
            }
            // Kolom 'date' (digunakan untuk orderBy di Controller)
            if (!Schema::hasColumn('kunjungan_dinas', 'date')) {
                $table->date('date')->after('department');
            }
            if (!Schema::hasColumn('kunjungan_dinas', 'destination')) {
                $table->string('destination')->after('date');
            }
            if (!Schema::hasColumn('kunjungan_dinas', 'purpose')) {
                $table->text('purpose')->after('destination');
            }
            if (!Schema::hasColumn('kunjungan_dinas', 'status')) {
                $table->enum('status', ['present', 'late', 'absent'])->default('present')->after('purpose');
            }

            // Kolom timestamps (created_at, updated_at) diasumsikan sudah ada.
        });
    }

    /**
     * Balikkan (rollback) migrasi.
     */
    public function down(): void
    {
        Schema::table('kunjungan_dinas', function (Blueprint $table) {
            // Hapus semua kolom yang ditambahkan di fungsi up (jika ada)
            $table->dropColumn(['nopek', 'name', 'position', 'department', 'date', 'destination', 'purpose', 'status']);
        });
    }
};
