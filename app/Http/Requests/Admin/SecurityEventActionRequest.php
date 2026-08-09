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
            'action' => ['required', Rule::in(['acknowledge', 'assign', 'add_note', 'contain', 'resolve', 'false_positive', 'block_source'])],
            'note' => [Rule::requiredIf(in_array($this->input('action'), ['add_note', 'contain', 'resolve', 'false_positive', 'block_source'], true)), 'nullable', 'string', 'min:5', 'max:1000'],
            'assigned_to' => [Rule::requiredIf($this->input('action') === 'assign'), 'nullable', 'integer', Rule::exists('users', 'id')->where('role', 'admin')],
        ];
    }
}
