<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LicensePlateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
         'plate_number' => 'required|min:5|max:255|unique:licenseplates,plate_number',

         "region"=>"required",
         "city"=>"required",
            'price' => ['required', 'numeric', 'between:1000,5000'],
               'status' => ['required', 'in:Available,Pending,Sold'],
                'featured'     => 'nullable|in:0,1'

        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            //
        ];
    }
}
