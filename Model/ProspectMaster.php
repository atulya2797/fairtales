<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ProspectMaster extends Model {

    protected $table = 'Prospect_Master';

    const RECORDTYPE_PROSPECT = 1;
    const RECORDTYPE_SUPPORTER = 2; //Supporter == donor

    /**
     * MethodOfFundraising
     */
    const CHANNEL_F2F = 1;
    const CHANNEL_D2D = 2;
    const CHANNEL_TMA = 3;
    const CHANNEL_TMR = 4;

    /**
     * LocType
     */
    const LOCTYPE_PERMISSION = 1;
    const LOCTYPE_STREET = 2;

    /**
     * Title
     */
    const TITLE_MR = 1;
    const TITLE_MISS = 2;
    const TITLE_MRS = 3;
    const TITLE_DR = 4;

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
        'RecordType', 'CRM_ID', 'CharityCode', 'Channel', 'EID', 'FID', 'EName', 'ETLID', 'EPMID', 'ETL', 'EPM', 'GeoLocationAcc', 'pinCode', 'LocType', 'PDate', 'PTime', 'Title', 'FullName', 'FirstName', 'LastName', 'Gender', 'Mobile_1', 'Mobile_2', 'eMail_Address', 'tempUid', 'created_at', 'updated_at'
    ];

    public function getSignup() {
        return $this->hasOne('App\Model\Signup', 'CRM_ID', 'CRM_ID');
    }

    public function getEmployee() {
        return $this->hasOne('App\Model\EmployeeMaster', 'EID', 'EID');
    }

    public static function getAllTitle() {
        return [
            1 => 'Mr',
            2 => 'Miss',
            3 => 'Mrs',
            4 => 'Dr'
        ];
    }

    public static function getAllGender() {
        return [
            1 => 'Male',
            2 => 'Female',
            3 => 'Other'
        ];
    }

    public static function getRecordType() {
        return [
            self::RECORDTYPE_PROSPECT => 'Prospect',
            self::RECORDTYPE_SUPPORTER => 'Supporter(Donor)'
        ];
    }

}
