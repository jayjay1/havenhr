<?php

namespace App\Http\Requests;

class ReorderPipelineStagesRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stages' => 'required|array|min:1',
            'stages.*.id' => 'required|uuid',
            'stages.*.sort_order' => 'required|integer|min:0',
        ];
    }
}
