<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Amending the CURRENT education enrolment (Phase 6A).
 *
 * Deliberately narrower than the store rules: NO end_date and NO closure_reason
 * fields exist here, so an amendment cannot close an enrolment — closing is
 * education.close, its own permission, its own screen, its own audit event.
 * Only the enrolment's substance (level, institution, class/year, start date,
 * notes) is amenable.
 *
 * end_date and closure_reason are also not fillable on the model, so even a
 * tampered request that smuggled the fields past validation could not mass-
 * assign them; two independent refusals, as usual.
 */
class UpdateEducationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'education_level_id' => [
                'required', 'integer',
                Rule::exists('education_levels', 'id')->where('is_active', true),
            ],
            'institution_id' => [
                'nullable', 'integer',
                Rule::exists('institutions', 'id')->where('is_active', true),
            ],
            'institution_name' => ['required', 'string', 'max:255'],
            'class_or_year' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
