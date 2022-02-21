<?php

namespace App\Http\Requests;

use App\Model\Signup;
use App\Model\ProspectMaster;
use App\Model\SignupDataEntry;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class FormReceivable extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $rule = [
            'IsReceivable' => 'required'
        ];

        return $rule;
    }

    public function messages() {
        return [
            'IsReceivable.required' => 'Please Select Record.'
        ];
    }

}
