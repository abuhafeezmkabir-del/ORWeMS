<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Seeder;

class PilotGeographySeeder extends Seeder
{
    public function run(): void
    {
        $nigeria = Country::where('iso_code', 'NGA')->firstOrFail();

        // Only levels verified from the project brief are seeded here.
        // LGAs, wards and communities must come from verified project data
        // and are intentionally left for a later verified import.
        $states = [
            ['name' => 'Niger State', 'code' => 'NG-NI'],
            ['name' => 'Nasarawa State', 'code' => 'NG-NA'],
            ['name' => 'Federal Capital Territory', 'code' => 'NG-FC'],
        ];

        foreach ($states as $state) {
            State::updateOrCreate(
                ['country_id' => $nigeria->id, 'name' => $state['name']],
                ['code' => $state['code'], 'type_label' => 'State', 'is_active' => true]
            );
        }
    }
}
