<?php

namespace App\Http\Requests;

class AIOptimizeATSRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_description' => ['required', 'string', 'max:5000'],
            'resume_content' => ['required', 'array'],
        ];
    }
}
