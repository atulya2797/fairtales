<?php

namespace App\Http\Requests;

use App\Model\Signup;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class DonorQualityReject extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $crmId = request()->route('crmId');
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        $denieArray = [Signup::MODEOFDONATION_ONLINE, Signup::MODEOFDONATION_ENACH, Signup::MODEOFDONATION_CHEQUE];
        if ($getDonor && in_array($getDonor->ModeOfDonation, $denieArray)) {
            return [
                'reject' => 'required'
            ];
        }
        $rules = [
            'NoOfSignACopy' => 'required|integer|between:1,3',
            'NoOfSignBCopy' => 'required|integer|between:0,3',
            'Remarks' => 'required|string'
        ];

        return $rules;
    }

    public function messages() {
        return [
            'NoOfSignACopy.required' => 'Please enter No Of Sign A Copy.',
            'NoOfSignACopy.integer' => 'No Of Sign A Copy Must be integer.',
            'NoOfSignACopy.between' => 'No Of Sign A Copy Must be Between 1 and 3.',
            'NoOfSignBCopy.required' => 'Please enter No Of Sign B Copy.',
            'NoOfSignBCopy.integer' => 'No Of Sign B Copy Must be integer.',
            'NoOfSignBCopy.between' => 'No Of Sign B Copy Must be between 0 and 3.',
            'Remarks.required' => 'Please Enter Remarks.',
            'Remarks.string' => 'Remarks Must Be String.',
            'reject.required' => 'Record Can\'t reject'
        ];
    }

}
