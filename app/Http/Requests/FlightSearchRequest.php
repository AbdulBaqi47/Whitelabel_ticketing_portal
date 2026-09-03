<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FlightSearchRequest extends FormRequest
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
        return array_merge([
            'cabin_class' => 'required|string|in:ECONOMY,BUSINESS,FIRST_CLASS,PREMUIM_ECONOMY',
            'route_type' => 'required|string|in:ONEWAY,RETURN,MULTICITY',
            'traveler_count' => 'required|array',
            'traveler_count.adult_count' => 'required|integer|min:1',
            'traveler_count.child_count' => 'nullable|integer|min:0',
            'traveler_count.infant_count' => 'nullable|integer|min:0',
            'uid'=> 'nullable|string'
        ], $this->routeTypeRules());
    }

    private function routeTypeRules(): array
    {
        $route_type = $this->input('route_type');
        switch ($route_type) {
            case 'ONEWAY':
                return [
                    'departure_date' => 'required|date',
                    'origin' => 'required|string|size:3',
                    'destination' => 'required|string|size:3',
                    'return_date' => 'nullable',
                ];

            case 'RETURN':
                return [
                    'departure_date' => 'required|date',
                    'return_date' => 'required|date|after:departure_date',
                    'origin' => 'required|string|size:3',
                    'destination' => 'required|string|size:3',
                ];

            case 'MULTICITY':
                return [
                    'legs' => 'required|array|min:2',
                    'legs.*.departure_date' => 'required|date|after_or_equal:today',
                    'legs.*.origin' => 'required|string|size:3',
                    'legs.*.destination' => 'required|string|size:3',
                ];

            default:
                return [];
        }
    }

    public function messages(): array
    {
        return [
            'route_type.in' => 'The route type must be one of the following: ONEWAY, RETURN, or MULTICITY.',
            'departure_date.required' => 'The departure date is required for ONEWAY and RETURN trips.',
            'return_date.required' => 'The return date is required for RETURN trips.',
            'legs.required' => 'At least two flight legs are required for a MULTICITY trip.',
            'uuid'=> 'Please Provide Booking Connector'
        ];
    }
}
