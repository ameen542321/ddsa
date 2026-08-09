<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SecurityEventActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['acknowledge', 'contain', 'resolve', 'false_positive', 'block_source'])],
            'note' => [Rule::requiredIf(in_array($this->input('action'), ['contain', 'resolve', 'false_positive', 'block_source'], true)), 'nullable', 'string', 'min:5', 'max:1000'],
        ];
    }
}
