<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class EmployeeMaster extends Model {

    protected $table = 'Employee_Master';

    const EID_PREFIX = 'FT';
    const FID_PREFIX = 'FD-UNI';

    /**
     * Channel
     */
    const CHANNEL_F2F = 1;
    const CHANNEL_D2D = 2;
    const CHANNEL_TMA = 3;
    const CHANNEL_TMR = 4;

    /**
     * EStatus
     */
    const EStatusConfirmed = 1;
    const EStatusProbation = 2;
    const EStatusExtendedProbation = 3;
    const EStatusLeft = 4;

    /**
     * EType
     */
    const ETypePartTime = 1;
    const ETypeFullTime = 2;

    /**
     * accountStatus
     */
    const AccountStatusDiactivated = 0;
    const AccountStatusActivated = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'EID', 'FID', 'Channel', 'CharityCode', 'CampaignCode', 'ECity', 'EName', 'EDesg', 'TeamId',
        'EDOJ', 'EDOL', 'EPhoneNo', 'EMail', 'ETLID', 'EPMID', 'ECHID', 'ERMID', 'ETL', 'EPM', 'ECH',
        'wlcmCallNumber', 'ERM', 'EAccess', 'Epwd', 'EStatus', 'EType', 'accountStatus', 'created_at', 'updated_at'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'Epwd', 'SR_NO'
    ];

    public function getCity() {
        return $this->hasOne('App\Model\EmployeeECity', 'id', 'ECity');
    }

    public function getDesg() {
        return $this->hasOne('App\Model\EmployeeEDesg', 'id', 'EDesg');
    }

    public function getCampaign() {
        return $this->hasOne('App\Model\Campaign', 'id', 'CampaignCode');
    }

    public function getETL() {
        return $this->hasOne('App\Model\EmployeeMaster', 'EID', 'ETLID');
    }

    public function getTeam() {
        return $this->hasOne('App\Model\Team', 'id', 'TeamId');
    }

    public function getUserInfo() {
        return $this->hasOne('App\Model\User', 'EID', 'EID');
    }

    public static function getProbation() {
        return [
            EmployeeMaster::EStatusConfirmed => 'Confirm',
            EmployeeMaster::EStatusProbation => 'Probation',
            EmployeeMaster::EStatusExtendedProbation => 'Extended Probation',
            EmployeeMaster::EStatusLeft => 'Left'
        ];
    }

}
