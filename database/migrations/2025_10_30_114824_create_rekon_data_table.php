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
        Schema::create('rekon_data', function (Blueprint $table) {
            $table->id();
            // Data Sekolah & Siswa
            $table->string('sekolah');           // SMAN_1_DENPASAR
            $table->string('id_siswa');          // 24908, 23988
            $table->string('nama_siswa');        // IDA BAGUS BAYUJIWADHITA MANUAB
            $table->string('alamat')->nullable();
            $table->string('kelas');             // XI., XII.
            $table->string('jurusan');           // 12, MIPA1, MIPA6

            // Data Tagihan
            $table->integer('jum_tagihan');      // 350000
            $table->integer('biaya_adm')->default(0);
            $table->integer('tagihan_lain')->default(0);
            $table->string('ket_tagihan_lain')->nullable();
            $table->string('keterangan')->nullable();

            // Periode (Kriteria penting untuk pencarian)
            $table->integer('tahun');            // 2024, 2023
            $table->integer('bulan');            // 1-12
            $table->string('dana_masyarakat');   // 350000

            // Data Transaksi
            $table->dateTime('tgl_tx');
            $table->string('tgl_tx_formatted');  // 01/07/2024 7:42
            $table->integer('sts_bayar');        // 1=aktif
            $table->string('kd_cab');            // EB, TLR
            $table->string('kd_user');           // igate_pac, webteller
            $table->integer('sts_reversal')->default(0);
            $table->string('no_bukti');          // 4171505446

            $table->timestamps();

            // Index untuk optimasi pencarian multi-kriteria
            $table->index(['sekolah', 'tahun', 'bulan']);
            $table->index(['id_siswa', 'tahun', 'bulan']);
            $table->index(['nama_siswa']);
            $table->index(['no_bukti']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rekon_data');
    }
};
