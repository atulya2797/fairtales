<?php

namespace App\Http\Requests\Api;

use App\Model\Attendance;
use App\Http\Requests\BaseRequest;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class MarkAttendance extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'InLoc' => 'required',
            'ManualLoc' => 'required|string',
            'LocType' => 'required|in:' . Attendance::LOCTYPE_PERMISSION . ',' . Attendance::LOCTYPE_STREET,
            'LogInIP' => 'required|ip'
        ];
    }

    public function messages() {
        return [
            'InLoc.required' => 'Geo Location is required',
            'ManualLoc.required' => 'The Manual Loc field is required.',
            'ManualLoc.string' => 'The Manual Loc field is required.',
            'LocType.required' => 'Please Select Type',
            'LogInIP.required' => 'Please Provide Ip Address',
            'LogInIP.ip' => 'Invalid Ip Address',
        ];
    }

}
