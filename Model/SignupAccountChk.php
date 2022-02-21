<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SignupAccountChk extends Model {

    protected $table = 'Signup_AccountChk';

    /**
     * Create score by divide amount by 1000
     */
    const Score_Point_Price = 1000;
    const OneTimeAmount = 10800;

    /**
     *
     */
    const STATUS_ACCEPTED = 1;
    const STATUS_RETRY = 2;
    const STATUS_REJECTED = 3;

    /**
     * Frequency
     */
    const Frequency_Monthly = 1;
    const Frequency_OneTime = 0;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CRM_ID', 'Frequency', 'Amount', 'AccountNo', 'IFSCCode', 'AccountValidationStatus', 'AccountHolderName_PayNimo', 'AccountValidationFailReason', 'OnlineTransactionID', 'BOStatUpdate', 'BOStatRetryTime', 'BOStatRemark', 'created_at', 'updated_at'
    ];

    public function getIfscDetail() {
        return $this->hasOne('App\Model\IfscMaster', 'ifsc', 'IFSCCode');
    }

    public static function getAllFrequency() {
        return [
            self::Frequency_OneTime => 'OneTime',
            self::Frequency_Monthly => 'Monthly'
        ];
    }

    public static function getBOStatUpdate() {
        return [
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_RETRY => 'Retry',
            self::STATUS_REJECTED => 'Rejected'
        ];
    }

    public function getSignup() {
        return $this->hasOne('App\Model\Signup', 'CRM_ID', 'CRM_ID');
    }

}
