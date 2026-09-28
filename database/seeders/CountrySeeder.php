<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        Country::updateOrCreate(
            ['iso_code' => 'NGA'],
            ['name' => 'Nigeria', 'nationality' => 'Nigerian', 'is_active' => true]
        );
    }
}
