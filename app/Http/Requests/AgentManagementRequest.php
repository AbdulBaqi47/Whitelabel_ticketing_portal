<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentManagementRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:5'],
            'agent_type' => ['required'],
        ];
        
        if ($this->isMethod('post')) {
            $rules['email'] = ['required', 'email', Rule::unique('users', 'email')];
            $rules['name'] = ['required', 'string', 'min:5', Rule::unique('users', 'name')];
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
        }

        return $rules;
    }
}
