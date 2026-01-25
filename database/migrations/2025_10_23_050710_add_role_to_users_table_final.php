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
        // PENTING: Tambahkan kondisi ifExists() untuk mencegah error jika kolom sudah ada
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                // Tambahkan kolom 'role' dengan nilai default 'user'
                $table->string('role')->default('user')->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom 'role' saat rollback
            if (Schema::hasColumn('users', 'role')) {
                 $table->dropColumn('role');
            }
        });
    }
};
