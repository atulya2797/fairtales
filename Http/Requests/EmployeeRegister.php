<?php

namespace App\Http\Requests;

use App\Model\EmployeeEDesg;
use App\Model\EmployeeMaster;
use App\Model\EmployeeEAccess;

/**
 *
 * @class BaseRequest extends FormRequest
 *
 * Notice : All the custom FormRequest method for
 * custom validation function are included in to base request.
 *
 */
class EmployeeRegister extends BaseRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $input = request()->all();
//        print_r($input);die;
        $rules = [
            'EName' => 'required|string|max:50',
            'EDOJ' => 'required',
            'EPhoneNo' => 'required|phone',
            'Campaign' => 'nullable|string',
            'Channel' => 'nullable|in:' . EmployeeMaster::CHANNEL_F2F . ',' . EmployeeMaster::CHANNEL_D2D . ',' . EmployeeMaster::CHANNEL_TMA . ',' . EmployeeMaster::CHANNEL_TMR,
            'EDesg' => 'required',
            'EAccess' => 'required',
            'EStatus' => 'required',
            'EType' => 'required',
            'EMail' => 'nullable|email|unique:users,email',
            'password' => 'nullable',
            'confirmPassword' => 'same:password'
        ];


        if (isset($input['EDesg'])) {
            if ($input['EDesg'] == EmployeeEDesg::DESG_FR) {
                $rules['Campaign'] = 'required|string';
                $rules['Channel'] = 'in:' . EmployeeMaster::CHANNEL_F2F . ',' . EmployeeMaster::CHANNEL_D2D . ',' . EmployeeMaster::CHANNEL_TMA . ',' . EmployeeMaster::CHANNEL_TMR;
                $rules['ETLID'] = 'nullable';
                $rules['EPMID'] = 'nullable';
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_FR;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_TL) {
                $rules['EMail'] = 'required|email|unique:users,email';
                $rules['Campaign'] = 'required|string';
                $rules['Channel'] = 'in:' . EmployeeMaster::CHANNEL_F2F . ',' . EmployeeMaster::CHANNEL_D2D . ',' . EmployeeMaster::CHANNEL_TMA . ',' . EmployeeMaster::CHANNEL_TMR;
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_TL;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_STL) {
                $rules['EMail'] = 'required|email|unique:users,email';
                $rules['Campaign'] = 'required|string';
                $rules['Channel'] = 'in:' . EmployeeMaster::CHANNEL_F2F . ',' . EmployeeMaster::CHANNEL_D2D . ',' . EmployeeMaster::CHANNEL_TMA . ',' . EmployeeMaster::CHANNEL_TMR;
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_STL;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_PM) {
                $rules['EMail'] = 'required|email|unique:users,email';
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_PM;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_CH) {
                $rules['EMail'] = 'required|email|unique:users,email';
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_PM;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_RM) {
                $rules['EMail'] = 'required|email|unique:users,email';
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_PM;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_OM) {
                $rules['EMail'] = 'required|email|unique:users,email';
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_ADMIN;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_BO) {
                $rules['EPMID'] = 'required';
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_BO;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_ADMIN) {
                $rules['EMail'] = 'required|email|unique:users,email';
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_ADMIN;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_SUPER_ADMIN) {
                $rules['EMail'] = 'required|email|unique:users,email';
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_SUPER_ADMIN;
            }
            if ($input['EDesg'] == EmployeeEDesg::DESG_TM) {
                $rules['EPMID'] = 'required';
                $rules['EAccess'] = 'required|in:' . EmployeeEAccess::ACCESS_TM;
            }
        }

        $charityCode = (isset($input['CharityCode']) && ($input['CharityCode'] != '')) ? $input['CharityCode'] : '';
        $charityCodeName = (isset($input['CharityCodeName']) && ($input['CharityCodeName'] != '')) ? $input['CharityCodeName'] : '';
        if (!$charityCode && !$charityCodeName) {
            if (isset($input['EDesg']) && in_array($input['EDesg'], [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL])) {
                $rules['CharityCode'] = 'required';
            }
        }

        $ECity = (isset($input['ECity']) && ($input['ECity'] != '')) ? $input['ECity'] : '';
        $ECityName = (isset($input['ECityName']) && ($input['ECityName'] != '')) ? $input['ECityName'] : '';
        if (!$ECity && !$ECityName) {
            if (isset($input['EDesg']) && !in_array($input['EDesg'], [EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN])) {
                $rules['ECity'] = 'required';
            }
        }

        $teamId = (isset($input['TeamId']) && ($input['TeamId'] != '')) ? $input['TeamId'] : '';
        $teamName = (isset($input['TeanName']) && ($input['TeanName'] != '')) ? $input['TeanName'] : '';
        if (!$teamId && !$teamName) {
            if (isset($input['EDesg']) && in_array($input['EDesg'], [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL])) {
                $rules['TeamId'] = 'required';
            }
        }

        return $rules;
    }

    public function messages() {
        return [
            'EMail.required' => 'Please enter Employee Email.',
            'EMail.email' => 'Invalid Employee Email.',
            'EMail.unique' => 'This Employee Email already exist.',
            'EName.required' => 'Please enter Employee Name.',
            'EName.string' => 'Invalid Employee Name.',
            'EName.max' => 'Name cannot be grater then 50 characters.',
            'EID.required' => 'Please enter Employee id.',
            'EID.string' => 'Invalid Employee id:',
            'EID.unique' => 'Employee id already exist.',
            'CharityCode.required' => 'Please select Process.',
            'Channel.in' => 'Invalid Channel.',
            'Campaign.required' => 'Please enter Campaign.',
            'ECity.required' => 'Please select Employee City.',
            'EDesg.required' => 'Please select Employee Desg.',
            'EDOJ.required' => 'Please select Employee Date Of Joining.',
            'EPhoneNo.required' => 'Please enter Employee Phone.',
            'EAccess.required' => 'Please select Employee Access.',
            'EAccess.in' => 'Invalid EAccess.',
            'EStatus.required' => 'Please select Employee Status.',
            'EType.required' => 'Please select Employee Type.',
            'TeamId.required' => 'Please Select Team.',
            'ETLID.required' => 'Please Select Team Leader.',
            'EPMID.required' => 'Please Select Process Manager.'
        ];
    }

}
