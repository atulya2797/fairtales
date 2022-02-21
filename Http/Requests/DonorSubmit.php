<?php

namespace App\Http\Requests;

use App\Model\Signup;
use App\Model\EmployeeMaster;
use App\Model\SignupAccountChk;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class DonorSubmit extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $input = request()->all();
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_NACH) {
            return [
                'AccountNo' => 'required|string',
                'IFSCCode' => 'required|string',
                'AccountValidationStatus' => 'required|string',
                'AccountHolderName_PayNimo' => 'required|string',
                'AccountValidationFailReason' => 'required|string',
                'Frequency' => 'required|in:' . SignupAccountChk::Frequency_Monthly . ',' . SignupAccountChk::Frequency_OneTime,
                'Amount' => 'required|numeric'
            ];
        } else {
            return [
                'OnlineTransactionID' => 'required|string',
                'Frequency' => 'required|in:' . SignupAccountChk::Frequency_Monthly . ',' . SignupAccountChk::Frequency_OneTime,
                'Amount' => 'required|numeric'
            ];
        }
    }

    public function messages() {
        return [
            'AccountNo.required' => 'Please Enter Account Number.',
            'AccountNo.string' => 'Please Enter Valid Account Number.',
            'IFSCCode.required' => 'Please Enter IFSC Code',
            'IFSCCode.string' => 'Please Enter Valid IFSC Code',
            'AccountValidationStatus.required' => 'Please Enter Account Validation Status.',
            'AccountValidationStatus.string' => 'Account Validation Status Must be string.',
            'AccountHolderName_PayNimo.required' => 'Please Enter Account Holder Name PayNimo.',
            'AccountHolderName_PayNimo.string' => 'AccountHolder Name PayNimo Must be string.',
            'AccountValidationFailReason.required' => 'Please Enter Account Validation Fail Reason.',
            'AccountValidationFailReason.string' => 'Account Validation Fail Reason Must be string.',
            'BOStatUpdate.required' => 'Please Enter BO Stat Update',
            'BOStatUpdate.string' => 'BO Stat Update Must be string.',
            'OnlineTransactionID.required' => 'Please Enter Online Transaction ID.',
            'OnlineTransactionID.string' => 'Please Enter Valid Online Transaction ID.',
            'Frequency.required' => 'Please Select Frequency.',
            'Frequency.in' => 'Invalid Frequency.',
            'Amount.required' => 'Please Enter Amount.',
            'Amount.numeric' => 'Invalid Amount.'
        ];
    }

}
