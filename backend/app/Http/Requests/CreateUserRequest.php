<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;
use Illuminate\Validation\Rule;

class CreateUserRequest extends BaseFormRequest
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
            'email' => ['required', 'string', 'email:rfc'],
            'password' => ['required', 'string', new StrongPassword],
            'role' => [
                'required',
                'string',
                Rule::in(['Owner', 'Admin', 'Recruiter', 'Hiring_Manager', 'Viewer']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'The role must be one of: Owner, Admin, Recruiter, Hiring_Manager, Viewer.',
        ];
    }
}
