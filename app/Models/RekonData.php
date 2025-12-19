<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RekonData extends Model
{
    protected $table = 'rekon_data';

    protected $fillable = [
        'sekolah', 'id_siswa', 'nama_siswa', 'alamat', 'kelas', 'jurusan',
        'jum_tagihan', 'biaya_adm', 'tagihan_lain', 'ket_tagihan_lain', 'keterangan',
        'tahun', 'bulan', 'dana_masyarakat',
        'tgl_tx', 'tgl_tx_formatted', 'sts_bayar', 'kd_cab', 'kd_user', 'sts_reversal', 'no_bukti'
    ];

    protected $casts = [
        'jum_tagihan' => 'integer',
        'biaya_adm' => 'integer',
        'tagihan_lain' => 'integer',
        'tahun' => 'integer',
        'bulan' => 'integer',
        'tgl_tx' => 'datetime',
        'sts_bayar' => 'integer',
        'sts_reversal' => 'integer',
    ];

    /**
     * Scope untuk pencarian berdasarkan 3 kriteria (mirip formula Excel)
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $sekolah
     * @param int $tahun
     * @param int $bulan
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByKriteria($query, $sekolah, $tahun, $bulan)
    {
        return $query->where('sekolah', $sekolah)
                    ->where('tahun', $tahun)
                    ->where('bulan', $bulan);
    }

    /**
     * Get nilai dana masyarakat (mirip kolom P di Excel)
     * @param string $sekolah
     * @param int $tahun
     * @param int $bulan
     * @return string|null
     */
    public static function getDanaMasyarakat($sekolah, $tahun, $bulan)
    {
        $data = static::byKriteria($sekolah, $tahun, $bulan)->first();
        return $data ? $data->dana_masyarakat : '-';
    }
}
