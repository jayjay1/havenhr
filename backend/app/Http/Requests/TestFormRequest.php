<?php

namespace App\Http\Requests;

/**
 * Test-only Form Request for validating BaseFormRequest behavior.
 * Used exclusively in tests — not for production endpoints.
 */
class TestFormRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc'],
        ];
    }
}
