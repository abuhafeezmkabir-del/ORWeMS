<?php

namespace Database\Seeders;

use App\Enums\ProgrammeStatus;
use App\Models\Programme;
use Illuminate\Database\Seeder;

class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        Programme::updateOrCreate(
            ['code' => 'WICHOV-PILOT'],
            [
                'name' => 'WICHOV Pilot Programme',
                'description' => 'Initial WICHOV pilot programme for orphans and vulnerable children.',
                'status' => ProgrammeStatus::Active->value,
            ]
        );
    }
}
