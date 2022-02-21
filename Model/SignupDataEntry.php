<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SignupDataEntry extends Model {

    protected $table = 'Signup_DataEntry';

    /**
     *
     */
    const BCopyFlagUnUsed = 0;
    const BCopyFlagUsed = 1;

    /**
     *
     */
    const FormTypeA = 'A Copy';
    const FormTypeB = 'B Copy';

    /**
     * AccountType
     */
    const AccountType_SB = 1;
    const AccountType_Ca = 2;
    const AccountType_Cc = 3;
    const AccountType_SB_Nre = 4;
    const AccountType_SB_Nro = 5;
    const AccountType_Other = 6;

    /**
     * DebitType
     */
    const DebitType_FixedAmount = 0;
    const DebitType_MaximumAmount = 1;

    /**
     * DoNotCall
     */
    const DoNotCall_No = 0;
    const DoNotCall_Yes = 1;

    /**
     * dataEntryStatus
     */
    const dataEntryStatus_Reject = 0;
    const dataEntryStatus_Accept = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CRM_ID', 'BatchNo', 'DataEntryRemarks', 'Title', 'FirstName', 'LastName', 'Gender', 'DateOfBirth', 'Mobile_1', 'Mobile_2', 'CompanyName', 'Address1', 'Address2', 'Address3', 'Address4', 'Postcode', 'City', 'State', 'Country', 'eMail_Address', 'PAN_Num', 'TaxExemptionReceiptName', 'HomePhone', 'WorkPhone', 'Alternate_eMail', 'DoNotCall', 'DoNotCall_Reason', 'CampaignCode', 'BankName', 'Branch', 'AccountHolderName', 'JointAccountHolderName', 'AccountType', 'DebitType', 'PledgeStartDate', 'PledgeEndDate', 'FormReceiveDate', 'BCopyFlag', 'FormType', 'Charity_ID', 'exportDate', 'dataEntryStatus', 'created_at', 'updated_at'
    ];

    public static function getDataEntryStatus() {
        return [
            self::dataEntryStatus_Reject => 'Reject',
            self::dataEntryStatus_Accept => 'Accept',
        ];
    }

    public static function getDataEntryCountry() {
        return 'India';
    }

    public static function getDoNotCall() {
        return [
            self::DoNotCall_No => 'No',
            self::DoNotCall_Yes => 'Yes'
        ];
    }

    public static function getAccountType() {
        return [
            self::AccountType_SB => 'SB',
            self::AccountType_Ca => 'CA',
            self::AccountType_Cc => 'CC',
            self::AccountType_SB_Nre => 'SB-NRE',
            self::AccountType_SB_Nro => 'SB-NRO',
            self::AccountType_Other => 'OTHER'
        ];
    }

    public static function getDebitType() {
        return [
            self::DebitType_FixedAmount => 'Fix Amount',
            self::DebitType_MaximumAmount => 'Maximum Amount'
        ];
    }

    public function getSignup() {
        return $this->hasOne('App\Model\Signup', 'CRM_ID', 'CRM_ID');
    }

}
