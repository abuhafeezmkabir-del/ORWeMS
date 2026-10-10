<?php

namespace App\Http\Controllers;

use App\Enums\SecurityEvent;
use App\Http\Requests\ChangeEducationRequest;
use App\Http\Requests\CloseEducationRequest;
use App\Http\Requests\StoreEducationRequest;
use App\Http\Requests\UpdateEducationRequest;
use App\Models\Beneficiary;
use App\Models\EducationEnrolment;
use App\Models\EducationLevel;
use App\Models\Institution;
use App\Support\Audit\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Education history management (Phase 6A).
 *
 * The same authorization chain as PlacementController, link by link: route
 * middleware (permission) → policy (permission AND beneficiary scope) → form
 * request → model guards + database constraints. What is different is only the
 * subject: where a placement asks "where does this child LIVE", an enrolment
 * asks "where do they STUDY" — and, crucially, "current" is derived from
 * end_date IS NULL with no stored status to drift.
 *
 * storeChange is the one compound action: closing the current enrolment and
 * opening the next happen in ONE transaction, because a child is enrolled in
 * one place at a time, and a workflow that could half-commit would record the
 * opposite. There is deliberately no destroy — education history is evidence.
 */
class EducationController extends Controller
{
    /**
     * The full education history for one child, newest first.
     */
    public function index(Request $request, Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->exists, 404);
        $this->authorize('viewFor', [EducationEnrolment::class, $beneficiary]);

