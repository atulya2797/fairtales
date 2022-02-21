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
class SendMessageToGroups extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {

        $rules = [
            'GrpId' => 'required',
            'message' => 'required|string',
        ];


        return $rules;
    }

    public function messages() {
        return [
            'GrpId.required' => 'Please Select Group.',
            'message.required' => 'Please Enter Message.',
            'message.string' => 'Invalid Message format.',
        ];
    }

}
