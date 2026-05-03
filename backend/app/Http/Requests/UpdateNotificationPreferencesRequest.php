<?php

namespace App\Http\Requests;

class UpdateNotificationPreferencesRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage_change_emails' => 'sometimes|boolean',
            'application_confirmation_emails' => 'sometimes|boolean',
        ];
    }
}