        // The relation bakes an ASC start_date order for history rendering;
        // the listing itself wants newest first, so reset before re-sorting.
        $enrolments = $beneficiary->enrolments()
            ->with([
                'educationLevel:id,code,name,sort_order',
                'institution:id,code,name',
            ])
            ->reorder()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return view('education.index', [
            'beneficiary' => $beneficiary,
            'enrolments' => $enrolments,
            'currentEnrolment' => $enrolments->first(fn (EducationEnrolment $e): bool => $e->isCurrent()),
        ]);
    }

    /**
     * The new-enrolment form.
     */
    public function create(Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->exists, 404);
        $this->authorize('create', [EducationEnrolment::class, $beneficiary]);

        return view('education.create', [
            'beneficiary' => $beneficiary,
            ...$this->formLookups(),
        ]);
    }

    /**
     * Record a new enrolment. Refuses (with a field error, via the form
     * request) when the child already has a current one.
     */
    public function store(StoreEducationRequest $request, Beneficiary $beneficiary): RedirectResponse
    {
        $this->authorize('create', [EducationEnrolment::class, $beneficiary]);

        $enrolment = $beneficiary->enrolments()->create($request->validated());

        Auditor::record(
            SecurityEvent::EducationEnrolmentCreated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'education_enrolment_id' => $enrolment->getKey(),
                'education_level' => $enrolment->educationLevel?->code,
                'start_date' => $enrolment->start_date?->toDateString(),
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Education enrolment recorded.');
    }

    /**
     * The change form: close the current enrolment and open the next, shown
     * together so the operator sees both halves they are committing.
     */
    public function change(Request $request, Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->exists, 404);

        $current = $this->currentEnrolment($beneficiary);

        if ($current === null) {
            abort(404);
        }

        // Changing is closing AND creating: both capabilities, or neither.
        $this->authorize('close', $current);
        $this->authorize('create', [EducationEnrolment::class, $beneficiary]);

        return view('education.change', [
            'beneficiary' => $beneficiary,
            'currentEnrolment' => $current,
            ...$this->formLookups(),
        ]);
    }

    /**
     * The change transaction: old enrolment closed, new one opened, one
     * commit, two audit events.
     */
    public function storeChange(ChangeEducationRequest $request, Beneficiary $beneficiary): RedirectResponse
    {
        $current = $this->currentEnrolment($beneficiary);

        if ($current === null) {
            abort(404);
        }

        $this->authorize('close', $current);
        $this->authorize('create', [EducationEnrolment::class, $beneficiary]);

        $data = $request->validated();

        DB::transaction(function () use ($current, $beneficiary, $data): void {
            $current->end_date = Carbon::parse($data['current_end_date']);
            $current->closure_reason = $data['current_closure_reason'] ?? null;
            $current->save();

            $beneficiary->enrolments()->create([
                'education_level_id' => $data['education_level_id'],
                'institution_id' => $data['institution_id'] ?? null,
                'institution_name' => $data['institution_name'],
                'class_or_year' => $data['class_or_year'] ?? null,
                'start_date' => $data['start_date'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        Auditor::record(
            SecurityEvent::EducationEnrolmentClosed,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'education_enrolment_id' => $current->getKey(),
                'end_date' => $current->end_date?->toDateString(),
                'via' => 'change',
            ],
        );
        Auditor::record(
            SecurityEvent::EducationEnrolmentCreated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'via' => 'change',
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Enrolment changed: the previous enrolment is closed and the new one recorded.');
    }

    /**
     * The amendment form for a CURRENT enrolment.
     */
    public function edit(Beneficiary $beneficiary, EducationEnrolment $enrolment): View
    {
        $this->assertEnrolmentBelongsTo($beneficiary, $enrolment);
        $this->authorize('update', $enrolment);

        return view('education.edit', [
            'beneficiary' => $beneficiary,
            'enrolment' => $enrolment,
            ...$this->formLookups(),
        ]);
    }

    /**
     * Amend a CURRENT enrolment. Closed enrolments are refused by the policy —
     * history does not get rewritten through an edit form.
     */
    public function update(UpdateEducationRequest $request, Beneficiary $beneficiary, EducationEnrolment $enrolment): RedirectResponse
    {
        $this->assertEnrolmentBelongsTo($beneficiary, $enrolment);
        $this->authorize('update', $enrolment);

        $enrolment->update($request->validated());

        Auditor::record(
            SecurityEvent::EducationEnrolmentUpdated,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'education_enrolment_id' => $enrolment->getKey(),
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Enrolment updated.');
    }


    /**
     * The closure confirmation. Closing is consequential and gets a confirm
     * screen + POST, exactly like archiving a beneficiary.
     */
    public function close(Beneficiary $beneficiary, EducationEnrolment $enrolment): View
    {
        $this->assertEnrolmentBelongsTo($beneficiary, $enrolment);
        $this->authorize('close', $enrolment);

        return view('education.close', [
            'beneficiary' => $beneficiary,
            'enrolment' => $enrolment,
        ]);
    }

    /**
     * Perform the closure. The row remains — closed, dated, explained — as the
     * history entry for this stage of the child's education.
     */
    public function confirmClose(CloseEducationRequest $request, Beneficiary $beneficiary, EducationEnrolment $enrolment): RedirectResponse
    {
        $this->assertEnrolmentBelongsTo($beneficiary, $enrolment);
        $this->authorize('close', $enrolment);

        $data = $request->validated();

        $enrolment->end_date = Carbon::parse($data['end_date']);
        $enrolment->closure_reason = $data['closure_reason'] ?? null;
        $enrolment->save();

        Auditor::record(
            SecurityEvent::EducationEnrolmentClosed,
            actor: Auditor::currentActor(),
            subject: $beneficiary->fresh(),
            context: [
                'beneficiary_ref' => $beneficiary->beneficiary_ref,
                'education_enrolment_id' => $enrolment->getKey(),
                'end_date' => $enrolment->end_date?->toDateString(),
            ],
        );

        return redirect()
            ->route('beneficiaries.show', $beneficiary)
            ->with('success', 'Enrolment closed. It remains in the history.');
    }

    /**
     * An enrolment resolved under a beneficiary URL must BE that beneficiary's
     * — anything else is a 404, because a cross-record id in a URL is a
     * mistake (or an attempt), and answering it with 403 would confirm the id
     * exists.
     */
    private function assertEnrolmentBelongsTo(Beneficiary $beneficiary, EducationEnrolment $enrolment): void
    {
        abort_unless(
            $beneficiary->exists
            && (int) $enrolment->beneficiary_id === (int) $beneficiary->getKey(),
            404
        );
    }

    private function currentEnrolment(Beneficiary $beneficiary): ?EducationEnrolment
    {
        return $beneficiary->enrolments()
            ->whereNull('end_date')
            ->with(['educationLevel:id,code,name', 'institution:id,code,name'])
            ->first();
    }

    /**
     * Lookups for the education forms: only ACTIVE levels and institutions. The
     * education level and institution are global reference rows, not scoped
     * records, so — unlike the placement forms' household/caregiver lookups —
     * there is no geographic expansion here; scope is enforced on the
     * beneficiary itself by the policy.
     *
     * @return array<string, mixed>
     */
    private function formLookups(): array
    {
        return [
            'levels' => EducationLevel::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'sort_order']),
            'institutions' => Institution::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ];
    }
}

