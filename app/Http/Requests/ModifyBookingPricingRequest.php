<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ModifyBookingPricingRequest extends FormRequest
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
        return [

            'base_fare' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', 'numeric', 'min:0'],
            'gross_fare' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'numeric'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'remarks' => ['required', 'string', 'min:5'],

            'fare_break_down' => ['required', 'array'],

            'fare_break_down.*.currency' => ['required', 'string'],
            'fare_break_down.*.quantity' => ['required', 'integer', 'min:1'],
            'fare_break_down.*.base_fare' => ['required', 'numeric', 'min:0'],
            'fare_break_down.*.tax' => ['required', 'numeric', 'min:0'],
            'fare_break_down.*.gross_amount' => ['required', 'numeric', 'min:0'],
            'fare_break_down.*.discount_psf' => ['required', 'numeric'],
            'fare_break_down.*.total_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

     public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {

            /** Root gross fare validation */
            if (
                $this->base_fare + $this->tax != $this->gross_fare
            ) {
                $validator->errors()->add(
                    'gross_fare',
                    'Gross fare must be equal to Base Fare + Tax.'
                );
            }

            /** Fare breakdown validation */
            foreach ($this->fare_break_down as $type => $fare) {

                if (($fare['base_fare'] + $fare['tax']) != $fare['gross_amount']) {
                    $validator->errors()->add(
                        "fare_break_down.$type.gross_amount",
                        "Gross amount must be equal to Base Fare + Tax for $type."
                    );
                }

                $total_fare = 0;
                if($fare['discount_psf'] > 0) {
                   $total_fare = $fare['gross_amount'] + $fare['discount_psf'];
                }else{
                    $total_fare = $fare['gross_amount'] - $fare['discount_psf'];
                }

                if ($total_fare != $fare['total_amount']) {
                    $message = $fare['discount_psf'] > 0 ? 'Plus' : 'minus';
                    $validator->errors()->add(
                        "fare_break_down.$type.total_amount",
                        "Total amount must be Gross Amount $message Discount for $type."
                    );
                }
                
            }
        });
    }
}
