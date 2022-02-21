<?php

namespace App\Http\Requests;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class GetClientExportDataReport extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'exportId' => 'required|exists:clientDataExport,id',
            'CharityCode' => 'required|exists:CharityCode,id',
            'ModeOfDonation' => 'required',
            'dateFrom' => 'required',
            'dateTo' => 'required',
            'unverifyAttempt' => 'required|numeric|min:0|max:5'
        ];
    }

    public function messages() {
        return [
            'exportId.required' => 'Please Select Record.',
            'exportId.exists' => 'Invalid Data Selection.',
            'CharityCode.required' => 'Please Select Client.',
            'ModeOfDonation.required' => 'Please Select Mode Of Donation.',
            'dateFrom.required' => 'Please Select Signup Date From.',
            'dateTo.required' => 'Please Select Signup Date To.',
            'CharityCode.exists' => 'Invalid Client Selection',
            'unverifyAttempt.numeric' => 'Attempt must be a number.',
            'unverifyAttempt.min' => 'Attempt must be a grater then or equal to 0.',
            'unverifyAttempt.max' => 'Attempt must be a less then or equal to 5.',
        ];
    }

}
