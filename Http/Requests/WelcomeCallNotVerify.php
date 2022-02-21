<?php

namespace App\Http\Requests;

use App\Model\Signup;
use App\Model\SignupWlcmCall;

/**
 *
 * @class BaseRequest extends FormRequest
 *
 * Notice : All the custom FormRequest method for
 * custom validation function are included in to base request.
 *
 */
class WelcomeCallNotVerify extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
//        date_format:"Y-m-dH:i"
        $input = request()->all();

        $getAllOutcome = array_keys(SignupWlcmCall::getCallOutcome());
        $appliedOutcome = array_diff($getAllOutcome, [SignupWlcmCall::Outcome_Verified]);
        $allOutcome = implode(',', $appliedOutcome);

        $rule = [
//            'SignupRemarks' => 'required|string',
            'ModeOfDonation' => 'required|in:' . Signup::MODEOFDONATION_NACH . ',' . Signup::MODEOFDONATION_ONLINE . ',' . Signup::MODEOFDONATION_CHEQUE . ',' . Signup::MODEOFDONATION_ENACH, //1-NACH,2-ONLINE,3-CHEQUE
            'FullName' => 'required|string',
            'Mobile_1' => 'required|phone',
            'CallOutcome' => 'required|in:' . $allOutcome,
            'SupLang' => 'nullable|in:' . SignupWlcmCall::SupLang_English . ',' . SignupWlcmCall::SupLang_Hingi . ',' . SignupWlcmCall::SupLang_Local,
            'IsSupAwareCause' => 'nullable|in:0,1',
            'IsSupAwareMonthly' => 'nullable|in:0,1',
            'IsEnqCanx' => 'nullable|in:0,1',
//            'Call_FinalStatusRemark' => 'required|string',
        ];

        if (isset($input['CallOutcome']) && ($input['CallOutcome'] == SignupWlcmCall::Outcome_Callback)) {
            $rule['CallBackTime'] = 'required';
        }

        if (isset($input['Mobile_2']) && ($input['Mobile_2'] != '')) {
            $rule['Mobile_2'] = 'phone';
        }

        return $rule;
    }

    public function messages() {
        return [
            'SignupRemarks.required' => 'Please Enter Signup Remarks.',
            'SignupRemarks.string' => 'Invalid Signup Remarks.',
            'ModeOfDonation.required' => 'Please Enter ModeOfDonation.',
            'ModeOfDonation.in' => 'Invalid ModeOfDonation.',
            'FullName.required' => 'Please Enter FullName.',
            'FullName' => 'Invalid FullName.',
            'Mobile_1.required' => 'Please Enter Mobile Number.',
            'CallOutcome.required' => 'Please Enter CallOutcome.',
            'CallOutcome.in' => 'Invalid CallOutcome.',
            'SupLang.required' => 'Please Select SupLang.',
            'SupLang.in' => 'Invalid SupLang.',
            'IsSupAwareCause.in' => 'Invalid IsSupAwareCause.',
            'IsSupAwareMonthly' => 'Invalid IsSupAwareMonthly.',
            'IsEnqCanx' => 'Invalid IsEnqCanx.',
            'Call_FinalStatusRemark.required' => 'Please Enter Call_FinalStatusRemark.',
            'Call_FinalStatusRemark.string' => 'Invalid Call_FinalStatusRemark.',
        ];
    }

}
