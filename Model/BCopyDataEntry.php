<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BCopyDataEntry extends Model {

    protected $table = 'BCopyDataEntry';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'RefCrmID', 'BatchNo', 'DataEntryRemarks', 'Title', 'FirstName', 'LastName', 'Gender', 'DateOfBirth', 'Mobile_1', 'Mobile_2', 'CompanyName', 'Address1', 'Address2', 'Address3', 'Address4', 'Postcode', 'City', 'State', 'Country', 'eMail_Address', 'PAN_Num', 'TaxExemptionReceiptName', 'HomePhone', 'WorkPhone', 'Alternate_eMail', 'DoNotCall', 'DoNotCall_Reason', 'CampaignCode', 'BankName', 'Branch', 'AccountHolderName', 'JointAccountHolderName', 'AccountType', 'DebitType', 'PledgeStartDate', 'PledgeEndDate', 'FormReceiveDate', 'BCopyFlag', 'FormType', 'Charity_ID', 'exportDate', 'dataEntryStatus', 'created_at', 'updated_at'
    ];

    public function getSignup() {
        return $this->hasOne('App\Model\Signup', 'CRM_ID', 'RefCrmID');
    }

}
