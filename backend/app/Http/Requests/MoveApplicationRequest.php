<?php

namespace App\Http\Requests;

class MoveApplicationRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage_id' => 'required|uuid',
        ];
    }
}
