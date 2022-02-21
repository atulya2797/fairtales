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
class RetryDonorSave extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'BOStatRetryTime' => 'required',
            'BOStatRemark' => 'required|string'
        ];
    }

    public function messages() {
        return [
            'BOStatRetryTime.required' => 'Please Select Retry Date and time.',
            'BOStatRemark.required' => 'Please Enter Remark.'
        ];
    }

}
