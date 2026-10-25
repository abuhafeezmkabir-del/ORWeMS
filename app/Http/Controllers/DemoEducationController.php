<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * DemoEducationController
 *
 * Synthetic education history for the demonstration area. No database
 * queries are performed.
 */
class DemoEducationController extends Controller
{
    public function index(Request $request, $beneficiary_ref)
    {
        $enrolments = [
            [
                'school' => 'Northern Community Primary',
                'level' => 'Primary 4',
                'programme' => 'Standard Curriculum',
                'enrolment_date' => '2026-01-15',
                'status' => 'Current',
                'term' => 'Second Term',
                'attendance' => '92%',
                'teacher' => 'Mr. Adebayo',
            ],
            [
                'school' => 'Community Nursery School',
                'level' => 'Nursery 3',
                'programme' => 'Early Childhood',
                'enrolment_date' => '2024-09-10',
                'status' => 'Completed',
                'term' => 'Third Term',
                'attendance' => '88%',
                'teacher' => 'Mrs. Okafor',
            ],
        ];

        $beneficiaries = [
            'BEN-2026-001' => ['ref' => 'BEN-2026-001', 'name' => 'Amina K.', 'age' => 9],
        ];

        $beneficiary = $beneficiaries[$beneficiary_ref] ?? null;

        if (! $beneficiary) {
            abort(404);
        }

        return view('demo.education.index', compact('beneficiary', 'enrolments'));
    }
}