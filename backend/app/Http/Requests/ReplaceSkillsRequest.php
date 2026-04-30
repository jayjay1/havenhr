<?php

namespace App\Http\Requests;

class ReplaceSkillsRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'skills' => ['present', 'array'],
            'skills.*.name' => ['required', 'string', 'max:255'],
            'skills.*.category' => ['required', 'string', 'in:technical,soft'],
        ];
    }
}
