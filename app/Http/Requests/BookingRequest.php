<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
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
    public function rules()
    {
        return [
            'confirmation_id' => ['required', 'string', 'max:50'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.title' => ['required', 'in:MR,MRS,MS,MSTR,INF', 'max:5'],
            'passengers.*.first_name' => ['required', 'string', 'max:50'],
            'passengers.*.last_name' => ['required', 'string', 'max:50'],
            'passengers.*.date_of_birth' => ['required', 'date_format:Y-m-d'],
            'passengers.*.gender' => ['required', 'in:M,F,O'],
            'passengers.*.nationality' => ['required', 'string', 'max:50'],
            'passengers.*.d_type' => ['required', 'in:P,I'],
            'passengers.*.d_expiry' => ['required', 'date_format:Y-m-d', 'after:today'],
            'passengers.*.d_number' => ['required', 'string', 'max:20'],
            'passengers.*.passenger_type' => ['required', 'in:ADT,CNN,INF'],
            'contact_number' => ['required', 'string', 'regex:/^\+?[0-9]{11,13}$/'],
            'created_for' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'agency_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $passengers = $this->input('passengers', []);

            foreach ($passengers as $index => $passenger) {
                $dob = $passenger['date_of_birth'] ?? null;
                $type = $passenger['passenger_type'] ?? null;
                $dType = $passenger['d_type'] ?? null;
                $dNumber = $passenger['d_number'] ?? null;
                
                if ($dob && $type) {
                    $age = \Carbon\Carbon::parse($dob)->age;

                    if ($type === 'CNN' && ($age < 2 || $age > 11)) {
                        $validator->errors()->add("passengers.$index.date_of_birth", 'Children (CNN) must be between 2 and 11 years old.');
                    }

                    if ($type === 'INF' && ($age < 0 || $age > 2)) {
                        $validator->errors()->add("passengers.$index.date_of_birth", 'Infants (INF) must be between 0 and 2 years old.');
                    }

                    if ($type === 'ADT' && $age <= 11) {
                        $validator->errors()->add("passengers.$index.date_of_birth", 'The date of birth must indicate an age greater than 11 years for an adult (ADT).');
                    }
                }

                if ($dType === 'P') {
                    if (!preg_match('/^[A-Za-z0-9]{6,9}$/', $dNumber)) {
                        $validator->errors()->add("passengers.$index.d_number", 'The passport number must be 6 to 9 alphanumeric characters.');
                    }
                } elseif ($dType === 'I') {
                    if (!preg_match('/^\d{10,15}$/', $dNumber)) {
                        $validator->errors()->add("passengers.$index.d_number", 'The ID number must be 10 to 15 digits.');
                    }
                }
            }
        });
    }



    public function messages(): array
    {
        return [
            'confirmation_id.required' => 'The confirmation ID is required.',
            'passengers.*.title.in' => 'The title must be one of: MR, MRS, MS, DR.',
            'passengers.*.date_of_birth.date_format' => 'The date of birth must be in the format DD-MM-YYYY.',
            'passengers.*.d_expiry.after' => 'The document expiry date must be a future date.',
            'passengers.*.gender.in' => 'Gender must be either M, F, or O.',
            'passengers.*.d_type.in' => 'Document type must be P (passport) or I (ID card).',
            'contact_number.required' => 'The Contact number is required.',
            'contact_number.regex' => 'The Contact number must be a valid international number (11 to 13 digits, optional leading +).',
            'branch_id.exists' => 'The selected branch does not exist.',
            'agency_id.exists' => 'The selected agency does not exist.',
        ];
    }
}
