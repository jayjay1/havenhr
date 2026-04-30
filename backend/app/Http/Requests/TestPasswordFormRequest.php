<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;

/**
 * Test-only Form Request for validating password redaction behavior.
 * Used exclusively in tests — not for production endpoints.
 */
class TestPasswordFormRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string', new StrongPassword, 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
