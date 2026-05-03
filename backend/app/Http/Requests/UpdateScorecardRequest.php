<?php

namespace App\Http\Requests;

class UpdateScorecardRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overall_rating' => 'sometimes|integer|min:1|max:5',
            'overall_recommendation' => 'sometimes|string|in:strong_no,no,mixed,yes,strong_yes',
            'notes' => 'sometimes|nullable|string|max:5000',
            'criteria' => 'sometimes|array',
            'criteria.*.question_text' => 'required_with:criteria|string|max:1000',
            'criteria.*.category' => 'required_with:criteria|string|in:technical,behavioral,cultural,experience',
            'criteria.*.sort_order' => 'required_with:criteria|integer|min:0',
            'criteria.*.rating' => 'required_with:criteria|integer|min:1|max:5',
            'criteria.*.notes' => 'sometimes|nullable|string|max:2000',
        ];
    }
}
