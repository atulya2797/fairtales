<?php

namespace App\Http\Requests;

use App\Model\Signup;
use App\Model\EmployeeMaster;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class DonorReject extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'BOStatRemark' => 'required|string'
        ];
    }

    public function messages() {
        return [
            'BOStatRemark.required' => 'Please Enter Remark.'
        ];
    }

}
