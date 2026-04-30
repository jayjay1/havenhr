<?php

namespace App\Http\Requests;

class AIGenerateSummaryRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_title' => ['required', 'string', 'max:255'],
            'years_experience' => ['required', 'integer', 'min:0', 'max:100'],
            'work_history' => ['sometimes', 'nullable', 'array'],
            'work_history.*' => ['string', 'max:5000'],
        ];
    }
}
