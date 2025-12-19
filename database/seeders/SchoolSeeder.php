<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\School;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        School::query()->delete();

        $schools = [
            [
                'name' => 'SMAN_1_DENPASAR',
                'display_name' => 'SMA Negeri 1 Denpasar',
                'address' => 'Jl. PB. Sudirman No. 1 Denpasar, Bali',
                'phone' => '(0361) 223362',
                'email' => 'sman1denpasar@gmail.com',
                'is_active' => true,
            ],
            [
                'name' => 'SMAN_2_DENPASAR',
                'display_name' => 'SMA Negeri 2 Denpasar',
                'address' => 'Jl. Tukad Yeh Aya No. 15 Denpasar, Bali',
                'phone' => '(0361) 235765',
                'email' => 'sman2denpasar@gmail.com',
                'is_active' => true,
            ],
            [
                'name' => 'SMAK_1_DENPASAR',
                'display_name' => 'SMA Katolik 1 Denpasar',
                'address' => 'Jl. Merdeka No. 1 Denpasar, Bali',
                'phone' => '(0361) 224876',
                'email' => 'smak1denpasar@gmail.com',
                'is_active' => true,
            ],
            [
                'name' => 'SMAN_1_BADUNG',
                'display_name' => 'SMA Negeri 1 Badung',
                'address' => 'Jl. Raya Sempidi, Badung, Bali',
                'phone' => '(0361) 901234',
                'email' => 'sman1badung@gmail.com',
                'is_active' => true,
            ],
            [
                'name' => 'SMAN_1_GIANYAR',
                'display_name' => 'SMA Negeri 1 Gianyar',
                'address' => 'Jl. Raya Gianyar, Bali',
                'phone' => '(0361) 943456',
                'email' => 'sman1gianyar@gmail.com',
                'is_active' => true,
            ]
        ];

        foreach ($schools as $school) {
            School::create($school);
        }

        $this->command->info('✅ Sample schools created successfully!');
        $this->command->info('📊 Total schools: ' . count($schools));
    }
}