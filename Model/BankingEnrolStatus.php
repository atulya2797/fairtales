<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BankingEnrolStatus extends Model {

    protected $table = 'Banking_Enrol_Status';

    const EnrolStatusFail = 0;
    const EnrolStatusSuccess = 1;

    /**
     * PledgeStatus
     */
    const PledgeStatusActive = 1;
    const PledgeStatusCancel = 2;
    const PledgeStatusRejected = 3;
    const PledgeStatusBurnt = 4;

    /**
     * Resub
     */
    const Resub_Yes = 1;
    const Resub_No = 0;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'CRM_ID', 'EID', 'FID', 'EName', 'ETL', 'CharityCode', 'Charity_ID', 'SignupDate', 'ModeOfDonation', 'BatchNo', 'PledgeStatus', 'EnrolRefNum', 'InitialRejectReason', 'EnrolStatus', 'Resub', 'ReasonDesc', 'EnrolStatDate', 'created_at', 'updated_at'
    ];

    public static function getPledgeStatus() {
        return [
            self::PledgeStatusActive => 'Active',
            self::PledgeStatusCancel => 'Cancelled',
            self::PledgeStatusRejected => 'Rejected',
            self::PledgeStatusBurnt => 'Burnt'
        ];
    }

    public static function getEnrolStatus() {
        return [
            self::EnrolStatusFail => 'Fail',
            self::EnrolStatusSuccess => 'Success'
        ];
    }

    public static function getResub() {
        return [
            self::Resub_No => 'N',
            self::Resub_Yes => 'Y'
        ];
    }

    public function getEmployee() {
        return $this->hasOne('App\Model\EmployeeMaster', 'EID', 'EID');
    }

}
