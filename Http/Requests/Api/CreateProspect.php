<?php

namespace App\Http\Requests\Api;

use App\Model\ProspectMaster;
use App\Http\Requests\BaseRequest;

/**
 * 
 * @class BaseRequest extends FormRequest
 * 
 * Notice : All the custom FormRequest method for 
 * custom validation function are included in to base request.
 * 
 */
class CreateProspect extends BaseRequest {

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
            'RecordType' => 'required|in:' . ProspectMaster::RECORDTYPE_PROSPECT . ',' . ProspectMaster::RECORDTYPE_SUPPORTER, // 1-Prospect, 2-Supporter
//            'CRM_ID' => 'required|unique:Prospect_Master,CRM_ID',
//            'CharityCode' => 'required|in:' . ProspectMaster::CHARITYCODE_UNICEF . ',' . ProspectMaster::CHARITYCODE_CRY . ',' . ProspectMaster::CHARITYCODE_ACTION_AID, //1- UNICEF / 2-CRY / 3-Action Aid
            'CharityCode' => 'required',
//            'Channel' => 'in:' . ProspectMaster::CHANNEL_F2F . ',' . ProspectMaster::CHANNEL_D2D . ',' . ProspectMaster::CHANNEL_TMA . ',' . ProspectMaster::CHANNEL_TMR, //1-F2F / 2-D2D / 3-TM-A / 4-TM-R
            'GeoLocationAcc' => 'required|string', //Geo Location
            'LocType' => 'required|in:' . ProspectMaster::LOCTYPE_PERMISSION . ',' . ProspectMaster::LOCTYPE_STREET, //1-Permission,2-Street
//            'PDate' => 'required', //Presentation Date
//            'PTime' => 'required', //Presentation Time
//            'Title' => 'required|in:' . ProspectMaster::TITLE_MR . ',' . ProspectMaster::TITLE_MISS . ',' . ProspectMaster::TITLE_MRS . ',' . ProspectMaster::TITLE_DR, //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
            'FirstName' => 'required|string',
            'LastName' => 'nullable|string',
            'Gender' => 'required|in:' . ProspectMaster::GENDER_MALE . ',' . ProspectMaster::GENDER_FEMALE . ',' . ProspectMaster::GENDER_OTHER, //1 - Male / 2 - Female / 3 - Other
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
            'CRM_ID.unique' => 'CRM ID Already registered',
            'CharityCode.required' => 'Please Select Charity Code', //1- UNICEF / 2-CRY / 3-Action Aid
            'CharityCode.in' => 'Invalid Charity Code', //1- UNICEF / 2-CRY / 3-Action Aid
            'Channel.required' => 'Please Select Channel', //1-F2F / 2-D2D / 3-TM-A / 4-TM-R
            'Channel.in' => 'Invalid Channel Code', //1-F2F / 2-D2D / 3-TM-A / 4-TM-R
            'GeoLocationAcc.required' => 'Geo Location Is Required', //Geo Location
            'GeoLocationAcc.string' => 'Invalid Geo Location', //Geo Location
            'LocType.required' => 'Please Select Location Type', //1-Permission,2-Street
            'LocType.in' => 'Invalid Location Type', //1-Permission,2-Street
            'PDate.required' => 'Please Select Date', //Presentation Date
            'PTime.required' => 'Please Select Time', //Presentation Time
            'Title.required' => 'Please Select Title', //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
            'Title.in' => 'Invalid Title', //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
            'FirstName.required' => 'Please Enter First Name',
            'FirstName.string' => 'Invalid First Name',
            'LastName.required' => 'Please Enter Last Name',
            'LastName.string' => 'Invalid Last Name',
            'Gender.required' => 'Please Select Gender', //1 - Male / 2 - Female / 3 - Other
            'Gender.in' => 'Invalid Gender', //1 - Male / 2 - Female / 3 - Other
            'Mobile_1.required' => 'Please Enter Mobile Number',
            'eMail_Address.required' => 'Please Enter Email Address',
            'eMail_Address.email' => 'Invalid Email'
        ];
    }

}
