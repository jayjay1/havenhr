<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;

/**
 * Test-only Form Request for validating owner_password redaction behavior.
 * Used exclusively in tests — not for production endpoints.
 */
class TestOwnerPasswordFormRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'owner_password' => ['required', 'string', new StrongPassword],
        ];
    }
}
