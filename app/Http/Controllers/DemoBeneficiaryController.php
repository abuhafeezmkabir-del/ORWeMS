<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * DemoBeneficiaryController
 *
 * Synthetic beneficiary profile for the demonstration area. No database
 * queries are performed; all records are fictional.
 */
class DemoBeneficiaryController extends Controller
{
    public function show(Request $request, $beneficiary_ref)
    {
        $beneficiaries = $this->sampleBeneficiaries();

        $beneficiary = collect($beneficiaries)->firstWhere('ref', $beneficiary_ref);

        if (! $beneficiary) {
            abort(404);
        }

        return view('demo.beneficiary.profile', compact('beneficiary'));
    }

    private function sampleBeneficiaries(): array
    {
        return [
            [
                'ref' => 'BEN-2026-001',
                'name' => 'Amina K.',
                'age' => 9,
                'gender' => 'Female',
                'location' => 'Northern Zone',
                'status' => 'Active',
                'caseworker' => 'Grace N.',
                'enrolled_at' => '2026-01-15',
                'health_concerns' => [
                    ['type' => 'Nutritional Assessment', 'date' => '2026-03-10', 'status' => 'Follow-up scheduled'],
                    ['type' => 'Immunisation Review', 'date' => '2026-02-20', 'status' => 'Up to date'],
                ],
                'education_level' => 'Primary 4',
                'school' => 'Northern Community Primary',
                'placement_type' => 'Foster Care',
                'caregiver' => 'Mrs. Esther K.',
            ],
            [
                'ref' => 'BEN-2026-002',
                'name' => 'Samuel O.',
                'age' => 14,
                'gender' => 'Male',
                'location' => 'Central District',
                'status' => 'Active',
                'caseworker' => 'David P.',
                'enrolled_at' => '2025-11-08',
                'health_concerns' => [
                    ['type' => 'General Wellness Check', 'date' => '2026-01-25', 'status' => 'Clear'],
                ],
                'education_level' => 'Junior Secondary 2',
                'school' => 'Central District Secondary',
                'placement_type' => 'Kinship Care',
                'caregiver' => 'Aunt Mary O.',
            ],
            [
                'ref' => 'BEN-2026-003',
                'name' => 'Fatima A.',
                'age' => 6,
                'gender' => 'Female',
                'location' => 'Southern Region',
                'status' => 'Follow-up',
                'caseworker' => 'Sarah J.',
                'enrolled_at' => '2026-04-02',
                'health_concerns' => [
                    ['type' => 'Immunisation Follow-up', 'date' => '2026-04-15', 'status' => 'Pending'],
                    ['type' => 'Growth Monitoring', 'date' => '2026-03-28', 'status' => 'On track'],
                    ['type' => 'Vision Screening', 'date' => '2026-03-28', 'status' => 'Referral recommended'],
                ],
                'education_level' => 'Primary 1',
                'school' => 'Southern Region Primary',
                'placement_type' => 'Family Reunification',
                'caregiver' => 'Mr. and Mrs. A. Ibrahim',
            ],
            [
                'ref' => 'BEN-2026-004',
                'name' => 'Daniel M.',
                'age' => 11,
                'gender' => 'Male',
                'location' => 'Northern Zone',
                'status' => 'Active',
                'caseworker' => 'Grace N.',
                'enrolled_at' => '2025-09-22',
                'health_concerns' => [],
                'education_level' => 'Primary 6',
                'school' => 'Northern Community Primary',
                'placement_type' => 'Group Home',
                'caregiver' => 'Staff rotation',
            ],
        ];
    }
}