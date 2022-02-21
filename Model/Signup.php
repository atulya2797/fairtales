<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Signup extends Model {

    protected $table = 'Signup';

    const ACCOUNT_CHECK = 1;
    const QUALITY_CHECK = 2;
    const DATA_ENTRY_CHECK = 3;
    const CALL_COMPLETE = 4;
    const FORM_RECEIVABLE = 5;

    /**
     * Recordtype
     */
    const RECORDTYPE_PROSPECT = 1;
    const RECORDTYPE_SUPPORTER = 2; //Supporter == donor

    /**
     * MethodOfFundraising
     */
    const METHODOFFUNDRAISING_F2F = 1;
    const METHODOFFUNDRAISING_D2D = 2;
    const METHODOFFUNDRAISING_TMA = 3;
    const METHODOFFUNDRAISING_TMR = 4;

    /**
     * LocType
     */
    const LOCTYPE_PERMISSION = 1;
    const LOCTYPE_STREET = 2;

    /**
     * ModeOfDonation
     */
    const MODEOFDONATION_NACH = 1;
    const MODEOFDONATION_ONLINE = 2;
    const MODEOFDONATION_CHEQUE = 3;
    const MODEOFDONATION_ENACH = 4;

    /**
     * Text Value
     */
    const MODEOFDONATION_NACH_TEXT = 'NACH';
    const MODEOFDONATION_ONLINE_TEXT = 'ONLINE';
    const MODEOFDONATION_CHEQUE_TEXT = 'CHEQUE';
    const MODEOFDONATION_ENACH_TEXT = 'ENACH';

    /**
     * Title
     */
    const TITLE_MR = 1;
    const TITLE_MISS = 2;
    const TITLE_MRS = 3;
    const TITLE_DR = 4;

    /*
     * ClawBack
     */
    const ClawBackYes = 1;
    const ClawBackNo = 0;

    /**
     * Gender
     */
    const GENDER_MALE = 1;
    const GENDER_FEMALE = 2;
    const GENDER_OTHER = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'SR_NO', 'RecordType', 'CRM_ID', 'CharityCode', 'MethodOfFundraising', 'SignupRemarks', 'EID', 'FID', 'EName', 'ETLID', 'ETL', 'GeoLocationAcc', 'LocType', 'PDate', 'PTime', 'ModeOfDonation', 'Photo1', 'Photo2', 'Photo3', 'Photo4', 'Photo5', 'Photo6', 'accountCheck', 'invoice_no', 'invoice_date', 'claw_back'
    ];

    public function getEmployee() {
        return $this->hasOne('App\Model\EmployeeMaster', 'EID', 'EID');
    }

    public function getEtl(){
        return $this->hasOne('App\Model\EmployeeMaster', 'EID', 'ETLID');
    }

    public function getProspect() {
        return $this->hasOne('App\Model\ProspectMaster', 'CRM_ID', 'CRM_ID');
    }

    public function getWelcomeCall() {
        return $this->hasOne('App\Model\SignupWlcmCall', 'CRM_ID', 'CRM_ID');
    }

    public function getSignupAccCheck() {
        return $this->hasOne('App\Model\SignupAccountChk', 'CRM_ID', 'CRM_ID');
    }

    public function getSignupFormChk() {
        return $this->hasOne('App\Model\SignupFormChk', 'CRM_ID', 'CRM_ID');
    }

    public function getSignupDataEntry() {
        return $this->hasOne('App\Model\SignupDataEntry', 'CRM_ID', 'CRM_ID');
    }

    public function getWlcmCallDetail() {
        return $this->hasMany('App\Model\WlcmCallDetail', 'CRM_ID', 'CRM_ID');
    }

    public function getBankingEnrolStatus() {
        return $this->hasOne('App\Model\BankingEnrolStatus', 'CRM_ID', 'CRM_ID');
    }

    public function getBankingDebitStatus() {
        return $this->hasMany('App\Model\BankingDebitStatus', 'CRM_ID', 'CRM_ID');
    }

    public function getBCopyDataEntry() {
        return $this->hasOne('App\Model\BCopyDataEntry', 'RefCrmID','CRM_ID');
    }

    public static function modeOfDonation() {
        return [
            self::MODEOFDONATION_NACH => 'NACH',
            self::MODEOFDONATION_ONLINE => 'Online',
            self::MODEOFDONATION_CHEQUE => 'Cheque',
            self::MODEOFDONATION_ENACH => 'ENACH'
        ];
    }

    public static function getClawBack() {
        return [
            self::ClawBackYes => 'yes',
            self::ClawBackNo => 'no'
        ];
    }

}
