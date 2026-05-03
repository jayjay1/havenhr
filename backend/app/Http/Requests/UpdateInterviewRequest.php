<?php

namespace App\Http\Requests;

class UpdateInterviewRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => 'sometimes|date|after:now',
            'duration_minutes' => 'sometimes|integer|in:30,45,60,90',
            'interview_type' => 'sometimes|string|in:phone,video,in_person',
            'location' => 'sometimes|string|max:500',
            'interviewer_id' => 'sometimes|uuid',
            'notes' => 'sometimes|nullable|string|max:2000',
            'status' => 'sometimes|string|in:scheduled,completed,cancelled,no_show',
        ];
    }
}
