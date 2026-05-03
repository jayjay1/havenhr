<?php

namespace App\Http\Requests;

class CreateInterviewKitRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'questions' => 'required|array|min:1',
            'questions.*.text' => 'required|string|max:1000',
            'questions.*.category' => 'required|string|in:technical,behavioral,cultural,experience',
            'questions.*.sort_order' => 'required|integer|min:0',
            'questions.*.scoring_rubric' => 'sometimes|nullable|string|max:2000',
        ];
    }
}
