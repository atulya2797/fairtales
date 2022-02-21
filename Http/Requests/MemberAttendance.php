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
class MemberAttendance extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'EID' => 'required|string',
            'memberAttendance.*.EID' => 'required|string',
            'memberAttendance.*.AttnRemarks' => 'required|string',
            'memberAttendance.*.Attendance' => 'required|in:' . Attendance::ATTENDANCE_P . ',' . Attendance::ATTENDANCE_HD . ',' . Attendance::ATTENDANCE_A,
        ];
    }

    public function messages() {
        return [
            'EID.required' => 'Please Provide Auth User EID.',
            'EID.string' => 'EID Must be alphanumeric.',
            'memberAttendance.*.EID.required' => 'Please Provide Member\'s EID',
            'memberAttendance.*.EID.string' => 'Member\'s EID Must be alphanumeric',
            'memberAttendance.*.AttnRemarks.required' => 'Please Select Remark',
            'memberAttendance.*.AttnRemarks.string' => 'Invalid Remark.',
            'memberAttendance.*.Attendance.required' => 'Please Select Attendance',
            'memberAttendance.*.Attendance.in' => 'Invalid Attendance.',
        ];
    }

}
