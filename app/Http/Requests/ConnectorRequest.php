<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class ConnectorRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'type' => [
                'required',
                'string',
                Rule::in(['PIA_HITIT', 'SABRE', 'AIRBLUE_API', 'FLYDUBAI_API', 'ONEAPI', 'SABRE_NDC']),
            ],

            'api_key' => ['required_if:type,PIA_HITIT,SABRE,AIRBLUE_API,FLYDUBAI_API,SABRE_NDC', 'string'],
            'api_secret' => ['required_if:type,PIA_HITIT,SABRE,AIRBLUE_API,FLYDUBAI_API,SABRE_NDC', 'string'],

            'client_ip' => ['required_if:type,PIA_HITIT', 'ip'],

            'connector_domain' => ['required_if:type,SABRE,FLYDUBAI_API,SABRE_NDC', 'string'],
            'pcc' => ['required_if:type,SABRE,FLYDUBAI_API,SABRE_NDC', 'string'],
            'printer' => ['required_if:type,SABRE,SABRE_NDC', 'string'],

            // SABRE_NDC specific
            'is_sabre_ndc' => ['required_if:type,SABRE_NDC', 'boolean'],
            'iata' => ['required_if:type,SABRE_NDC', 'array'],
            'iata.emirates' => ['required_if:type,SABRE_NDC', 'boolean'],
            'iata.qatar_airways' => ['required_if:type,SABRE_NDC', 'boolean'],
            'iata.saudi_airline' => ['required_if:type,SABRE_NDC', 'boolean'],

            'supplier_id' => ['required', 'integer'],
            'is_enable' => ['required', 'boolean'],
        ];
    }
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') === 'SABRE_NDC') {
                $iata = $this->input('iata', []);
                $anyEnabled = !empty($iata['emirates']) || !empty($iata['qatar_airways']) || !empty($iata['saudi_airline']);

                if (!$anyEnabled) {
                    $validator->errors()->add('iata', 'At least one airline must be enabled in SABRE NDC.');
                }
            }
        });
    }

    public function messages()
    {
        return [
            'name.required' => 'The name field is required.',
            'type.required' => 'The type field is required.',
            'type.in' => 'The type must be either PIA_HITIT or SABRE.',
            'type.unique' => 'This connector already exists in the system.',

            'api_key.required_if' => 'The API Key is required when the type is PIA_HITIT or SABRE.',
            'api_secret.required_if' => 'The API Secret is required when the type is PIA_HITIT or SABRE.',

            'client_ip.required_if' => 'The Client IP is required when the type is PIA_HITIT.',
            'client_ip.ip' => 'The Client IP must be a valid IP address.',

            'connector_domain.required_if' => 'The Connector Domain is required when the type is SABRE.',
            'pcc.required_if' => 'The PCC field is required when the type is SABRE.',
            'printer.required_if' => 'The Printer field is required when the type is SABRE.',

            'is_sabre_ndc.required_if' => 'The is_sabre_ndc field is required when the type is SABRE_NDC.',
            'iata.required_if' => 'The IATA field is required when the type is SABRE_NDC.',
            'iata.array' => 'The IATA field must be an object.',
            'iata.emirates.required_if' => 'The Emirates field is required in SABRE NDC.',
            'iata.emirates.boolean' => 'The Emirates field must be true or false.',
            'iata.qatar_airways.required_if' => 'The Qatar Airways field is required in SABRE NDC.',
            'iata.qatar_airways.boolean' => 'The Qatar Airways field must be true or false.',
            'iata.saudi_airline.required_if' => 'The Saudi Airline field is required in SABRE NDC.',
            'iata.saudi_airline.boolean' => 'The Saudi Airline field must be true or false.',
        ];
    }
}
