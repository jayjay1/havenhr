<?php

namespace App\Http\Requests;

class ScheduleInterviewRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_application_id' => 'required|uuid',
            'interviewer_id' => 'required|uuid',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'required|integer|in:30,45,60,90',
            'interview_type' => 'required|string|in:phone,video,in_person',
            'location' => 'required|string|max:500',
            'notes' => 'sometimes|nullable|string|max:2000',
        ];
    }
}
