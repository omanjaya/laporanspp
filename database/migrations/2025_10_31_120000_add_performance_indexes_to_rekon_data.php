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
        Schema::table('rekon_data', function (Blueprint $table) {
            // Additional indexes for dashboard analytics performance
            $table->index(['tahun', 'bulan'], 'idx_rekon_year_month');
            $table->index(['sekolah', 'tahun', 'bulan', 'sts_bayar'], 'idx_rekon_school_year_month_status');
            $table->index(['sts_bayar', 'tahun', 'bulan'], 'idx_rekon_status_year_month');
            $table->index(['tgl_tx'], 'idx_rekon_transaction_date');
            $table->index(['kd_cab', 'tahun'], 'idx_rekon_branch_year');

            // Composite index for search operations (mirrors Excel INDEX/MATCH logic)
            $table->index(['sekolah', 'id_siswa', 'tahun', 'bulan'], 'idx_rekon_search_composite');

            // Add index for dana_masyarakat to speed up financial calculations
            $table->index(['dana_masyarakat'], 'idx_rekon_dana_masyarakat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rekon_data', function (Blueprint $table) {
            $table->dropIndex('idx_rekon_year_month');
            $table->dropIndex('idx_rekon_school_year_month_status');
            $table->dropIndex('idx_rekon_status_year_month');
            $table->dropIndex('idx_rekon_transaction_date');
            $table->dropIndex('idx_rekon_branch_year');
            $table->dropIndex('idx_rekon_search_composite');
            $table->dropIndex('idx_rekon_dana_masyarakat');
        });
    }
};