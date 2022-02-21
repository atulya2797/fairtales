<?php

namespace App\Http\Requests;

use App\Model\Signup;
use App\Model\ProspectMaster;
use App\Model\SignupDataEntry;

/**
 *
 * @class BaseRequest extends FormRequest
 *
 * Notice : All the custom FormRequest method for
 * custom validation function are included in to base request.
 *
 */
class DonorDataEntryAccept extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $input = request()->all();
        $input['DoNotCall'] = (isset($input['DoNotCall']) && ($input['DoNotCall'] == 'on')) ? SignupDataEntry::DoNotCall_Yes : SignupDataEntry::DoNotCall_No;

        $getSignUp = Signup::where(['CRM_ID' => request()->route('crmId')])->first();

        $rule = [
//            'Call_FinalStatusRemark' => 'required|string',
            'Title' => 'required|in:' . ProspectMaster::TITLE_MR . ',' . ProspectMaster::TITLE_MISS . ',' . ProspectMaster::TITLE_MRS . ',' . ProspectMaster::TITLE_DR,
            'FirstName' => 'required|string',
            'LastName' => 'nullable|string',
            'CompanyName' => 'nullable|string',
            'Mobile_1' => 'required|phone',
            'Mobile_2' => 'nullable|phone',
            'Address1' => 'nullable|string',
            'Address2' => 'nullable|string',
            'Address3' => 'nullable|string',
            'Address4' => 'nullable|string',
            'eMail_Address' => 'nullable|email',
            'Alternate_eMail' => 'nullable|email',
            'PAN_Num' => 'nullable|string',
            'TaxExemptionReceiptName' => 'nullable|string',
            'DoNotCall_Reason' => 'nullable|string',
//            'DataEntryRemarks' => 'string',
            'PledgeStartDate' => 'required'
        ];

        if ($getSignUp->ModeOfDonation == Signup::MODEOFDONATION_NACH) {
//            $rule['Frequency'] = 'required|in:' . SignupDataEntry::Frequency_Monthly . ',' . SignupDataEntry::Frequency_OneTime;
//            $rule['Amount'] = 'required|numeric';
            $rule['AccountHolderName'] = 'nullable|string';
            $rule['JointAccountHolderName'] = 'nullable|string';
            $rule['AccountType'] = 'required|in:' . SignupDataEntry::AccountType_SB . ',' . SignupDataEntry::AccountType_Ca . ',' . SignupDataEntry::AccountType_Cc . ',' . SignupDataEntry::AccountType_SB_Nre . ',' . SignupDataEntry::AccountType_SB_Nro . ',' . SignupDataEntry::AccountType_Other;
            $rule['DebitType'] = 'required|in:' . SignupDataEntry::DebitType_FixedAmount . ',' . SignupDataEntry::DebitType_FixedAmount;
            $rule['PledgeEndDate'] = 'nullable';
        }

        if (($getSignUp->ModeOfDonation == Signup::MODEOFDONATION_NACH) || ($getSignUp->ModeOfDonation == Signup::MODEOFDONATION_ENACH)) {
//            $rule['Frequency'] = 'required|in:' . SignupDataEntry::Frequency_Monthly;
        }


        if ($input['DateOfBirth']) {
            $dob = \App\Helper\Common::fixDateFormat($input['DateOfBirth'], 'd-m-Y');

            $OutStamp = date("Y-m-d H:i:s");
            $datetime1 = new \DateTime($dob);
            $datetime2 = new \DateTime($OutStamp);
            $interval = $datetime1->diff($datetime2);

            /**
             * echo $interval->format('%Y-%m-%d %H:%i:%s');
             */
            $TotalDays = $interval->days;
            if ($TotalDays < 8401) {
                $rule['customDate'] = 'required';
            }
        }

        return $rule;
    }

    public function messages() {
        return [
            'Call_FinalStatusRemark.required' => 'Please Enter Call Final Status Remark.',
            'Call_FinalStatusRemark.string' => 'Call Final Status Remark Must Be String.',
            'Title.required' => 'Please Select Title.',
            'Title.in' => 'Invalid Title.',
            'FirstName.required' => 'Please Enter First Name.',
            'FirstName.string' => 'Invalid First Name.',
            'LastName.string' => 'Invalid Last Name.',
            'CompanyName.string' => 'Invalid Company Name.',
            'Mobile_1.required' => 'Please Enter Primary Phone Number.',
            'Mobile_1.phone' => 'Invalid Primary Phone Number.',
            'Mobile_2.phone' => 'Invalid Secondary Phone Number.',
            'Address1.string' => 'Address 1 Must be string.',
            'Address2.string' => 'Address 2 Must be string.',
            'Address3.string' => 'Address 3 Must be string.',
            'Address4.string' => 'Address 4 Must be string.',
            'eMail_Address.email' => 'Please Enter Valid Email Address.',
            'Alternate_eMail.email' => 'Please Enter Valid Alternate Email Address.',
            'PAN_Num.string' => 'PAN Number Must Be String.',
            'TaxExemptionReceiptName.string' => 'Tax Exemption Receipt Name Must Be String.',
            'DataEntryRemarks.string' => 'Please Enter Remarks.',
//            For Nach Only
            'Frequency.required' => 'Please Select Frequency.',
            'Frequency.in' => 'Invalid Frequency.',
            'Amount.required' => 'Please Enter Amount.',
            'Amount.numeric' => 'Invalid Amount.',
            'AccountHolderName.required' => 'Please Enter Account Holder Name.',
            'AccountHolderName.string' => 'Invalid Account Holder Name.',
            'JointAccountHolderName.required' => 'Please Enter Joint Account Holder Name.',
            'JointAccountHolderName' => 'Invalid Joint Account Holder Name',
            'AccountType.required' => 'Please Select Account Type.',
            'AccountType.in' => 'Invalid Account Type.',
            'DebitType.required' => 'Please Select Debit Type.',
            'DebitType.in' => 'Invalid Debit Type.',
            'customDate.required' => 'Please Change Date Of Birth.'
        ];
    }

}
