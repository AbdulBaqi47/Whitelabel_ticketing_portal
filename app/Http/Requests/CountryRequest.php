<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class CountryRequest extends FormRequest
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
                'max:255',
                Rule::unique('countries', 'name')->ignore($uuid, 'uuid'),
            ],
            'nice_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('countries', 'nice_name')->ignore($uuid, 'uuid'),
        ],
            'iso' => 'sometimes|required|string|max:255',
            'iso3' => 'required|string|max:255',
            'status' => 'boolean',
        ];
    }
}
