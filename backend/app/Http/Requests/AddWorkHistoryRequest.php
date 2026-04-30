<?php

namespace App\Http\Requests;

class AddWorkHistoryRequest extends BaseFormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m'],
            'end_date' => ['nullable', 'date_format:Y-m', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
