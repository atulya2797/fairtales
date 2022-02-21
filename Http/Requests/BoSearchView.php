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
class BoSearchView extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $rules = [
            'dateOfSignup' => 'nullable|string',
            'crmName' => 'nullable|string',
            'AccNo' => 'nullable|string',
            'mobile' => 'nullable|phone'
        ];

        return $rules;
    }

    public function messages() {
        return [
            'dateOfSignup.string' => 'Date of signup must be a string.',
            'crmName' => 'CRM ID/Name must be a string.',
            'AccNo' => 'Account Number must be a string.'
        ];
    }

}
