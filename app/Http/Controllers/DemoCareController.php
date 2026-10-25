<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * DemoCareController
 *
 * Synthetic care / placement history and relationship management for the
 * demonstration area. No database queries are performed.
 */
class DemoCareController extends Controller
{
    public function index(Request $request, $beneficiary_ref)
    {
        $careRecords = [
            [
                'date' => '2026-01-15',
                'type' => 'Initial Placement',
                'placement' => 'Foster Care — Northern Zone',
                'caregiver' => 'Mrs. Esther K.',
                'worker' => 'Grace N.',
                'notes' => 'Child settled well into foster home. Initial health screening completed.',
            ],
            [
                'date' => '2026-03-02',
                'type' => 'Case Review',
                'placement' => 'Foster Care — Northern Zone',
                'caregiver' => 'Mrs. Esther K.',
                'worker' => 'Grace N.',
                'notes' => 'Quarterly review: attendance at school improved. Next review in 90 days.',
            ],
            [
                'date' => '2026-05-10',
                'type' => 'Health Follow-up',
                'placement' => 'Foster Care — Northern Zone',
                'caregiver' => 'Mrs. Esther K.',
                'worker' => 'Grace N.',
                'notes' => 'Nutritional assessment follow-up scheduled. Child responding to dietary plan.',
            ],
        ];

        $beneficiaries = [
            'BEN-2026-001' => ['ref' => 'BEN-2026-001', 'name' => 'Amina K.', 'age' => 9],
        ];

        $beneficiary = $beneficiaries[$beneficiary_ref] ?? null;

        if (! $beneficiary) {
            abort(404);
        }

        return view('demo.care.index', compact('beneficiary', 'careRecords'));
    }

    public function relationships(Request $request, $beneficiary_ref)
    {
        $relationships = [
            [
                'name' => 'Mrs. Esther K.',
                'relation' => 'Foster Caregiver',
                'contact' => '0800-123-4567',
                'address' => 'Northern Zone',
                'status' => 'Active',
            ],
            [
                'name' => 'Grace N.',
                'relation' => 'Case Worker',
                'contact' => '0800-765-4321',
                'address' => 'Regional Office',
                'status' => 'Active',
            ],
        ];

        $beneficiaries = [
            'BEN-2026-001' => ['ref' => 'BEN-2026-001', 'name' => 'Amina K.', 'age' => 9],
        ];

        $beneficiary = $beneficiaries[$beneficiary_ref] ?? null;

        if (! $beneficiary) {
            abort(404);
        }

        return view('demo.relationships.index', compact('beneficiary', 'relationships'));
    }

    public function storeRelationship(Request $request, $beneficiary_ref)
    {
        // Simulated: no data is persisted.
        return back()
            ->with('status', 'demo-action-simulated')
            ->with('message', 'Action simulated: relationship entry was not saved to the database.');
    }
}