<?php

namespace Database\Factories;

use App\Models\RekonData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RekonData>
 */
class RekonDataFactory extends Factory
{
    protected $model = RekonData::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schools = ['SMAN_1_DENPASAR', 'SMAN_2_DENPASAR', 'SMAK_1_DENPASAR'];
        $kelas = ['X', 'XI', 'XII'];
        $jurusan = ['MIPA1', 'MIPA2', 'MIPA3', 'IPS1', 'IPS2', 'Bahasa'];

        return [
            'sekolah' => $this->faker->randomElement($schools),
            'id_siswa' => $this->faker->unique()->numberBetween(10000, 99999),
            'nama_siswa' => $this->faker->name(),
            'alamat' => $this->faker->address(),
            'kelas' => $this->faker->randomElement($kelas),
            'jurusan' => $this->faker->randomElement($jurusan),
            'jum_tagihan' => $this->faker->numberBetween(300000, 500000),
            'biaya_adm' => $this->faker->numberBetween(0, 10000),
            'tagihan_lain' => $this->faker->numberBetween(0, 50000),
            'ket_tagihan_lain' => $this->faker->optional()->sentence(),
            'keterangan' => $this->faker->optional()->sentence(),
            'tahun' => $this->faker->numberBetween(2023, 2024),
            'bulan' => $this->faker->numberBetween(1, 12),
            'dana_masyarakat' => (string) $this->faker->numberBetween(300000, 500000),
            'tgl_tx' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'tgl_tx_formatted' => fn($attributes) => now()->format('d/m/Y H:i'),
            'sts_bayar' => 1,
            'kd_cab' => $this->faker->randomElement(['EB', 'TLR', 'DP']),
            'kd_user' => $this->faker->randomElement(['igate_pac', 'webteller', 'mobile']),
            'sts_reversal' => 0,
            'no_bukti' => $this->faker->unique()->numerify('##########'),
        ];
    }

    /**
     * Create data for specific school
     */
    public function forSchool(string $school): static
    {
        return $this->state(fn (array $attributes) => [
            'sekolah' => $school,
        ]);
    }

    /**
     * Create data for specific period
     */
    public function forPeriod(int $tahun, int $bulan): static
    {
        return $this->state(fn (array $attributes) => [
            'tahun' => $tahun,
            'bulan' => $bulan,
        ]);
    }

    /**
     * Create data with payment status
     */
    public function withPaymentStatus(int $status): static
    {
        return $this->state(fn (array $attributes) => [
            'sts_bayar' => $status,
        ]);
    }

    /**
     * Create data with reversal status
     */
    public function withReversalStatus(int $status): static
    {
        return $this->state(fn (array $attributes) => [
            'sts_reversal' => $status,
        ]);
    }

    /**
     * Create data for specific student
     */
    public function forStudent(string $idSiswa): static
    {
        return $this->state(fn (array $attributes) => [
            'id_siswa' => $idSiswa,
        ]);
    }

    /**
     * Create data with invalid dana_masyarakat (for testing validation)
     */
    public function withInvalidDanaMasyarakat(): static
    {
        return $this->state(fn (array $attributes) => [
            'dana_masyarakat' => $this->faker->word(),
        ]);
    }

    /**
     * Create data with empty dana_masyarakat
     */
    public function withEmptyDanaMasyarakat(): static
    {
        return $this->state(fn (array $attributes) => [
            'dana_masyarakat' => '',
        ]);
    }

    /**
     * Create bulk data for testing performance
     */
    public function createBulk(int $count, array $overrides = []): \Illuminate\Database\Eloquent\Collection
    {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $data[] = array_merge($this->definition(), $overrides);
        }

        return $this->model::insert($data)
            ? $this->model::limit($count)->orderBy('id', 'desc')->get()
            : collect();
    }
}