<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BankingDebitStatus extends Model {

    protected $table = 'Banking_Debit_Status';

    const DebitStatusFail = 0;
    const DebitStatusSuccess = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'CRM_ID', 'EID', 'FID', 'EName', 'ETL', 'CharityCode', 'Charity_ID', 'SignupDate', 'ModeOfDonation', 'BatchNo', 'DebitRefNum', 'Debit_Attempt', 'DebitStatus', 'DebitReasonDesc', 'DebitStatusDate', 'created_at', 'updated_at'
    ];

    public static function getDebitStatus() {
        return [
            self::DebitStatusFail => 'Fail',
            self::DebitStatusSuccess => 'Success'
        ];
    }

    public function getEmployee() {
        return $this->hasOne('App\Model\EmployeeMaster', 'EID', 'EID');
    }

}
