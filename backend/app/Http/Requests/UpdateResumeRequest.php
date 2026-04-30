<?php

namespace App\Http\Requests;

class UpdateResumeRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'template_slug' => ['sometimes', 'string', 'in:clean,modern,professional,creative'],
            'content' => ['sometimes', 'array'],
            'content.personal_info' => ['sometimes', 'array'],
            'content.personal_info.name' => ['sometimes', 'string', 'max:255'],
            'content.personal_info.email' => ['sometimes', 'string', 'max:255'],
            'content.personal_info.phone' => ['sometimes', 'string', 'max:255'],
            'content.personal_info.location' => ['sometimes', 'string', 'max:255'],
            'content.personal_info.linkedin_url' => ['sometimes', 'string', 'max:500'],
            'content.personal_info.portfolio_url' => ['sometimes', 'string', 'max:500'],
            'content.summary' => ['sometimes', 'string'],
            'content.work_experience' => ['sometimes', 'array'],
            'content.work_experience.*.job_title' => ['required_with:content.work_experience', 'string', 'max:255'],
            'content.work_experience.*.company_name' => ['required_with:content.work_experience', 'string', 'max:255'],
            'content.work_experience.*.start_date' => ['sometimes', 'nullable', 'string'],
            'content.work_experience.*.end_date' => ['sometimes', 'nullable', 'string'],
            'content.work_experience.*.bullets' => ['sometimes', 'array'],
            'content.work_experience.*.bullets.*' => ['string'],
            'content.education' => ['sometimes', 'array'],
            'content.education.*.institution_name' => ['required_with:content.education', 'string', 'max:255'],
            'content.education.*.degree' => ['required_with:content.education', 'string', 'max:255'],
            'content.education.*.field_of_study' => ['required_with:content.education', 'string', 'max:255'],
            'content.education.*.start_date' => ['sometimes', 'nullable', 'string'],
            'content.education.*.end_date' => ['sometimes', 'nullable', 'string'],
            'content.skills' => ['sometimes', 'array'],
            'content.skills.*' => ['string', 'max:255'],
            'change_summary' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
