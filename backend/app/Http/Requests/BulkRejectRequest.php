<?php

namespace App\Http\Requests;

class BulkRejectRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_ids' => 'required|array|min:1|max:100',
            'application_ids.*' => 'required|uuid',
        ];
    }
}
