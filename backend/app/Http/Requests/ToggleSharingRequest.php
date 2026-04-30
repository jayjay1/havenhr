<?php

namespace App\Http\Requests;

class ToggleSharingRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enable' => ['required', 'boolean'],
            'show_contact' => ['sometimes', 'boolean'],
        ];
    }
}
