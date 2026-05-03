<?php

namespace App\Http\Requests;

class UpdateInterviewKitRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'questions' => 'sometimes|array|min:1',
            'questions.*.text' => 'required_with:questions|string|max:1000',
            'questions.*.category' => 'required_with:questions|string|in:technical,behavioral,cultural,experience',
            'questions.*.sort_order' => 'required_with:questions|integer|min:0',
            'questions.*.scoring_rubric' => 'sometimes|nullable|string|max:2000',
        ];
    }
}
