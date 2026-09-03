<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AirlineRequest extends FormRequest
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
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $rules = [
            'name' => 'sometimes|required|string|max:255|unique:airlines,name,'  . $uuid . ',uuid',
            'country_id' => 'required|exists:countries,id',
            'iata_code' => 'sometimes|required|string|max:255',
            'status' => 'required',
            'issuing_pcc'=> 'nullable|string|max:255',
            'reserving_pcc'=>'nullable|string|max:255',
            'tour_code'=>'nullable|string|max:255',
        ];


        if(request()->hasFile('thumbnail') && $isUpdate){
            $rules['thumbnail'] = 'image|mimes:png,jpg,jpeg';
        }else if($isUpdate){
            $rules['thumbnail'] = 'nullable';
        }else{
            $rules['thumbnail'] = 'required|image|mimes:png,jpg,jpeg';
        }

        return $rules;
    }
}
