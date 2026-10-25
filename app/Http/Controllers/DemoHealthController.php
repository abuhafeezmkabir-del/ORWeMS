<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * DemoHealthController
 *
 * Synthetic health record interface for the demonstration area. All records
 * are fictional. No database queries are performed; all "actions" are
 * simulated and return a notice that nothing was persisted.
 */
class DemoHealthController extends Controller
{
    public function index(Request $request, $beneficiary_ref)
    {
        $records = [
            [
                'date' => '2026-03-10',
                'concern' => 'Nutritional Assessment',
                'summary' => 'Child assessed for growth monitoring. Weight within expected range for age. Dietary advice provided to caregiver.',
                'status' => 'Open',
                'follow_up' => '2026-04-10',
            ],
            [
                'date' => '2026-02-20',
                'concern' => 'Immunisation Review',
                'summary' => 'Routine immunisation check completed. All scheduled vaccines up to date. No adverse reactions reported.',
                'status' => 'Closed',
                'follow_up' => '2027-02-20',
            ],
        ];

        $beneficiaries = [
            'BEN-2026-001' => ['ref' => 'BEN-2026-001', 'name' => 'Amina K.', 'age' => 9],
        ];

        $beneficiary = $beneficiaries[$beneficiary_ref] ?? null;

        if (! $beneficiary) {
            abort(404);
        }

        return view('demo.health.index', compact('beneficiary', 'records'));
    }

    public function create(Request $request, $beneficiary_ref)
    {
        $concerns = [
            'Nutritional Assessment',
            'Immunisation Review',
            'Growth Monitoring',
            'Vision Screening',
            'General Wellness Check',
        ];

        $beneficiaries = [
            'BEN-2026-001' => ['ref' => 'BEN-2026-001', 'name' => 'Amina K.', 'age' => 9],
        ];

        $beneficiary = $beneficiaries[$beneficiary_ref] ?? null;

        if (! $beneficiary) {
            abort(404);
        }

        return view('demo.health.create', compact('beneficiary', 'concerns'));
    }

    public function store(Request $request, $beneficiary_ref)
    {
        // Simulated: validate but do NOT persist anything.
        $request->validate([
            'concern' => 'required|string|max:255',
            'record_date' => 'required|date|before_or_equal:today',
            'summary' => 'required|string|max:1000',
            'follow_up' => 'nullable|date|after:record_date',
        ]);

        return back()
            ->with('status', 'demo-action-simulated')
            ->with('message', 'Action simulated: health record was NOT saved to the database. This is a demonstration environment.');
    }

    public function close(Request $request, $beneficiary_ref)
    {
        $records = [
            ['id' => 1, 'date' => '2026-03-10', 'concern' => 'Nutritional Assessment', 'status' => 'Open'],
            ['id' => 2, 'date' => '2026-02-20', 'concern' => 'Immunisation Review', 'status' => 'Closed'],
        ];

        $beneficiaries = [
            'BEN-2026-001' => ['ref' => 'BEN-2026-001', 'name' => 'Amina K.', 'age' => 9],
        ];

        $beneficiary = $beneficiaries[$beneficiary_ref] ?? null;

        if (! $beneficiary) {
            abort(404);
        }

        return view('demo.close', compact('beneficiary', 'records'));
    }

    public function confirmClose(Request $request, $beneficiary_ref)
    {
        // Simulated: no deletion, no status mutation in database.
        $request->validate([
            'record_id' => 'required|integer',
            'closure_reason' => 'required|string|max:500',
        ]);

        return back()
            ->with('status', 'demo-action-simulated')
            ->with('message', 'Close simulated: record history preserved; no record was deleted or modified in the database.');
    }
}