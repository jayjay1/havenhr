<?php

namespace App\Http\Requests;

class TransitionJobPostingStatusRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:draft,published,closed,archived',
        ];
    }
}
