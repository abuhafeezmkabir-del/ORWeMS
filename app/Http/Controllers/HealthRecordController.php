<?php

namespace App\Http\Controllers;

use App\Enums\SecurityEvent;
use App\Http\Requests\CloseHealthRecordRequest;
use App\Http\Requests\StoreHealthRecordRequest;
use App\Http\Requests\UpdateHealthRecordRequest;
use App\Models\Beneficiary;
use App\Models\HealthConcern;
use App\Models\HealthRecord;
use App\Support\Audit\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Casework-level health record management (Phase 6B).
 *
 * The same authorization chain as EducationController, link by link: route
 * middleware (permission) → policy (permission AND beneficiary scope) → form
 * request → model guards + database constraints. What is different is only
 * the subject and its privacy posture: health is the most sensitive content
 * ORWeMS holds, so the controller NEVER loads health rows for an operator
 * the policy refuses — the case-file section is gated by `viewFor` before a
 * single row is read, and an unauthorized case file renders the neutral
 * "Health information is restricted." line with no counts, no summaries, no
 * referral detail.
 *
 * There is deliberately no destroy — health history is evidence. Closing (a
 * dated, reasoned, audited state change) is the terminal action.
 *
 * AUDIT PRIVACY: the audit contexts below carry identifiers only
 * (beneficiary_ref, health_record_id, concern id, dates, follow-up state).
 * The summary, referral destination and notes NEVER enter the audit trail:
 * audit_logs is a security trail, not a second copy of the child's health.
 * HealthRecordViewed is recorded on the record page; the case-file section
 * render is not (it would write an audit row on every case-file view, which
 * is surveillance of the operator, not accountability for the record).
 */
class HealthRecordController extends Controller
{
    /**
     * The health records for one child, newest first.
     */
    public function index(Request $request, Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->exists, 404);
        $this->authorize('viewFor', [HealthRecord::class, $beneficiary]);

        $records = $beneficiary->healthRecords()
            ->with(['healthConcern:id,code,name,sort_order'])
            ->reorder()
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->get();

