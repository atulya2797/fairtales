<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BCopyCalling extends Model {

    protected $table = 'BCopyCalling';

    const CallFinalStatus_Complete = 1;
    const CallFinalStatus_Pending = 2;
    const CallFinalStatus_Denied = 3;

    /**
     * callFinalOutcome
     */
    const callFinalOutcome_Agreed = 1;
    const callFinalOutcome_CallBack = 2;
    const callFinalOutcome_NoAnswer = 3;
    const callFinalOutcome_HungUp = 4;
    const callFinalOutcome_InvalidNumber = 5;
    const callFinalOutcome_LanguageBarrier = 6;
    const callFinalOutcome_NumberNotInUse = 7;
    const callFinalOutcome_NotIntrested = 8;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CRM_ID', 'title', 'FirstName', 'LastName', 'Gender', 'DateOfBirth', 'Mobile_1', 'Mobile_2', 'CompanyName', 'Address1', 'Address2', 'Address3', 'Address4', 'Postcode', 'City', 'State', 'Country', 'eMail_Address', 'LastDonationDate', 'LastDonationAmount', 'LastDonationFrequency', 'NoOfPayments', 'UniqueID', 'CallType', 'CallFinalStatus', 'Remarks', 'callFinalOutcome', 'CallBackDateTime', 'ModeOfDonation', 'transactionId', 'CallAttemps', 'ExportDate', 'BCopyEID', 'created_at', 'updated_at'
    ];

    public static function getCallFinalOutcome() {
        return [
            self::callFinalOutcome_Agreed => 'Agreed',
            self::callFinalOutcome_CallBack => 'Call Back',
            self::callFinalOutcome_NoAnswer => 'No Answer',
            self::callFinalOutcome_HungUp => 'Hung up',
            self::callFinalOutcome_InvalidNumber => 'Invalid Number',
            self::callFinalOutcome_LanguageBarrier => 'Language Barrier',
            self::callFinalOutcome_NumberNotInUse => 'Number Not In Use',
            self::callFinalOutcome_NotIntrested => 'Not Intrested'
        ];
    }

    public static function getCallFinalStatus() {
        return [
            self::CallFinalStatus_Complete => 'Completed',
            self::CallFinalStatus_Pending => 'Pending',
            self::CallFinalStatus_Denied => 'Denied'
        ];
    }

    public function getBCopyCallingDetail() {
        return $this->hasMany('App\Model\BCopyCallingDetail', 'CRM_ID', 'CRM_ID');
    }

}
