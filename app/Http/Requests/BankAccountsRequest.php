<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankAccountsRequest extends FormRequest
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
        $rules = [
            'account_number' => [
                'required', 
                'string', 
                Rule::unique('banks', 'account_number')->where(fn ($query) => $query->where('uuid', '!=', $uuid)),
                'regex:/^[A-Za-z0-9]+$/'
            ],
            'bank_name' => [
                'required', 
                'string', 
            ],
            'bank_address' => 'sometimes|required|string',
            'contact_number' => 'sometimes|required|string',
            'account_holder_name' => ['required', 'string'],
            'iban'=>'sometimes|required|string',
        ];

        if($this->isMethod('post')){
            $rules['bank_logo'] = ['required', 'image', 'mimes:png,jpg,jpeg'];
        }else{
            $rules['bank_logo'] = ['nullable', 'image', 'mimes:png,jpg,jpeg'];
        }

        return $rules;
    }
}
