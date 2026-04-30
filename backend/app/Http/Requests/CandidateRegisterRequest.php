<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;

class CandidateRegisterRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'unique:candidates,email'],
            'password' => ['required', 'string', new StrongPassword],
        ];
    }
}
