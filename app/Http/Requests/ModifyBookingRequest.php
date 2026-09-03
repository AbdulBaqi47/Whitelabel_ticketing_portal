<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModifyBookingRequest extends FormRequest
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

            'status' => ['required', 'string', 'in:confirmed,cancelled,refunded,issued,voided,expired'],

            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.passenger_id' => ['required','integer','exists:passengers,id'],
            'passengers.*.ticket_number' => ['nullable','string','max:50','required_if:status,issued'],
            'passengers.*.d_type' => ['required', 'string', 'in:P,I'],
            'passengers.*.d_number' => ['required', 'string', 'max:50'],
            'passengers.*.d_expiry' => ['required', 'date', 'after:today'],
            'passengers.*.date_of_birth' => ['required', 'date', 'before:today'],

            /* =========================
             * Booked Segments
             * ========================= */
            'booked_segments' => ['required', 'array', 'min:1'],
            'booked_segments.*.id' => ['nullable', 'integer'],
            'booked_segments.*.seg_origin' => ['required', 'string', 'size:3'],
            'booked_segments.*.seg_destination' => ['required', 'string', 'size:3'],
            'booked_segments.*.m_airline' => ['required', 'string', 'size:2'],
            'booked_segments.*.m_flight_number' => ['required', 'string', 'max:10'],
            'booked_segments.*.o_airline' => ['nullable', 'string', 'size:2'],
            'booked_segments.*.o_flight_number' => ['nullable', 'string', 'max:10'],
            'booked_segments.*.flight_pnr' => ['required', 'string', 'max:20'],
            'booked_segments.*.meal' => ['required', 'in:yes,no'],
            'booked_segments.*.seg_departure_datetime' => ['required', 'date'],
            'booked_segments.*.seg_arrival_datetime' => ['required','date','after:booked_segments.*.seg_departure_datetime'],
            'booked_segments.*.parent_id' => ['nullable', 'integer'],

            /* =========================
             * Airports (nested objects)
             * ========================= */
            'booked_segments.*.a_airport.iata_code' => ['required', 'string', 'size:3'],
            'booked_segments.*.d_airport.iata_code' => ['required', 'string', 'size:3'],

            /* =========================
             * Baggage (per sector)
             * ========================= */
            'baggage' => ['nullable', 'array'],
            'baggage.*' => ['array'],
            'baggage.*.*' => ['array'],
            'baggage.*.*.*.provisionType' => ['required', 'in:A,B'],
            'baggage.*.*.*.provision' => ['required', 'string'],
            'baggage.*.*.*.weight' => ['required', 'numeric', 'min:0'],
            'baggage.*.*.*.unit' => ['required', 'in:kg,lb'],
            'baggage.*.*.*.pieces' => ['required', 'integer', 'min:1'],
        ];
    }
}
