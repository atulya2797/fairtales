<?php

namespace App\Http\Requests;

use App\Model\SignupFormChk;
use App\Model\ProspectMaster;
use App\Model\SignupWlcmCall;
use App\Model\SignupDataEntry;
use App\Model\SignupAccountChk;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class BCopySearchEdit extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        if (request()->isMethod('get')) {
            return [];
        }
        $getCallOutCome = SignupWlcmCall::getCallOutcome();
        $getFFpStatus = SignupFormChk::getFFPStatus();
        $getAllTitle = ProspectMaster::getAllTitle();
        $getAllDoNotCall = SignupDataEntry::getDoNotCall();
        $getAllFrequency = SignupAccountChk::getAllFrequency();
        $getAccType = SignupDataEntry::getAccountType();
        $getDebitType = SignupDataEntry::getDebitType();
        $getDataEntryStatus = SignupDataEntry::getDataEntryStatus();
        $getBOStatUpdate = SignupAccountChk::getBOStatUpdate();
        $getCallFinalStatus = SignupWlcmCall::getCallFinalStatus();

        $rules = [
            'BOStatUpdate' => 'nullable|in:' . implode(',', array_keys($getBOStatUpdate)),
            'CallOutcome.*' => 'nullable|in:' . implode(',', array_keys($getCallOutCome)),
            'Call_FinalStatus.*' => 'nullable|in:' . implode(',', array_keys($getCallFinalStatus)),
            'Call_FinalStatusRemark.*' => 'nullable|string',
            'FFPStatus' => 'nullable|in:' . implode(',', array_keys($getFFpStatus)),
            'Remarks' => 'nullable|string',
            'NoOfSignACopy' => 'nullable|integer|between:1,3',
            'NoOfSignBCopy' => 'nullable|integer|between:0,3',
            'dataEntryStatus' => 'nullable|in:' . implode(',', array_keys($getDataEntryStatus)),
            'DataEntryRemarks' => 'nullable|string',
            'Title' => 'nullable|in:' . implode(',', array_keys($getAllTitle)),
            'FirstName' => 'nullable|string',
            'LastName' => 'nullable|string',
            'DateOfBirth' => 'nullable',
            'CompanyName' => 'nullable|string',
            'Mobile_1' => 'nullable|phone',
            'Mobile_2' => 'nullable|phone',
            'Address1' => 'nullable|string',
            'Address2' => 'nullable|string',
            'Address3' => 'nullable|string',
            'Address4' => 'nullable|string',
            'Postcode' => 'nullable',
            'eMail_Address' => 'nullable|email',
            'Alternate_eMail' => 'nullable|email',
            'HomePhone' => 'nullable',
            'WorkPhone' => 'nullable',
            'TaxExemptionReceiptName' => 'nullable|string',
            'DoNotCall' => 'nullable|in:' . implode(',', array_keys($getAllDoNotCall)),
            'DoNotCall_Reason' => 'nullable|string',
            'Frequency' => 'nullable|in:' . implode(',', array_keys($getAllFrequency)),
            'Amount' => 'nullable|numeric',
            'AccountNo' => 'nullable|numeric',
            'IFSCCode' => 'nullable|string',
            'AccountHolderName' => 'nullable|string',
            'JointAccountHolderName' => 'nullable|string',
            'AccountType' => 'nullable|in:' . implode(',', array_keys($getAccType)),
            'DebitType' => 'nullable|in:' . implode(',', array_keys($getDebitType)),
            'PledgeStartDate' => 'nullable',
            'PledgeEndDate' => 'nullable',
            'OnlineTransactionID' => 'nullable|string',
            'FormReceiveDate' => 'nullable',
        ];

        return $rules;
    }

    public function messages() {
        return [
            'BOStatUpdate.*.required' => 'Please Select Account Validation Final Status.',
            'BOStatUpdate.*.in' => 'Invalid Account Validation Final Status.',
            'CallOutcome.*.required' => 'Please Select CallOutcome.',
            'CallOutcome.*.in' => 'Invalid CallOutcome.',
            'Call_FinalStatusRemark.*.required' => 'Please Select Final Status Remark.',
            'Call_FinalStatusRemark.*.string' => 'Invalid Call Final Status Remark.',
            'FFPStatus.required' => 'Please Select Form Quality Final Status.',
            'FFPStatus.in' => 'Invalid Form Quality Final Status.',
            'Remarks.required' => 'Please Enter Form Quality Remarks.',
            'Remarks.string' => 'Invalid Form Quality Remarks.',
            'NoOfSignACopy.required' => 'Please Enter No Of Sign A Copy',
            'NoOfSignACopy.integer' => 'No Of Sign A Copy must be number',
            'NoOfSignBCopy.required' => 'Please Enter No Of Sign B Copy',
            'NoOfSignBCopy.integer' => 'No Of Sign B Copy must be number',
            'dataEntryStatus.required' => 'Please select Data Entry Status',
            'dataEntryStatus.in' => 'Invalid Data Entry Status',
            'DataEntryRemarks.required' => 'Please Enter Data Entry Remarks.',
            'DataEntryRemarks.string' => 'Invalid Data Entry Remarks.',
            'Title.required' => 'Please Select Title.',
            'Title.in' => 'Invalid Title.',
            'FirstName.required' => 'Please Enter First Name.',
            'FirstName.string' => 'Invalid First Name.',
            'LastName.required' => 'Please Enter Last Name.',
            'LastName.string' => 'Invalid Last Name.',
            'DateOfBirth' => 'Please Select Date Of Birth.',
            'CompanyName.required' => 'Please Enter Company Name.',
            'CompanyName.string' => 'Invalid Company Name.',
            'Mobile_1.required' => 'Please Enter Mobile_1.',
            'Mobile_2.required' => 'Please Enter Mobile_2.',
            'Address1.required' => 'Please Enter Address1.',
            'Address1.string' => 'Invalid Address1.',
            'Address2.required' => 'Please Enter Address2.',
            'Address2.string' => 'Invalid Address2.',
            'Address3.required' => 'Please Enter Address3.',
            'Address3.string' => 'Invalid Address3.',
            'Address4.required' => 'Please Enter Address4.',
            'Address4.string' => 'Invalid Address4.',
            'Postcode.required' => 'Please Enter Postcode.',
            'eMail_Address.required' => 'Please Enter Email Address.',
            'eMail_Address.email' => 'Invalid Email Address.',
            'Alternate_eMail.required' => 'Please Enter Alternate Email Address.',
            'Alternate_eMail.email' => 'Invalid Alternate Email Address.',
            'HomePhone.required' => 'Please Enter Home Phone.',
            'WorkPhone.required' => 'Please Enter Work Phone.',
            'PAN_Num.required' => 'Please Enter PAN_Num.',
            'PAN_Num.string' => 'Invalid PAN_Num.',
            'TaxExemptionReceiptName.required' => 'Please Enter Tax Exemption Receipt Name.',
            'TaxExemptionReceiptName.string' => 'Invalid Tax Exemption Receipt Name.',
            'DoNotCall.required' => 'Please Select DoNotCall.',
            'DoNotCall.in' => 'Invalid DoNotCall.',
            'DoNotCall_Reason.required' => 'Please Enter DoNotCall_Reason.',
            'DoNotCall_Reason.string' => 'Invalid DoNotCall_Reason.',
            'Frequency.required' => 'Please Select Frequency.',
            'Frequency.in' => 'Invalid Frequency.',
            'Amount.required' => 'Please Enter Amount.',
            'Amount.numeric' => 'Invalid Amount.',
            'AccountNo.required' => 'Please Enter AccountNo.',
            'AccountNo.numeric' => 'Invalid AccountNo.',
            'IFSCCode.required' => 'Please Enter IFSCCode.',
            'IFSCCode.string' => 'Invalid IFSCCode.',
            'AccountHolderName.required' => 'Please Enter Account Holder Name.',
            'AccountHolderName.string' => 'Invalid Account Holder Name.',
            'JointAccountHolderName.required' => 'Please Enter Joint Account Holder Name.',
            'JointAccountHolderName.string' => 'Invalid Joint Account Holder Name.',
            'AccountType.required' => 'Please Select Account Type.',
            'AccountType.in' => 'Invalid Account Type.',
            'DebitType.required' => 'Please Select Debit Type.',
            'DebitType.in' => 'Invalid Debit Type.',
            'PledgeStartDate.required' => 'Please Select Pledge Start Date.',
            'PledgeEndDate.required' => 'Please Select Pledge End Date.',
            'OnlineTransactionID.required' => 'Please Enter Online Transaction ID.',
            'OnlineTransactionID.string' => 'Invalid Online Transaction ID.',
            'FormReceiveDate.required' => 'Please Enter Form Receive Date.',
        ];
    }

}
