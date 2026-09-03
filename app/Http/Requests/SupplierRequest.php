<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $uuid = $this->route('uuid');
        return [
            'name' => [
                'required',
                'string',
                Rule::unique('suppliers', 'name')->where(fn($query) => $query->where('uuid', '!=', $uuid)),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['boolean']
        ];
    }

    public function messages()
    {
        return [

            'name.required' => 'The supplier name is required.',
            'name.string' => 'The supplier name must be a string.',
            'name.unique' => 'A supplier with this name already exists.',
            'description.string' => 'The description must be a string.',
            'status.boolean' => 'The status must be true or false.',
        ];
    }
}
