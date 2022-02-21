<?php

namespace App\Http\Requests\Api;

use App\Model\Signup;
use App\Http\Requests\BaseRequest;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class CreateDonor extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $input = request()->all();
        $email = isset($input['eMail_Address']) ? $input['eMail_Address'] : '';
        $mobile = isset($input['Mobile_1']) ? $input['Mobile_1'] : '';

        $rule = [
            'RecordType' => 'required|in:' . Signup::RECORDTYPE_PROSPECT . ',' . Signup::RECORDTYPE_SUPPORTER, // 1-Prospect, 2-Supporter
//            'CRM_ID' => 'required',
//            'CharityCode' => 'required|in:' . Signup::CHARITYCODE_UNICEF . ',' . Signup::CHARITYCODE_CRY . ',' . Signup::CHARITYCODE_ACTION_AID, //1- UNICEF / 2-CRY / 3-Action Aid
            'CharityCode' => 'required',
//            'MethodOfFundraising' => 'in:' . Signup::METHODOFFUNDRAISING_F2F . ',' . Signup::METHODOFFUNDRAISING_D2D . ',' . Signup::METHODOFFUNDRAISING_TMA . ',' . Signup::METHODOFFUNDRAISING_TMR, //1-F2F / 2-D2D / 3-TM-A / 4-TM-R
//            'SignupRemarks' => 'required',
            'GeoLocationAcc' => 'required|string', //Geo Location
            'LocType' => 'required|in:' . Signup::LOCTYPE_PERMISSION . ',' . Signup::LOCTYPE_STREET, //1-Permission,2-Street
//            'PDate' => 'required', //Presentation Date
//            'PTime' => 'required', //Presentation Time
            'ModeOfDonation' => 'required|in:' . Signup::MODEOFDONATION_NACH . ',' . Signup::MODEOFDONATION_ONLINE . ',' . Signup::MODEOFDONATION_CHEQUE . ',' . Signup::MODEOFDONATION_ENACH, //1-NACH,2-ONLINE,3-CHEQUE
//            'Title' => 'required|in:' . Signup::TITLE_MR . ',' . Signup::TITLE_MISS . ',' . Signup::TITLE_MRS . ',' . Signup::TITLE_DR, //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
            'FirstName' => 'required|string',
            'LastName' => 'string',
            'Gender' => 'required|in:' . Signup::GENDER_MALE . ',' . Signup::GENDER_FEMALE . ',' . Signup::GENDER_OTHER, //1 - Male / 2 - Female / 3 - Other
//            'Mobile_1' => 'required|phone',
//            'eMail_Address' => 'required|email'
        ];
        if (!$email && !$mobile) {
            $rule['Mobile_1'] = 'required|phone';
            $rule['eMail_Address'] = 'required|email';
        }
        return $rule;
    }

    public function messages() {
        return [
            'RecordType.required' => 'Please Select Record Type', // 1-Prospect, 2-Supporter
            'RecordType.in' => 'Invalid Record Type', // 1-Prospect, 2-Supporter
            'CRM_ID.required' => 'Please Add CRM ID',
            'CharityCode.required' => 'Please Select Charity Code', //1- UNICEF / 2-CRY / 3-Action Aid
            'CharityCode.in' => 'Invalid Charity Code', //1- UNICEF / 2-CRY / 3-Action Aid
            'MethodOfFundraising.required' => 'Please Select Channel', //1-F2F / 2-D2D / 3-TM-A / 4-TM-R
            'MethodOfFundraising.in' => 'Invalid Method Of Fundraising', //1-F2F / 2-D2D / 3-TM-A / 4-TM-R
            'SignupRemarks.required' => 'Please add Signup Remarks',
            'GeoLocationAcc.required' => 'Geo Location Is Required', //Geo Location
            'GeoLocationAcc.string' => 'Invalid Geo Location', //Geo Location
            'LocType.required' => 'Please Select Location Type', //1-Permission,2-Street
            'LocType.in' => 'Invalid Location Type', //1-Permission,2-Street
            'PDate.required' => 'Please Select Date', //Presentation Date
            'PTime.required' => 'Please Select Time', //Presentation Time
            'ModeOfDonation.required' => 'Please Select Mode Of Donation',
            'ModeOfDonation.in' => 'Invalid Mode Of Donation',
            'Title.required' => 'Please Select Title', //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
            'Title.in' => 'Invalid Title', //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
            'FirstName.required' => 'Please Enter First Name',
            'FirstName.string' => 'Invalid First Name',
            'LastName.required' => 'Please Enter Last Name',
            'LastName.string' => 'Invalid Last Name',
            'Gender.required' => 'Please Select Gender', //1 - Male / 2 - Female / 3 - Other
            'Gender.in' => 'Invalid Gender', //1 - Male / 2 - Female / 3 - Other
            'Mobile_1.required' => 'Please Enter Mobile Number Or Email Address',
            'eMail_Address.required' => 'Please Enter Mobile Number Or Email Address',
            'eMail_Address.email' => 'Invalid Email'
        ];
    }

}
