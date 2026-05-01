<?php

namespace App\Http\Requests;

class AddPipelineStageRequest extends BaseFormRequest
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
            'sort_order' => 'required|integer|min:0',
        ];
    }
}
