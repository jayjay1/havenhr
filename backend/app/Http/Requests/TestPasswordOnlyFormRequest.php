<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;

/**
 * Test-only Form Request for validating StrongPassword rule behavior.
 * Used exclusively in tests — not for production endpoints.
 */
class TestPasswordOnlyFormRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', new StrongPassword],
        ];
    }
}
