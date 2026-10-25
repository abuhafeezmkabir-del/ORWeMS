<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * DemoDashboardController
 *
 * Synthetic dashboard for the demonstration area. All data is fictional;
 * no database queries are performed.
 */
class DemoDashboardController extends Controller
{
    public function index(Request $request)
    {
        $beneficiaries = [
            [
                'ref' => 'BEN-2026-001',
                'name' => 'Amina K.',
                'age' => 9,
                'location' => 'Northern Zone',
                'status' => 'Active',
                'open_concerns' => 2,
            ],
            [
                'ref' => 'BEN-2026-002',
                'name' => 'Samuel O.',
                'age' => 14,
                'location' => 'Central District',
                'status' => 'Active',
                'open_concerns' => 1,
            ],
            [
                'ref' => 'BEN-2026-003',
                'name' => 'Fatima A.',
                'age' => 6,
                'location' => 'Southern Region',
                'status' => 'Follow-up',
                'open_concerns' => 3,
            ],
            [
                'ref' => 'BEN-2026-004',
                'name' => 'Daniel M.',
                'age' => 11,
                'location' => 'Northern Zone',
                'status' => 'Active',
                'open_concerns' => 0,
            ],
        ];

        return view('demo.dashboard', compact('beneficiaries'));
    }
}