        return view('health.index', [
            'beneficiary' => $beneficiary,
            'records' => $records,
            'openRecords' => $records->filter(fn (HealthRecord $r): bool => $r->isOpen()),
        ]);
    }

    /**
     * A single record. Also the audited view event: opening this page reads
     * sensitive content, so the read itself is recorded.
     */
    public function show(Beneficiary $beneficiary, HealthRecord $healthRecord): View
    {
        $this->assertRecordBelongsTo($beneficiary, $healthRecord);
        $this->authorize('view', $healthRecord);

        $healthRecord->loadMissing(['healthConcern:id,code,name']);

        Auditor::record(
            SecurityEvent::HealthRecordViewed,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'health_record_id' => $healthRecord->getKey(),
            ],
        );

        return view('health.show', [
            'beneficiary' => $beneficiary,
            'record' => $healthRecord,
        ]);
    }

    /**
     * The new-record form.
     */
    public function create(Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->exists, 404);
        $this->authorize('create', [HealthRecord::class, $beneficiary]);

        return view('health.create', [
            'beneficiary' => $beneficiary,
            ...$this->formLookups(),
        ]);
    }

    /**
     * Record a new health record. Refuses (with a field error, via the form
     * request) when the child already has an OPEN record for the same
     * concern.
     */
    public function store(StoreHealthRecordRequest $request, Beneficiary $beneficiary): RedirectResponse
    {
        $this->authorize('create', [HealthRecord::class, $beneficiary]);

        $record = $beneficiary->healthRecords()->create($request->validated());

        Auditor::record(
            SecurityEvent::HealthRecordCreated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'health_record_id' => $record->getKey(),
                'health_concern_id' => $record->health_concern_id,
                'record_date' => $record->record_date?->toDateString(),
                'follow_up_status' => $record->follow_up_status,
            ],
        );

        return redirect()
            ->route('beneficiaries.health.show', [$beneficiary, $record])
            ->with('success', 'Health record recorded.');
    }

    /**
     * The amendment form. A closed record is refused by the policy with a
     * 403 — amending history is not a workflow.
     */
    public function edit(Beneficiary $beneficiary, HealthRecord $healthRecord): View
    {
        $this->assertRecordBelongsTo($beneficiary, $healthRecord);
        $this->authorize('update', $healthRecord);

        $healthRecord->loadMissing(['healthConcern:id,code,name']);

        return view('health.edit', [
            'beneficiary' => $beneficiary,
            'record' => $healthRecord,
            ...$this->formLookups(),
        ]);
    }

    /**
     * Amend the OPEN record. The concern may change, but the changed concern
     * must not collide with another open record: the no-exact-open-duplicate
     * rule binds amendments, enforced here in the transaction the model guard
     * and the database constraint back.
     */
    public function update(UpdateHealthRecordRequest $request, Beneficiary $beneficiary, HealthRecord $healthRecord): RedirectResponse
    {
        $this->assertRecordBelongsTo($beneficiary, $healthRecord);
        $this->authorize('update', $healthRecord);

        $data = $request->validated();

        $collision = $beneficiary->healthRecords()
            ->where('health_concern_id', $data['health_concern_id'])
            ->whereNull('end_date')
            ->whereKeyNot($healthRecord->getKey())
            ->exists();

        if ($collision) {
            return back()
                ->withErrors(['health_concern_id' => 'Another open record for this concern already exists. Close it first, or keep this record on its current concern.'])
                ->withInput();
        }

        $healthRecord->update($data);

        Auditor::record(
            SecurityEvent::HealthRecordUpdated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'health_record_id' => $healthRecord->getKey(),
                'health_concern_id' => $healthRecord->health_concern_id,
                'follow_up_status' => $healthRecord->follow_up_status,
            ],
        );

        return redirect()
            ->route('beneficiaries.health.show', [$beneficiary, $healthRecord])
            ->with('success', 'Health record updated.');
    }

    /**
     * The closure confirmation. Closing is consequential and gets a confirm
     * screen + POST, exactly like archiving a beneficiary.
     */
    public function close(Beneficiary $beneficiary, HealthRecord $healthRecord): View
    {
        $this->assertRecordBelongsTo($beneficiary, $healthRecord);
        $this->authorize('close', $healthRecord);

        $healthRecord->loadMissing(['healthConcern:id,code,name']);

        return view('health.close', [
            'beneficiary' => $beneficiary,
            'record' => $healthRecord,
        ]);
    }

    /**
     * Perform the closure. The row remains — closed, dated, explained — as
     * the history entry for this episode of the child's health tracking.
     */
    public function confirmClose(CloseHealthRecordRequest $request, Beneficiary $beneficiary, HealthRecord $healthRecord): RedirectResponse
    {
        $this->assertRecordBelongsTo($beneficiary, $healthRecord);
        $this->authorize('close', $healthRecord);

        $data = $request->validated();

        $healthRecord->end_date = Carbon::parse($data['end_date']);
        $healthRecord->closure_reason = $data['closure_reason'] ?? null;
        $healthRecord->save();

        Auditor::record(
            SecurityEvent::HealthRecordClosed,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'health_record_id' => $healthRecord->getKey(),
                'end_date' => $healthRecord->end_date?->toDateString(),
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Health record closed. It remains in the history.');
    }

    /**
     * A record resolved under a beneficiary URL must BE that beneficiary's —
     * anything else is a 404, because a cross-record id in a URL is a mistake
     * (or an attempt), and answering it with 403 would confirm the id exists.
     */
    private function assertRecordBelongsTo(Beneficiary $beneficiary, HealthRecord $healthRecord): void
    {
        abort_unless(
            $beneficiary->exists
            && (int) $healthRecord->beneficiary_id === (int) $beneficiary->getKey(),
            404
        );
    }

    /**
     * Lookups for the health forms: only ACTIVE concerns. The concern is a
     * global reference row, not a scoped record, so — unlike the placement
     * forms' household/caregiver lookups — there is no geographic expansion
     * here; scope is enforced on the beneficiary itself by the policy.
     *
     * @return array<string, mixed>
     */
    private function formLookups(): array
    {
        return [
            'concerns' => HealthConcern::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'sort_order']),
        ];
    }
}
