<?php

namespace App\Http\Requests;

class CreateResumeRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'template_slug' => ['required', 'string', 'in:clean,modern,professional,creative'],
        ];
    }
}
