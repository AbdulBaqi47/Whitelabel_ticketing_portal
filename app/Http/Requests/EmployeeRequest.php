<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
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
    // 6a4f8e11-6edb-4f98-abce-fc66d2397493
    public function rules(): array
    {
        $uuid = $this->route('uuid');

        $rules = [];

        if ($this->isMethod('post')) {
            $rules['name'] = ['required', 'string', 'min:5', Rule::unique('users', 'name')];
            $rules['email'] = ['required', 'email', Rule::unique('users', 'email')];
            $rules['phone_number'] = [
                'required',
                'regex:/^\+?\d{1,4}?[\d\s\-\(\)]{7,15}$/',
                Rule::unique('users', 'phone_number'),
            ];
        } else {

            $rules['name'] = [
                'required',
                'string',
                'min:5',
                function ($attribute, $value, $fail) {
                    $user = \App\Models\User::where('uuid', $this->route('uuid'))->first();

                    if (
                        $user->name != $value && \App\Models\User::where('name', $value)
                        ->where('id', '!=', $user->id)->exists()
                    ) {
                        $fail('The name has already been taken.');
                    }
                }
            ];

            $rules['email'] = [
                'required',
                'email',
                function ($attribute, $value, $fail) {

                    $user = \App\Models\User::where('uuid', $this->route('uuid'))->first();

                    if (
                        $user->email != $value && \App\Models\User::where('email', $value)
                        ->where('id', '!=', $user->id)->exists()
                    ) {
                        $fail('The email has already been taken.');
                    }
                }
            ];

            $rules['phone_number'] = [
                'required',
                'regex:/^\+?\d{1,4}?[\d\s\-\(\)]{7,15}$/',
                function ($attribute, $value, $fail) use ($uuid) {
                    $user = \App\Models\User::where('uuid', $uuid)->first();

                    if (
                        $user->phone_number != $value && \App\Models\User::where('phone_number', $value)
                        ->where('id', '!=', $user->id)->exists()
                    ) {
                        $fail('The Phone Number has already been taken.');
                    }
                }
            ];
        }

        return $rules;
    }
}
