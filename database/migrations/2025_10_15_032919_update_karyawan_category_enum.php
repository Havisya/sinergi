<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            // Hapus kolom lama
            $table->dropColumn('category');
        });

        Schema::table('karyawan', function (Blueprint $table) {
            // Tambahkan kembali dengan nilai yang diperluas
            // Anda bisa menambahkan 'PWT', 'Kontrak', dll.
            $table->enum('category', ['PWTT', 'PWT', 'TAD', 'Security'])
                  ->nullable()
                  ->after('position');
        });
    }

    public function down(): void
    {
        // Untuk rollback, kembalikan ke enum awal (atau yang Anda inginkan)
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->enum('category', ['PWTT', 'PWT', 'TAD', 'Security'])
                  ->nullable()
                  ->after('position');
        });
    }
};
