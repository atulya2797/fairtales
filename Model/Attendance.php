<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model {

    protected $table = 'Attendance';

    const ATTENDANCE_P = 1;
    const ATTENDANCE_HD = 2;
    const ATTENDANCE_A = 3;

    /**
     * Attendance Text
     */
    const ATTENDANCE_P_TEXT = 'P';
    const ATTENDANCE_HD_TEXT = 'HD';
    const ATTENDANCE_A_TEXT = 'A';

    /**
     * LocType
     */
    const LOCTYPE_PERMISSION = 1;
    const LOCTYPE_STREET = 2;

    /**
     * Attendance Remarks
     */
    const AttnRemarks_Present = 'P';
    const AttnRemarks_HalfDay = 'HD';
    const AttnRemarks_SickLeave = 'SL';
    const AttnRemarks_CasualLeave = 'CL';
    const AttnRemarks_LeaveWithoutPay = 'LWP';
    const AttnRemarks_Absent = 'A';
    const AttnRemarks_AbsentWithoutInfo = 'AWI';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'SR_NO', 'EID', 'EName', 'ECity', 'AttnApproveBy', 'AttnRemarks', 'InStamp', 'InLoc', 'OutStamp', 'OutLoc', 'TotProdHrs', 'InOutDist', 'Attendance', 'ManualLoc', 'LocType', 'LogInIP', 'created_at', 'updated_at'
    ];

    public static function attnRemarks() {
        return [
            'P' => 'Present',
            'HD' => 'Half Day',
            'SL' => 'Sick Leave',
            'CL' => 'Casual Leave',
            'LWP' => 'Leave without Pay',
            'A' => 'Absent',
            'AWI' => 'Absent without Info',
        ];
    }

    public function getEmployee() {
        return $this->hasOne('App\Model\EmployeeMaster', 'EID', 'EID');
    }

    public function getApproveBy() {
        return $this->hasOne('App\Model\EmployeeMaster', 'EID', 'AttnApproveBy');
    }

}
