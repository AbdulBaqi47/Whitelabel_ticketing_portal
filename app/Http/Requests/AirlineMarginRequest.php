<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AirlineMarginRequest extends FormRequest
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
            'sales_channel' => ['required', 'string', 'max:255'],
            'airline_id' => ['required', 'exists:airlines,id'], 
            'region' => ['required', 'array', 'min:1'],
            'region.*' => ['string', 'max:255'],
            'margin' => ['required', 'string'],
            'margin_type' => ['required', 'in:amount,percentage'],
            'sale_start_continue' => ['nullable', 'date'],
            'sale_end_continue' => ['nullable', 'date', 'after_or_equal:sale_start_continue'],
            'travel_start_continue' => ['nullable', 'date'],
            'travel_end_continue' => ['nullable', 'date', 'after_or_equal:travel_start_continue'],
            'rbds' => ['nullable', 'string', 'max:255'],
            'is_apply_on_gross' => ['required', 'boolean'],
            'status' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}