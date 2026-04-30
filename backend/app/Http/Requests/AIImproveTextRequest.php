<?php

namespace App\Http\Requests;

class AIImproveTextRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:5000'],
        ];
    }
}
