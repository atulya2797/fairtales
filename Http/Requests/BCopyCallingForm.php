<?php

namespace App\Http\Requests;

use App\Model\Signup;
use App\Model\BCopyCalling;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class BCopyCallingForm extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $callOutCome = BCopyCalling::getCallFinalOutcome();
        $callOutComeList = implode(',', array_keys($callOutCome));
        $modeOfDonation = Signup::modeOfDonation();
        $modeOfDonationList = implode(',', array_keys($callOutCome));

        $rules = [
            'title' => 'required',
            'FirstName' => 'required|string',
            'LastName' => 'string|nullable',
            'Mobile_1' => 'phone|nullable',
            'Mobile_2' => 'phone|nullable',
            'CompanyName' => 'string|nullable',
            'Address1' => 'string|nullable',
            'Address2' => 'string|nullable',
            'Address3' => 'string|nullable',
            'Address4' => 'string|nullable',
            'Postcode' => 'string|nullable',
            'City' => 'string|nullable',
            'State' => 'string|nullable',
            'Country' => 'string|nullable',
            'eMail_Address' => 'email|nullable',
            'callFinalOutcome' => 'required|in:' . $callOutComeList,
            'ModeOfDonation' => 'nullable|in:' . $modeOfDonationList,
            'Remarks' => 'string|nullable'
        ];

        return $rules;
    }

    public function messages() {
        return [
            'title.required' => 'Please select Title.',
            'FirstName.required' => 'Please Enter First Name.',
            'FirstName.string' => 'First Name must be string.',
            'LastName.string' => 'Last Name must be string.',
            'CompanyName.string' => 'Company Name must be string.',
            'eMail_Address.email' => 'Invalid Email Address.',
            'Remarks.string' => 'Remark must be string.'
        ];
    }

}
