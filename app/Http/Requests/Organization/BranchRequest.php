<?php

namespace App\Http\Requests\Organization;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class BranchRequest extends FormRequest
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
        $rules = [
            'org_name' => ['required', 'string', 'max:255'],
            'manager_name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
        ];

        if ($this->isMethod('post')) {
            $rules['email'] = ['required', 'email', Rule::unique('users', 'email')];
            $rules['phone_number'] = [
                'required',
                'regex:/^\+?\d{1,4}?[\d\s\-\(\)]{7,15}$/',
                Rule::unique('users', 'phone_number')
            ];
        } else {

            $rules['email'] = [
                'required',
                'email',
                function ($attribute, $value, $fail) {

                    $organizationUuid = $this->route('uuid');

                    $org = \App\Models\Organization::where('uuid', $organizationUuid)->firstOrFail();

                    if ($org->main_user->email != $value && \App\Models\User::where('email', $value)
                        ->where('id', '!=', $org->main_user->id)->exists()
                    ) {
                        $fail('The email has already been taken.');
                    }
                }
            ];
            $rules['phone_number'] = [
                'required',
                'regex:/^\+?\d{1,4}?[\d\s\-\(\)]{7,15}$/',
                function ($attribute, $value, $fail) {
                    $organizationUuid = $this->route('uuid');

                    $org = \App\Models\Organization::where('uuid', $organizationUuid)->firstOrFail();

                    if (
                        $org->main_user->phone_number != $value && \App\Models\User::where('phone_number', $value)
                        ->where('id', '!=', $org->main_user->id)->exists()
                    ) {
                        $fail('The Phone Number has already been taken.');
                    }
                }
            ];
        }

        return $rules;
    }
}
