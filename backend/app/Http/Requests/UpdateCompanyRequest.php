<?php

namespace App\Http\Requests;

/**
 * Form request for updating company settings.
 *
 * Validates the company name field for the PUT /api/v1/company endpoint.
 */
class UpdateCompanyRequest extends BaseFormRequest
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
        ];
    }
}
