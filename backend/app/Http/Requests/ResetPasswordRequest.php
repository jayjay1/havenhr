<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;

class ResetPasswordRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'password' => ['required', 'string', new StrongPassword],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ];
    }
}
