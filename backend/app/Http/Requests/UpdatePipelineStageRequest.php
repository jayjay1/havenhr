<?php

namespace App\Http\Requests;

class UpdatePipelineStageRequest extends BaseFormRequest
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
            'color' => 'sometimes|nullable|regex:/^#[0-9a-fA-F]{6}$/',
        ];
    }
}
