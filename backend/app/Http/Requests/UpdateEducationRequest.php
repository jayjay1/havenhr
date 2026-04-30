<?php

namespace App\Http\Requests;

class UpdateEducationRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['sometimes', 'required', 'string', 'max:255'],
            'degree' => ['sometimes', 'required', 'string', 'max:255'],
            'field_of_study' => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['sometimes', 'required', 'date_format:Y-m'],
            'end_date' => ['nullable', 'date_format:Y-m', 'after_or_equal:start_date'],
        ];
    }
}
