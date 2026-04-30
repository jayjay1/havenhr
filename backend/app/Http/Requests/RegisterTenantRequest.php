<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterTenantRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_email_domain' => [
                'required',
                'string',
                'unique:companies,email_domain',
                'regex:/^(?!-)([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/',
            ],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email:rfc'],
            'owner_password' => ['required', 'string', new StrongPassword],
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * If the only validation failure is a duplicate email domain, return 409.
     * Otherwise, fall through to the standard 422 handling.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();

        // Check if the only error is the domain uniqueness violation
        if ($this->isDomainConflictOnly($errors)) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'DOMAIN_ALREADY_EXISTS',
                        'message' => 'The company email domain is already registered.',
                    ],
                ], 409)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Determine if the validation errors consist solely of a domain uniqueness violation.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    protected function isDomainConflictOnly(array $errors): bool
    {
        if (count($errors) !== 1 || !isset($errors['company_email_domain'])) {
            return false;
        }

        $messages = $errors['company_email_domain'];

        return count($messages) === 1
            && $messages[0] === $this->messages()['company_email_domain.unique'];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_email_domain.regex' => 'The company email domain must be a valid domain format (e.g., example.com).',
            'company_email_domain.unique' => 'The company email domain is already registered.',
        ];
    }
}
