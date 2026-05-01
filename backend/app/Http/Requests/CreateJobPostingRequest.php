<?php

namespace App\Http\Requests;

class CreateJobPostingRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:10000',
            'location' => 'required|string|max:255',
            'employment_type' => 'required|string|in:full-time,part-time,contract,internship',
            'department' => 'nullable|string|max:255',
            'salary_min' => 'nullable|integer|min:0',
            'salary_max' => 'nullable|integer|min:0',
            'salary_currency' => 'nullable|string|max:3',
            'requirements' => 'nullable|string|max:5000',
            'benefits' => 'nullable|string|max:5000',
            'remote_status' => 'nullable|string|in:remote,on-site,hybrid',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        parent::withValidator($validator);

        $validator->after(function ($validator) {
            $salaryMin = $this->input('salary_min');
            $salaryMax = $this->input('salary_max');

            if ($salaryMin !== null && $salaryMax !== null && $salaryMin > $salaryMax) {
                $validator->errors()->add(
                    'salary_max',
                    'The salary max must be greater than or equal to salary min.'
                );
            }
        });
    }
}
