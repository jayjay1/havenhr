<?php

namespace App\Http\Requests;

class UpdatePersonalInfoRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ];
    }
}
