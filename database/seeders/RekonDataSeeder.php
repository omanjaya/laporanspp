<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RekonData;
use Illuminate\Support\Facades\DB;

class RekonDataSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        DB::table('rekon_data')->delete();

        // Sample data dari contoh yang diberikan
        $sampleData = [
            // Data Agus Oka Bharunanta Andika Giri
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '25578',
                'nama_siswa' => 'Agus Oka Bharunanta Andika Giri',
                'alamat' => 'Denpasar',
                'kelas' => 'X.1',
                'jurusan' => 'IPA',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => null,
                'keterangan' => 'Uang Komite',
                'tahun' => 2024,
                'bulan' => 7,
                'dana_masyarakat' => '350000',
                'tgl_tx' => '2024-12-19 08:00:00',
                'tgl_tx_formatted' => '19/12/2024 08:00',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'admin',
                'sts_reversal' => 0,
                'no_bukti' => '00120241219001'
            ],
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '25578',
                'nama_siswa' => 'Agus Oka Bharunanta Andika Giri',
                'alamat' => 'Denpasar',
                'kelas' => 'X.1',
                'jurusan' => 'IPA',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => null,
                'keterangan' => 'Uang Komite',
                'tahun' => 2024,
                'bulan' => 8,
                'dana_masyarakat' => '350000',
                'tgl_tx' => '2024-12-19 08:00:00',
                'tgl_tx_formatted' => '19/12/2024 08:00',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'admin',
                'sts_reversal' => 0,
                'no_bukti' => '00120241219002'
            ],
            // Data Anak Agung Ayu Indira Maharani
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '25579',
                'nama_siswa' => 'Anak Agung Ayu Indira Maharani',
                'alamat' => 'Denpasar',
                'kelas' => 'X.1',
                'jurusan' => 'IPA',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => null,
                'keterangan' => 'Uang Komite',
                'tahun' => 2024,
                'bulan' => 7,
                'dana_masyarakat' => '350000',
                'tgl_tx' => '2024-12-05 10:00:00',
                'tgl_tx_formatted' => '05/12/2024 10:00',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'admin',
                'sts_reversal' => 0,
                'no_bukti' => '00220241205001'
            ],
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '25579',
                'nama_siswa' => 'Anak Agung Ayu Indira Maharani',
                'alamat' => 'Denpasar',
                'kelas' => 'X.1',
                'jurusan' => 'IPA',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => null,
                'keterangan' => 'Uang Komite',
                'tahun' => 2025,
                'bulan' => 1,
                'dana_masyarakat' => '350000',
                'tgl_tx' => '2025-05-16 14:00:00',
                'tgl_tx_formatted' => '16/05/2025 14:00',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'admin',
                'sts_reversal' => 0,
                'no_bukti' => '00220250516001'
            ],
            // Data Anak Agung Gde Anom Putra Asmara (LUNAS)
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '25580',
                'nama_siswa' => 'Anak Agung Gde Anom Putra Asmara',
                'alamat' => 'Denpasar',
                'kelas' => 'X.1',
                'jurusan' => 'IPA',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => null,
                'keterangan' => 'LUNAS',
                'tahun' => 2024,
                'bulan' => 7,
                'dana_masyarakat' => 'LUNAS',
                'tgl_tx' => '2024-06-01 09:00:00',
                'tgl_tx_formatted' => '01/06/2024 09:00',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'admin',
                'sts_reversal' => 0,
                'no_bukti' => 'LUNAS2024001'
            ],
            // Data Ayu Anindya Pradnya
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '25582',
                'nama_siswa' => 'Ayu Anindya Pradnya',
                'alamat' => 'Denpasar',
                'kelas' => 'X.1',
                'jurusan' => 'IPA',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => null,
                'keterangan' => 'Uang Komite',
                'tahun' => 2024,
                'bulan' => 7,
                'dana_masyarakat' => '350000',
                'tgl_tx' => '2024-12-11 11:00:00',
                'tgl_tx_formatted' => '11/12/2024 11:00',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'admin',
                'sts_reversal' => 0,
                'no_bukti' => '00520241211001'
            ],
            // Data Evan Aditya Dharmawan (pembayaran bertahap)
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '25583',
                'nama_siswa' => 'Evan Aditya Dharmawan',
                'alamat' => 'Denpasar',
                'kelas' => 'X.1',
                'jurusan' => 'IPA',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => null,
                'keterangan' => 'Uang Komite',
                'tahun' => 2024,
                'bulan' => 7,
                'dana_masyarakat' => '350000',
                'tgl_tx' => '2024-09-10 13:00:00',
                'tgl_tx_formatted' => '10/09/2024 13:00',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'admin',
                'sts_reversal' => 0,
                'no_bukti' => '00620240910001'
            ],
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '25583',
                'nama_siswa' => 'Evan Aditya Dharmawan',
                'alamat' => 'Denpasar',
                'kelas' => 'X.1',
                'jurusan' => 'IPA',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => null,
                'keterangan' => 'Uang Komite',
                'tahun' => 2024,
                'bulan' => 8,
                'dana_masyarakat' => '350000',
                'tgl_tx' => '2024-09-10 13:00:00',
                'tgl_tx_formatted' => '10/09/2024 13:00',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'admin',
                'sts_reversal' => 0,
                'no_bukti' => '00620240910002'
            ],
            // Data dari contoh awal
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '24908',
                'nama_siswa' => 'IDA BAGUS BAYUJIWADHITA MANUAB',
                'alamat' => '-',
                'kelas' => 'XI.',
                'jurusan' => '12',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => '-',
                'keterangan' => '-',
                'tahun' => 2024,
                'bulan' => 6,
                'dana_masyarakat' => '350000',
                'tgl_tx' => '2024-07-01 07:42:00',
                'tgl_tx_formatted' => '01/07/2024 7:42',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'igate_pac',
                'sts_reversal' => 0,
                'no_bukti' => '4171505446'
            ],
            [
                'sekolah' => 'SMAN_1_DENPASAR',
                'id_siswa' => '23988',
                'nama_siswa' => 'DEWA GEDE NATIH ANAND JAGADHIT',
                'alamat' => '-',
                'kelas' => 'XII.',
                'jurusan' => 'MIPA1',
                'jum_tagihan' => 350000,
                'biaya_adm' => 0,
                'tagihan_lain' => 0,
                'ket_tagihan_lain' => '-',
                'keterangan' => '-',
                'tahun' => 2024,
                'bulan' => 1,
                'dana_masyarakat' => '350000',
                'tgl_tx' => '2024-07-02 15:29:00',
                'tgl_tx_formatted' => '02/07/2024 15:29',
                'sts_bayar' => 1,
                'kd_cab' => 'EB',
                'kd_user' => 'igate_pac',
                'sts_reversal' => 0,
                'no_bukti' => '4171523573'
            ]
        ];

        // Insert data
        foreach ($sampleData as $data) {
            RekonData::create($data);
        }

        $this->command->info('✅ Sample RekonData created successfully!');
        $this->command->info('📊 Total records: ' . count($sampleData));
    }
}