<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\BaseRequest;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class FundraiserProfileUpdate extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'EName' => 'required|string',
            'EMail' => 'required|email',
            'EPhoneNo' => 'required',
            'Epwd' => 'required',
        ];
    }

    public function messages() {
        return [
            'EName.required' => 'The name field is required.',
            'EMail.required' => 'The email field is required.',
            'EPhoneNo.required' => 'The phone no. field is required.',
            'Epwd.required' => 'The password field is required.',
        ];
    }

}
