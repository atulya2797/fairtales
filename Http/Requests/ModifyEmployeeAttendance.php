<?php

namespace App\Http\Requests;

use App\Model\Attendance;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class ModifyEmployeeAttendance extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $getallAttRemarks = Attendance::attnRemarks();
        $getRemarks = array_keys($getallAttRemarks);
        $remarks = implode(',', $getRemarks);

        $rule = [
            'AttnRemarks' => 'required|in:' . $remarks,
            'OutStamp' => 'required',
            'InStamp' => 'required'
        ];

        return $rule;
    }

    public function messages() {
        return [
            'AttnRemarks.required' => 'Please Select Remark',
            'AttnRemarks.in' => 'Invalid Remark',
            'OutStamp.required' => 'Please Select Out Stamp Date',
            'InStamp.required' => 'Please Select Out Stamp Date'
        ];
    }

}
