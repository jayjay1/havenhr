<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseFormRequest extends FormRequest
{
    /**
     * Fields whose values should be redacted in error responses.
     *
     * @var list<string>
     */
    protected array $sensitiveFields = [
        'password',
        'password_hash',
        'owner_password',
        'password_confirmation',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Configure the validator instance to reject unknown fields.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->rejectUnknownFields($validator);
        });
    }

    /**
     * Check for fields not defined in rules() and add errors for them.
     */
    protected function rejectUnknownFields(Validator $validator): void
    {
        $allowedFields = array_keys($this->rules());
        $submittedFields = array_keys($this->all());

        $unknownFields = array_diff($submittedFields, $allowedFields);

        foreach ($unknownFields as $field) {
            $validator->errors()->add(
                $field,
                "The field {$field} is not allowed."
            );
        }
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        $fields = [];

        foreach ($errors as $fieldName => $messages) {
            $fields[$fieldName] = [
                'value' => $this->getFieldValueForResponse($fieldName),
                'messages' => $messages,
            ];
        }

        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => [
                        'fields' => $fields,
                    ],
                ],
            ], 422)
        );
    }

    /**
     * Get the field value for the error response, redacting sensitive fields.
     */
    protected function getFieldValueForResponse(string $fieldName): mixed
    {
        if (in_array($fieldName, $this->sensitiveFields, true)) {
            return '[REDACTED]';
        }

        return $this->input($fieldName);
    }
}
