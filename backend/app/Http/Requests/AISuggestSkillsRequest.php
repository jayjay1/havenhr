<?php

namespace App\Http\Requests;

class AISuggestSkillsRequest extends BaseFormRequest
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
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'existing_skills' => ['sometimes', 'nullable', 'array'],
            'existing_skills.*' => ['string', 'max:255'],
        ];
    }
}
