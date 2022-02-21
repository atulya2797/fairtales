<?php

namespace App\Http\Requests;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class ResetPassword extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'password' => 'required_with:confirm_password|same:confirm_password',
            'confirm_password' => 'required|passwordRegex'
        ];
    }

    public function messages() {
        return [
            'password.required' => 'Please Select Password',
            'confirm_password.required' => 'Please Select Password1'
        ];
    }

}
