<?php

namespace App\Http\Controllers;

use App\Http\Requests\WelcomeCallProccessScoreDeduction;
use App\Http\Requests\WelcomeCallProccessUnverify;
use Session;
use App\Model\Team;
use App\Model\User;
use App\Model\Signup;
use App\Helper\Common;
use App\Model\Campaign;
use App\Model\IfscMaster;
use App\Model\CharityCode;
use App\Helper\PubNubHelper;
use App\Model\EmployeeScore;
use App\Model\EmployeeECity;
use App\Model\EmployeeEDesg;
use App\Model\SignupFormChk;
use App\Model\WlcmCallDetail;
use App\Model\ProspectMaster;
use App\Model\SignupWlcmCall;
use App\Model\EmployeeMaster;
use App\Model\EmployeeEAccess;
use App\Model\SignupDataEntry;
use App\Model\SignupAccountChk;
use App\Helper\BankAccountCheck;
use App\Http\Requests\DonorReject;
use App\Http\Requests\DonorSubmit;
use App\Http\Requests\EmployeeEdit;
use App\Http\Requests\FormReceivable;
use App\Http\Requests\RetryDonorSave;
use App\Http\Requests\EmployeeRegister;
use Illuminate\Support\Facades\Request;
use App\Http\Requests\WelcomeCallVerify;
use App\Http\Requests\DonorQualityAccept;
use App\Http\Requests\DonorQualityReject;
use App\Http\Requests\DonorQualityModify;
use App\Http\Requests\WelcomeCallRejected;
use App\Http\Requests\DonorDataEntryAccept;
use App\Http\Requests\DonorDataEntryReject;
use App\Http\Requests\WelcomeCallNotVerify;

class AdminController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
// space that we can use the repository from
    public function __construct() {
        parent::__construct();
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function employeeList() {
        $employee = $this->getEmployeeList();
        $approvalPermissionArray = [
            EmployeeEDesg::DESG_OM => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM],
            EmployeeEDesg::DESG_ADMIN => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM],
            EmployeeEDesg::DESG_SUPER_ADMIN => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN, EmployeeEDesg::DESG_TM]
        ];
        $approvalPermission = $approvalPermissionArray[$this->user->EDesg] ?? [];
        return view('admin.employeeList', compact('employee', 'approvalPermission'));
    }

    public function employeeEditView($eid) {
        $getEmployee = EmployeeMaster::where(['EID' => $eid])->first();
        $getUser = User::where(['EID' => $eid])->first();
        $getCharityCode = CharityCode::all();

        if (!$getEmployee || !$getUser) {
            abort(404);
        }

        $employeeECity = EmployeeECity::all();

        $accessListByDesg = [
            EmployeeEDesg::DESG_BO => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM],
            EmployeeEDesg::DESG_OM => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM],
            EmployeeEDesg::DESG_ADMIN => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN],
            EmployeeEDesg::DESG_SUPER_ADMIN => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
        ];

        $eDesg = EmployeeEDesg::whereIn('id', $accessListByDesg[$this->user->EDesg])->get();

        $eAccess = EmployeeEAccess::get();


        $managerDesg = $this->getManagersDesgType();
        $etlid = EmployeeMaster::select(['EID', 'EName'])
            ->whereHas('getUserInfo')
            ->where(['EDesg' => EmployeeEDesg::DESG_TL])
            ->get();
        $epmid = EmployeeMaster::select(['EID', 'EName'])
            ->whereHas('getUserInfo')
            ->where(['EDesg' => EmployeeEDesg::DESG_PM])
            ->get();
        $echid = EmployeeMaster::select(['EID', 'EName'])
            ->whereHas('getUserInfo')
            ->where(['EDesg' => EmployeeEDesg::DESG_CH])
            ->get();
        $ermid = EmployeeMaster::select(['EID', 'EName'])
            ->whereHas('getUserInfo')
            ->where(['EDesg' => EmployeeEDesg::DESG_RM])
            ->get();
        $team = Team::all();
        return view('admin.employeeEditView', compact('getEmployee', 'employeeECity', 'eDesg', 'managerDesg', 'eAccess', 'etlid', 'epmid', 'echid', 'ermid', 'team', 'getCharityCode'));
    }

    public function employeeEdit(EmployeeEdit $request, $eid) {
        $input = request()->all();

        $getEmployee = EmployeeMaster::where(['EID' => $eid])->first();
        $getUser = User::where(['EID' => $eid])->first();

        if (!$getEmployee || !$getUser) {
            abort(404);
        }
        /**
         * Create City
         */
        if (isset($input['ECityName']) && !empty($input['ECityName'])) {
            $cityData = $this->createCity($input['ECityName']);
            $input['ECity'] = $cityData->id;
        }
        unset($input['ECityName']);
        /**
         * Campaign Related to city and process(CharityCode)
         */
        $input['CampaignCode'] = NULL;
        $charityCode = $input['CharityCode'] ?? null;
        if ($charityCode) {
            $getCampaign = Campaign::where(['CityId' => $input['ECity']])
                ->where(['CharityCodeId' => $charityCode])
                ->first();
            if ($getCampaign) {
                $input['CampaignCode'] = $getCampaign->id;
            }
        }

        /**
         * ETL and EPM name
         */
        $etlId = $input['ETLID'] ?? null;
        $epmId = $input['EPMID'] ?? null;
        $echId = $input['ECHID'] ?? null;
        $ermId = $input['ERMID'] ?? null;
        if ($etlId) {
            $getEtlEmployee = EmployeeMaster::where(['EID' => $etlId])->first();
            $input['ETL'] = $getEtlEmployee->EName ?? null;
        }
        if ($epmId) {
            $getEpmEmployee = EmployeeMaster::where(['EID' => $epmId])->first();
            $input['EPM'] = $getEpmEmployee->EName ?? null;
        }
        if ($echId) {
            $getEchEmployee = EmployeeMaster::where(['EID' => $echId])->first();
            $input['ECH'] = $getEchEmployee->EName ?? null;
        }
        if ($ermId) {
            $getErmEmployee = EmployeeMaster::where(['EID' => $ermId])->first();
            $input['ERM'] = $getErmEmployee->EName ?? null;
        }
        /**
         * ETL and EPM name
         */
        /**
         * compare date of joining and date of leaving
         */
        $date1 = isset($input['EDOL']) ? $input['EDOL'] : null;
        $date2 = isset($input['EDOJ']) ? $input['EDOJ'] : null;

        /**
         * Fix date format for DOL and DOJ
         */
        $input['EDOL'] = $date1 ? Common::fixDateFormat($date1, 'd-m-Y', 'Y-m-d') : null;
        $input['EDOJ'] = $date2 ? Common::fixDateFormat($date2, 'd-m-Y', 'Y-m-d') : null;

        $format = "d/m/Y";
        $edol = \DateTime::createFromFormat($format, $date1);
        $edoj = \DateTime::createFromFormat($format, $date2);

        if ($date1 && ($edol < $edoj)) {
            return $this->sendResponse(false, '', 'Date of Leaving cannot be less then Date of joining.');
        }
        /**
         * custom error for if TL is exist or not
         */
        $userInput['name'] = $input['EName'];
        $userInput['email'] = $input['EMail'];
        $userInput['phone'] = $input['EPhoneNo'];

        if (($input['EDesg'] != $getEmployee->EDesg) || ($input['EStatus'] == EmployeeMaster::EStatusLeft)) {
            $userInput['api_token'] = null; // logout app user if employee information updated.
        }
        if (($input['password']) && ($input['confirmPassword'])) {
            $password = $input['password'];
            $bpassword = bcrypt($password);
            $userInput['password'] = $bpassword;
            $input['Epwd'] = $bpassword;
            $userInput['api_token'] = null;
        }
        unset($input['password']);
        unset($input['confirmPassword']);
        /**
         * update data
         */
        if (isset($input['TeanName']) && !empty($input['TeanName'])) {
            $teamData = $this->createTeam($input['TeanName']);
            $input['TeamId'] = $teamData->id;
        }
        unset($input['TeanName']);
        /**
         * Create Charity Code(process)
         */
        if (isset($input['CharityCodeName']) && !empty($input['CharityCodeName'])) {
            $charityCodeData = $this->createCharityCode($input['CharityCodeName']);
            $input['CharityCode'] = $charityCodeData->id;
        }
        unset($input['CharityCodeName']);
        /**
         * Create Campaign
         */
        if (isset($input['Campaign']) && !empty($input['Campaign'])) {
            $campaignData = $this->createCampaign($input['Campaign'], $input['CharityCode'], $input['ECity']);
            $input['CampaignCode'] = $campaignData->id;
        }
        unset($input['Campaign']);
        /**
         * End Adding
         */
        $input['accountStatus'] = EmployeeMaster::AccountStatusDiactivated;
        if (($input['EDesg'] == EmployeeEDesg::DESG_SUPER_ADMIN) && ($this->user->EDesg == EmployeeEDesg::DESG_SUPER_ADMIN)) {
            $input['accountStatus'] = EmployeeMaster::AccountStatusActivated;
        }
        EmployeeMaster::where(['EID' => $eid])->update($input);
        User::where(['EID' => $eid])->update($userInput);

        PubNubHelper::createChatGroups($eid);
        $this->deleteNonAssignedTeam();
        $this->deleteNonAssignedCity();
        return $this->sendResponse(true, route('employeeList'), 'Employee Updated Successfully');
    }

    public function activateEmployeeAccount($eid) {
        $input['accountStatus'] = EmployeeMaster::AccountStatusActivated;
        EmployeeMaster::where(['EID' => $eid])->update($input);
        return redirect()->back();
    }

    public function employeePasswordReset($eid) {
        $checkEid = EmployeeMaster::where(['EID' => $eid])->first();
        if (!$checkEid) {
            abort(404);
        }
        $input = uniqid();
        $password = bcrypt($input);
        User::where(['EID' => $eid])->update(['password' => $password]);
        EmployeeMaster::where(['EID' => $eid])->update(['Epwd' => $password]);
        Session::put('success', 'Password Changed Successfully, New Password Is :' . $input);
        return redirect()->route('employeeList');
    }

    public function employeeRegisterView() {
        $employeeECity = EmployeeECity::all();


        $accessListByDesg = [
            EmployeeEDesg::DESG_BO => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_TM],
            EmployeeEDesg::DESG_OM => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_TM],
            EmployeeEDesg::DESG_ADMIN => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_TM, EmployeeEDesg::DESG_ADMIN],
            EmployeeEDesg::DESG_SUPER_ADMIN => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN, EmployeeEDesg::DESG_TM],
        ];

        $eDesg = EmployeeEDesg::whereIn('id', $accessListByDesg[$this->user->EDesg])->get();

        $eAccess = EmployeeEAccess::get();
        $managerDesg = $this->getManagersDesgType();
        $getCharityCode = CharityCode::all();
        $etlid = EmployeeMaster::select(['EID', 'EName'])
            ->whereHas('getUserInfo')
            ->where(['EDesg' => EmployeeEDesg::DESG_TL])
            ->get();
        $epmid = EmployeeMaster::select(['EID', 'EName'])
            ->whereHas('getUserInfo')
            ->where(['EDesg' => EmployeeEDesg::DESG_PM])
            ->get();
        $echid = EmployeeMaster::select(['EID', 'EName'])
            ->whereHas('getUserInfo')
            ->where(['EDesg' => EmployeeEDesg::DESG_CH])
            ->get();
        $ermid = EmployeeMaster::select(['EID', 'EName'])
            ->whereHas('getUserInfo')
            ->where(['EDesg' => EmployeeEDesg::DESG_RM])
            ->get();
        $team = Team::all();
        return view('admin.employeeRegisterView', compact('eDesg', 'eAccess', 'employeeECity', 'managerDesg', 'etlid', 'epmid', 'echid', 'ermid', 'team', 'getCharityCode'));
    }

    public function employeeRegister(EmployeeRegister $request) {
        $input = request()->all();
        /**
         * Mange custom and default password
         */
        $password = uniqid();
        if (($input['password'] != '') && $input['confirmPassword'] != '') {
            $password = $input['password'];
            unset($input['confirmPassword']);
        }
        $bpassword = bcrypt($password);
        $input['password'] = $bpassword;
        $input['Epwd'] = $bpassword;
        /*         * ** */
        $input['name'] = $input['EName'];
        $input['email'] = (isset($input['EMail'])) ? $input['EMail'] : null;
        $input['phone'] = $input['EPhoneNo'];

        $input['EDOJ'] = Common::fixDateFormat($input['EDOJ'], 'd-m-Y', 'Y-m-d');
        /**
         * ETL and EPM name
         */
        $etlId = $input['ETLID'] ?? null;
        $epmId = $input['EPMID'] ?? null;
        $echId = $input['ECHID'] ?? null;
        $ermId = $input['ERMID'] ?? null;
        if ($etlId) {
            $getEtlEmployee = EmployeeMaster::where(['EID' => $etlId])->first();
            $input['ETL'] = $getEtlEmployee->EName ?? null;
        }
        if ($epmId) {
            $getEpmEmployee = EmployeeMaster::where(['EID' => $epmId])->first();
            $input['EPM'] = $getEpmEmployee->EName ?? null;
        }
        if ($echId) {
            $getEchEmployee = EmployeeMaster::where(['EID' => $echId])->first();
            $input['ECH'] = $getEchEmployee->EName ?? null;
        }
        if ($ermId) {
            $getErmEmployee = EmployeeMaster::where(['EID' => $ermId])->first();
            $input['ERM'] = $getErmEmployee->EName ?? null;
        }
        /**
         * ETL and EPM name
         */
        /**
         * Create Team
         */
        if (isset($input['TeanName']) && !empty($input['TeanName'])) {
            $teamData = $this->createTeam($input['TeanName']);
            $input['TeamId'] = $teamData->id;
        }
        unset($input['TeanName']);
        /**
         * Create City
         */
        if (isset($input['ECityName']) && !empty($input['ECityName'])) {
            $cityData = $this->createCity($input['ECityName']);
            $input['ECity'] = $cityData->id;
        }
        unset($input['ECityName']);
        /**
         * Create Charity Code(process)
         */
        if (isset($input['CharityCodeName']) && !empty($input['CharityCodeName'])) {
            $charityCodeData = $this->createCharityCode($input['CharityCodeName']);
            $input['CharityCode'] = $charityCodeData->id;
        }
        unset($input['CharityCodeName']);
        /**
         * Create Campaign
         */
        if (isset($input['Campaign']) && !empty($input['Campaign'])) {
            $campaignData = $this->createCampaign($input['Campaign'], $input['CharityCode'], $input['ECity']);
            $input['CampaignCode'] = $campaignData->id;
        }
        unset($input['Campaign']);
        /**
         * End Adding
         */
        $createEmployeeMaster = EmployeeMaster::create($input);

        /**
         * create EID, FID for employee
         */
        if ($createEmployeeMaster) {
            $getSrNo = $createEmployeeMaster->SR_NO ?? $createEmployeeMaster->id;
            $generateEID = EmployeeMaster::EID_PREFIX . $getSrNo;
            $generateFID = EmployeeMaster::FID_PREFIX . $getSrNo;
            $newUpdateData = [
                'EID' => $generateEID,
                'FID' => $generateFID,
                'accountStatus' => EmployeeMaster::AccountStatusDiactivated
            ];
            EmployeeMaster::where(['id' => $getSrNo])->update($newUpdateData);
            /**
             * create and update eid for employee
             */
            $input['EID'] = $generateEID;
            $createUser = User::create($input);
            if (!$createUser) {
                $employeeData = EmployeeMaster::where(['EID' => $generateEID])->first();
                $employeeData->delete();
                return $this->sendResponse(false, '', 'Something Went wrong please try again.');
            }
            Session::put('success', 'Register Success, EID is: ' . $generateEID . ', FID is: ' . $generateFID . ', Password is: ' . $password);
        }


        /**
         * Create Chat Group For Employee
         */
        PubNubHelper::createChatGroups($generateEID);

        $this->deleteNonAssignedTeam();
        $this->deleteNonAssignedCity();

        return $this->sendResponse(true, route('employeeRegisterView'));
    }

    /**
     * Function called on employee edit and employee create
     * this will delete all those team which is not assigned to any of the employee.
     * In this function we can also delete group made by that team but we will not, because that
     * group can be assigned to any other employee from group management in admin section
     */
    public function deleteNonAssignedTeam() {
        $getAllTeam = \App\Model\Team::get();
        foreach ($getAllTeam as $key => $val) {
            $isAssignedEmployee = EmployeeMaster::where(['TeamId' => $val->id])->first();
            if (!$isAssignedEmployee) {
                /**
                 * Delete Team because no employee assigned
                 */
                $val->delete();
            }
        }
    }

    public function deleteNonAssignedCity() {
        $getAllCity = EmployeeECity::get();
        foreach ($getAllCity as $val) {
            $getEmployee = EmployeeMaster::where(['ECity' => $val->id])->first();
            if (!$getEmployee) {
                $getCampaign = Campaign::where(['CityId' => $val->id])->first();
                if ($getCampaign) {
                    $getCampaign->delete();
                }
                $val->delete();
            }
        }
    }

    public function createTeam($teamName) {
        $data = [
            'TeamName' => $teamName
        ];
        $checkTeamName = Team::where($data)->first();
        if ($checkTeamName) {
            return $checkTeamName;
        }
        $teamData = Team::create($data);
        return $teamData;
    }

    public function createCity($cityName) {
        $cityData = EmployeeECity::where(['Ecity' => $cityName])->first();
        if (!$cityData) {
            $data = [
                'Ecity' => $cityName
            ];
            $cityData = EmployeeECity::create($data);
        }
        return $cityData;
    }

    public function createCharityCode($charityCodeName) {
        $charityCodeData = CharityCode::where(['CharityCode' => $charityCodeName])->first();
        if (!$charityCodeData) {
            $data = [
                'CharityCode' => $charityCodeName
            ];
            $charityCodeData = CharityCode::create($data);
        }
        return $charityCodeData;
    }

    public function createCampaign($campaign, $charityCodeId, $cityId) {
        $campaignData = Campaign::where([
            'CharityCodeId' => $charityCodeId,
            'CityId' => $cityId
        ])
            ->first();
        if ($campaignData && $campaign) {
            Campaign::where([
                'CharityCodeId' => $charityCodeId,
                'CityId' => $cityId
            ])
                ->update(['Campaign' => $campaign]);
            $campaignData = Campaign::where([
                'CharityCodeId' => $charityCodeId,
                'CityId' => $cityId
            ])
                ->first();
        }
        if (!$campaignData) {
            $data = [
                'CharityCodeId' => $charityCodeId,
                'CityId' => $cityId,
                'Campaign' => $campaign
            ];
            $campaignData = Campaign::create($data);
        }
        return $campaignData;
    }

    public function getEmployeeList() {
        $input = request()->all();


        $employee = EmployeeMaster::where('EID', '<>', $this->user->EID)
            ->whereHas('getUserInfo');
        if (isset($input['search']) && !empty($input['search'])) {
            $search = $this->clearString($input['search']);
            $employee = $employee->where('EName', 'LIKE', '%' . $search . '%')->orWhere('EID', 'LIKE', '%' . $input['search'] . '%');
        }

        $accessListByDesg = [
            EmployeeEDesg::DESG_BO => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM],
            EmployeeEDesg::DESG_OM => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM],
            EmployeeEDesg::DESG_ADMIN => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN],
            EmployeeEDesg::DESG_SUPER_ADMIN => [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
        ];

        if (isset($accessListByDesg[$this->user->EDesg])) {
            $employee = $employee->whereIn('EDesg', $accessListByDesg[$this->user->EDesg]);
        }

        $employee = $employee->paginate($this->pageSize);
        return $employee;
    }

    public function donorList() {
        $getDonorList = $this->getDonorList();
        $getAllRemainDonor = $getDonorList['getAllRemainDonor'];
        return view('admin.donorList', compact('getAllRemainDonor'));
    }

    public function donorSelect($crmId) {
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();
        if (!$getDonor) {
            abort(404);
        }
        $getDonorList = $this->getDonorList();
        $getAllRemainDonor = $getDonorList['getAllRemainDonor'];
        if (!$this->checkDonorIfInList($getAllRemainDonor, $crmId)) {
            abort(404);
        }
        return view('admin.donorSelect', compact('getDonor', 'getAllRemainDonor'));
    }

    public function retryDonorSave(RetryDonorSave $request, $crmId) {
        $input = request()->all();
        $input['CRM_ID'] = $crmId;
        $input['BOStatRetryTime'] = Common::fixDateFormat($input['BOStatRetryTime'], 'd-m-Y H:i:s');
        $input['BOStatUpdate'] = SignupAccountChk::STATUS_RETRY;
        $input['IFSCCode'] = isset($input['IFSCCode']) ? strtoupper($input['IFSCCode']) : null;
        $checkSignupAccountChk = SignupAccountChk::where(['CRM_ID' => $crmId])->first();
        if ($checkSignupAccountChk) {
            $checkSignupAccountChk->update($input);
        } else {
            SignupAccountChk::create($input);
        }

        Signup::where(['CRM_ID' => $crmId])
            ->update([
                'ModeOfDonation' => $input['ModeOfDonation']
            ]);

        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        /**
         * Save Employee Score Detail Start
         */
        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->CountNACHAVPending) && $getEmployeeScore->CountNACHAVPending) ? $getEmployeeScore->CountNACHAVPending + 1 : 1;
//            $employeeScore['CountNACHAVPending'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['CountNACHAVPending'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        /**
         * Save Employee Score Detail End
         */
        return $this->sendResponse(true, route('donorList'), 'Record Saved For Retry.');
    }

    public function donorAccountDetailSubmit(DonorSubmit $request, $crmId) {
        $input = request()->all();
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();
        if (!$getDonor) {
            abort(404);
        }

        $input['CRM_ID'] = $crmId;
        $input['BOStatUpdate'] = SignupAccountChk::STATUS_ACCEPTED;
        $input['IFSCCode'] = isset($input['IFSCCode']) ? strtoupper($input['IFSCCode']) : null;
        $input['BOStatRetryTime'] = Common::fixDateFormat($input['BOStatRetryTime'], 'd-m-Y H:i:s', 'Y-m-d H:i:s');

        $checkSignupAccountChk = SignupAccountChk::where(['CRM_ID' => $crmId])->first();
        if ($checkSignupAccountChk) {
            $checkSignupAccountChk->update($input);
        } else {
            SignupAccountChk::create($input);
        }

        Signup::where(['CRM_ID' => $crmId])
            ->update([
                'accountCheck' => Signup::ACCOUNT_CHECK,
                'ModeOfDonation' => $input['ModeOfDonation']
            ]);
        /**
         * Save Employee Score Detail Start
         */
        $eid = $getDonor->EID;
        $getAmmount = $input['Amount'];
        $scorePoints = SignupAccountChk::Score_Point_Price;
        $score = 0;
//        $input['Frequency'] = (int) $input['Frequency'];
        if ($input['Frequency'] == SignupAccountChk::Frequency_Monthly) {
            $createScore = $getAmmount / $scorePoints;
            $score = (float)number_format((float)$createScore, 2, '.', '');
        }
        if ($input['Frequency'] == SignupAccountChk::Frequency_OneTime) {
            $score = 1;
            if ($getAmmount < SignupAccountChk::OneTimeAmount) {
                $score = 0;
            }
        }
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();

        $updateSignupScore = [];
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->CountNACHSignup) && $getEmployeeScore->CountNACHSignup) ? ($getEmployeeScore->CountNACHSignup - 1) : (0 - 1);
//            $updateSignupScore['CountNACHSignup'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $updateSignupScore['CountNACHSignup'] = $generateScore;
        }
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_ENACH) {
            $generateScore = (isset($getEmployeeScore->CountENACHSignup) && $getEmployeeScore->CountENACHSignup) ? ($getEmployeeScore->CountENACHSignup - 1) : (0 - 1);
//            $updateSignupScore['CountENACHSignup'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $updateSignupScore['CountENACHSignup'] = $generateScore;
        }
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_ONLINE) {
            $generateScore = (isset($getEmployeeScore->CountOnlineSignup) && $getEmployeeScore->CountOnlineSignup) ? ($getEmployeeScore->CountOnlineSignup - 1) : (0 - 1);
//            $updateSignupScore['CountOnlineSignup'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $updateSignupScore['CountOnlineSignup'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($updateSignupScore)) {
                $getEmployeeScore->update($updateSignupScore);
            }
        } else {
            if (count($updateSignupScore)) {
                $updateSignupScore['CurrentDate'] = date('Y-m-d H:i:s');
                $updateSignupScore['EID'] = $eid;
                EmployeeScore::create($updateSignupScore);
            }
        }

        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->CountNACHAVSuccess) && $getEmployeeScore->CountNACHAVSuccess) ? ($getEmployeeScore->CountNACHAVSuccess + $score) : ($score);
//            $employeeScore['CountNACHAVSuccess'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['CountNACHAVSuccess'] = $generateScore;
            $employeeScore['CountNACHSignup'] = $getEmployeeScore->CountNACHSignup + $score;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ENACH) {
            $generateScore = (isset($getEmployeeScore->CountENACHAVSuccess) && $getEmployeeScore->CountENACHAVSuccess) ? ($getEmployeeScore->CountENACHAVSuccess + $score) : ($score);
//            $employeeScore['CountENACHAVSuccess'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['CountENACHAVSuccess'] = $generateScore;
            $employeeScore['CountENACHSignup'] = $getEmployeeScore->CountENACHSignup + $score;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ONLINE) {
            $generateScore = (isset($getEmployeeScore->CountOnlineAVSuccess) && $getEmployeeScore->CountOnlineAVSuccess) ? ($getEmployeeScore->CountOnlineAVSuccess + $score) : ($score);
//            $employeeScore['CountOnlineAVSuccess'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['CountOnlineAVSuccess'] = $generateScore;
            $employeeScore['CountOnlineSignup'] = $getEmployeeScore->CountOnlineSignup + $score;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }

        $donorName = $getDonor->getProspect->FullName ?? 'Donor';
        $employeeName = $getDonor->getEmployee->EName ?? 'Fr';
        $message = "$donorName enrolled by $employeeName has been successfully a/c validated.";
        PubNubHelper::publishChatToTeamGroup($getDonor->EID, $message);
        PubNubHelper::publishChatToTeamSpecificGroup($getDonor->EID, $message);
        /**
         * Send Fr rank report and city daily score report start
         */
        try {
            $consoleCommand = 'cd ' . base_path() . ' && php artisan sendFrRankReport ' . $getDonor->EID . ' && php artisan sendFrScoreReport ' . $getDonor->EID;
            exec($consoleCommand);
        } catch (\Exception $ex) {

        }
        /**
         * Send Fr rank report and city daily score report end
         */
        /**
         * Save Employee Score Detail End
         */
        return $this->sendResponse(true, route('donorList'), 'Account Verify successfully');
    }

    public function rejectDonorAccountDetail(DonorReject $request, $crmId) {
        $input = request()->all();
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();
        if (!$getDonor) {
            abort(404);
        }
        $input['CRM_ID'] = $crmId;
        $input['BOStatUpdate'] = SignupAccountChk::STATUS_REJECTED;
        $input['IFSCCode'] = isset($input['IFSCCode']) ? strtoupper($input['IFSCCode']) : null;
        $checkSignupAccountChk = SignupAccountChk::where(['CRM_ID' => $crmId])->first();
        if ($checkSignupAccountChk) {
            $checkSignupAccountChk->update($input);
        } else {
            SignupAccountChk::create($input);
        }
        Signup::where(['CRM_ID' => $crmId])
            ->update([
                'ModeOfDonation' => $input['ModeOfDonation']
            ]);
        /**
         * Save Employee Score Detail Start
         */
        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();

        $updateSignupScore = [];
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->CountNACHSignup) && $getEmployeeScore->CountNACHSignup) ? ($getEmployeeScore->CountNACHSignup - 1) : (0 - 1);
//            $updateSignupScore['CountNACHSignup'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $updateSignupScore['CountNACHSignup'] = $generateScore;
        }
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_ENACH) {
            $generateScore = (isset($getEmployeeScore->CountENACHSignup) && $getEmployeeScore->CountENACHSignup) ? ($getEmployeeScore->CountENACHSignup - 1) : (0 - 1);
//            $updateSignupScore['CountENACHSignup'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $updateSignupScore['CountENACHSignup'] = $generateScore;
        }
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_ONLINE) {
            $generateScore = (isset($getEmployeeScore->CountOnlineSignup) && $getEmployeeScore->CountOnlineSignup) ? ($getEmployeeScore->CountOnlineSignup - 1) : (0 - 1);
//            $updateSignupScore['CountOnlineSignup'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $updateSignupScore['CountOnlineSignup'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($updateSignupScore)) {
                $getEmployeeScore->update($updateSignupScore);
            }
        } else {
            if (count($updateSignupScore)) {
                $updateSignupScore['CurrentDate'] = date('Y-m-d H:i:s');
                $updateSignupScore['EID'] = $eid;
                EmployeeScore::create($updateSignupScore);
            }
        }

        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->CountNachAVReject) && $getEmployeeScore->CountNachAVReject) ? $getEmployeeScore->CountNachAVReject + 1 : 1;
//            $employeeScore['CountNachAVReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['CountNachAVReject'] = $generateScore;
            $employeeScore['CountNACHSignup'] = $getEmployeeScore->CountNACHSignup + 1;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ENACH) {
            $generateScore = (isset($getEmployeeScore->CountENachAVReject) && $getEmployeeScore->CountENachAVReject) ? $getEmployeeScore->CountENachAVReject + 1 : 1;
//            $employeeScore['CountENachAVReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['CountENachAVReject'] = $generateScore;
            $employeeScore['CountENACHSignup'] = $getEmployeeScore->CountENACHSignup + 1;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ONLINE) {
            $generateScore = (isset($getEmployeeScore->CountOnlineAVReject) && $getEmployeeScore->CountOnlineAVReject) ? $getEmployeeScore->CountOnlineAVReject + 1 : 1;
//            $employeeScore['CountOnlineAVReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['CountOnlineAVReject'] = $generateScore;
            $employeeScore['CountOnlineSignup'] = $getEmployeeScore->CountOnlineSignup + 1;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        $donorName = $getDonor->getProspect->FullName ?? 'Donor';
        $employeeName = $getDonor->getEmployee->EName ?? 'Fr';
        $rejectReason = $input['BOStatRemark'] ? 'due to ' . $input['BOStatRemark'] : null;
        $message = "$donorName enrolled by $employeeName has been rejected in a/c validation $rejectReason";
        PubNubHelper::publishChatToTeamGroup($getDonor->EID, $message);
        PubNubHelper::publishChatToTeamSpecificGroup($getDonor->EID, $message);
        /**
         * Save Employee Score Detail End
         */
        return $this->sendResponse(true, route('donorList'), 'Record Rejected From Account Validation');
    }

    public function checkBankAccount() {
        $input = request()->all();
        $identifier = null;
        $iFSC = null;
        if (isset($input['identifier']) && isset($input['iFSC'])) {
            $identifier = $input['identifier'];
            $iFSC = $input['iFSC'];
        }
        if (!$this->checkIfscCode($iFSC)) {
            return $this->sendResponse(false, '', 'IFSC Code not available in the system.');
        }
        $obj = new BankAccountCheck($identifier, $iFSC);
        $result = $obj->checkAccountVerify();
        $cdata = isset($result['data']) ? $result['data'] : '';
        return $this->sendResponse($result['status'], '', $result['message'], $cdata);
    }

    /**
     *
     * @param type $param
     * Check IFSC Code before bank account validation Api call.
     */
    public function checkIfscCode($ifsc) {
        $ifscAvailable = false;
        $checkIfscMaster = IfscMaster::where(['ifsc' => $ifsc])->first();
        if ($checkIfscMaster) {
            $ifscAvailable = true;
        }
        return $ifscAvailable;
    }

    /**
     *
     * For Call
     *
     */
    public function donorCallList() {
        $getDonorList = $this->getDonorList(Signup::ACCOUNT_CHECK);
        $getAllRemainDonor = $getDonorList['getAllRemainDonor'];
        return view('admin.donorCallList', compact('getAllRemainDonor'));
    }

    public function donorCallSelect($crmId) {
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();
        if (!$getDonor) {
            abort(404);
        }
        $getDonorList = $this->getDonorList(Signup::ACCOUNT_CHECK);
        $getAllRemainDonor = $getDonorList['getAllRemainDonor'];
        if (!$this->checkDonorIfInList($getAllRemainDonor, $crmId)) {
            abort(404);
        }
        return view('admin.donorCallSelect', compact('getDonor', 'getAllRemainDonor'));
    }

    public function welcomeCallVerify(WelcomeCallVerify $request, $crmId) {
        $input = request()->all();
        /**
         * Call Agent Detail
         */
        $input['Call_Agent'] = $this->user->EID;   //Auth User logged in
        $input['Call_TimeStamp'] = date('Y-m-d H:i:s');
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : '';  //recording save path on exotel api

        $getFirstAndLastName = explode(' ', $input['FullName']);
        $input['FirstName'] = $input['FullName'];
        $input['LastName'] = '';
        if (count($getFirstAndLastName) > 1) {
            $input['LastName'] = end($getFirstAndLastName);
            array_pop($getFirstAndLastName);
            $input['FirstName'] = implode(' ', $getFirstAndLastName);
        }

        $prospectData = [
            'Mobile_1' => $input['Mobile_1'],
            'Mobile_2' => $input['Mobile_2'],
            'FirstName' => $input['FirstName'],
            'LastName' => $input['LastName'],
            'FullName' => $input['FullName'],
        ];

        ProspectMaster::where(['CRM_ID' => $crmId])->update($prospectData);

        /**
         * Get total attempt
         */
        $getSignupWlcmCall = SignupWlcmCall::where(['CRM_ID' => $crmId])->first();
        $tempCallSid = (isset($getSignupWlcmCall->tempCallSid) && $getSignupWlcmCall->tempCallSid != null) ? $getSignupWlcmCall->tempCallSid : '';
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : $tempCallSid;
        $input['CRM_ID'] = $crmId;
        $input['Call_FinalStatus'] = SignupWlcmCall::Call_FinalStatus_verified;
        WlcmCallDetail::create($input);
        $getTotalCount = 1;
        if ($getSignupWlcmCall) {
            $getTotalCount = 0;
            $getCallDetails = WlcmCallDetail::where(['CRM_ID' => $crmId])->get();
            $dateArray = [];
            foreach ($getCallDetails as $wlcmCalDetl) {
                $getDate = Common::fixDateFormat($wlcmCalDetl->Call_TimeStamp, 'Y-m-d H:i:s', 'Y-m-d');
                if (!in_array($getDate, $dateArray)) {
                    $getTotalCount++;
                    $dateArray[] = $getDate;
                }
            }
        }
        $input['Call_Attempt'] = $getTotalCount;
        if ($getSignupWlcmCall) {
            $signupWlcmCallUpdateData = $input;
            $signupWlcmCallUpdateData['tempCallSid'] = null;
            $getSignupWlcmCall->update($signupWlcmCallUpdateData);
        } else {
            SignupWlcmCall::create($input);
        }


        $accountCheck = Signup::QUALITY_CHECK;
//        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_NACH) {
//            $accountCheck = Signup::DATA_ENTRY_CHECK;
//        }
        Signup::where(['CRM_ID' => $crmId])->update(['accountCheck' => $accountCheck, 'ModeOfDonation' => $input['ModeOfDonation']]);


        /**
         * Save Employee Score Detail Start
         */
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        $getAmmount = $getDonor->getSignupAccCheck->Amount;
        $getFrequency = $getDonor->getSignupAccCheck->Frequency;
        $scorePoints = SignupAccountChk::Score_Point_Price;
        $score = 0;
        if ($getFrequency == SignupAccountChk::Frequency_Monthly) {
            $createScore = $getAmmount / $scorePoints;
            $score = (float)number_format((float)$createScore, 2, '.', '');
        }
        if ($getFrequency == SignupAccountChk::Frequency_OneTime) {
            $score = 1;
            if ($getAmmount < SignupAccountChk::OneTimeAmount) {
                $score = 0;
            }
        }

        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->WCNachVerified) && $getEmployeeScore->WCNachVerified) ? $getEmployeeScore->WCNachVerified + $score : $score;
//            $employeeScore['WCNachVerified'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['WCNachVerified'] = $generateScore;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ENACH) {
            $generateScore = (isset($getEmployeeScore->WCENachVerified) && $getEmployeeScore->WCENachVerified) ? $getEmployeeScore->WCENachVerified + $score : $score;
//            $employeeScore['WCENachVerified'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['WCENachVerified'] = $generateScore;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ONLINE) {
            $generateScore = (isset($getEmployeeScore->WCOnlineVerified) && $getEmployeeScore->WCOnlineVerified) ? $getEmployeeScore->WCOnlineVerified + $score : $score;
//            $employeeScore['WCOnlineVerified'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['WCOnlineVerified'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        /**
         * Save Employee Score Detail End
         */
        return $this->sendResponse(true, route('donorCallList'), 'Call successfully Verified');
    }

    public function welcomeCallNotVerify(WelcomeCallNotVerify $request, $crmId) {
        $input = request()->all();
        /**
         * Call Agent Detail
         */
        $input['Call_Agent'] = $this->user->EID;
        $input['Call_TimeStamp'] = date('Y-m-d H:i:s');
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : '';  //recording save path on exotel api

        $getFirstAndLastName = explode(' ', $input['FullName']);
        $input['FirstName'] = $input['FullName'];
        $input['LastName'] = '';
        if (count($getFirstAndLastName) > 1) {
            $input['LastName'] = end($getFirstAndLastName);
            array_pop($getFirstAndLastName);
            $input['FirstName'] = implode(' ', $getFirstAndLastName);
        }
        if ($input['CallBackTime'] == '') {
            $tomorrow = new \DateTime('tomorrow');
            $tomorrowDate = $tomorrow->format('Y-m-d');
            $input['CallBackTime'] = $tomorrowDate . ' 09:00:00';
        } else {
            $input['CallBackTime'] = Common::fixDateFormat($input['CallBackTime'], 'd-m-Y H:i:s', 'Y-m-d H:i:s');
        }

        $prospectData = [
            'Mobile_1' => $input['Mobile_1'],
            'Mobile_2' => $input['Mobile_2'],
            'FirstName' => $input['FirstName'],
            'LastName' => $input['LastName'],
            'FullName' => $input['FullName'],
        ];

        ProspectMaster::where(['CRM_ID' => $crmId])->update($prospectData);

        /**
         * Get total attempt
         */
        $getSignupWlcmCall = SignupWlcmCall::where(['CRM_ID' => $crmId])->first();
        $tempCallSid = (isset($getSignupWlcmCall->tempCallSid) && $getSignupWlcmCall->tempCallSid != null) ? $getSignupWlcmCall->tempCallSid : '';
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : $tempCallSid;
        $input['CRM_ID'] = $crmId;
        $input['Call_FinalStatus'] = SignupWlcmCall::Call_FinalStatus_not_verified;
        WlcmCallDetail::create($input);
        $getTotalCount = 1;
        if ($getSignupWlcmCall) {
            $getTotalCount = 0;
            $getCallDetails = WlcmCallDetail::where(['CRM_ID' => $crmId])->get();
            $dateArray = [];
            foreach ($getCallDetails as $wlcmCalDetl) {
                $getDate = Common::fixDateFormat($wlcmCalDetl->Call_TimeStamp, 'Y-m-d H:i:s', 'Y-m-d');
                if (!in_array($getDate, $dateArray)) {
                    $getTotalCount++;
                    $dateArray[] = $getDate;
                }
            }
        }
        $input['Call_Attempt'] = $getTotalCount;
        if ($getSignupWlcmCall) {
            $signupWlcmCallUpdateData = $input;
            $signupWlcmCallUpdateData['tempCallSid'] = null;
            $getSignupWlcmCall->update($signupWlcmCallUpdateData);
        } else {
            SignupWlcmCall::create($input);
        }

        $signUpdataArray = [
            'ModeOfDonation' => $input['ModeOfDonation']
        ];
        if ($getTotalCount == SignupWlcmCall::MaxCountNotVerify) {
            $signUpdataArray['accountCheck'] = Signup::QUALITY_CHECK;
        }
        Signup::where(['CRM_ID' => $crmId])->update($signUpdataArray);

        return $this->sendResponse(true, route('donorCallList'), 'Call Not Verified and saved for later.');
    }

    public function welcomeCallRejected(WelcomeCallRejected $request, $crmId) {
        $input = request()->all();
        /**
         * Call Agent Detail
         */
        $input['Call_Agent'] = $this->user->EID;
        $input['Call_TimeStamp'] = date('Y-m-d H:i:s');
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : '';  //recording save path on exotel api

        $getFirstAndLastName = explode(' ', $input['FullName']);
        $input['FirstName'] = $input['FullName'];
        $input['LastName'] = '';
        if (count($getFirstAndLastName) > 1) {
            $input['LastName'] = end($getFirstAndLastName);
            array_pop($getFirstAndLastName);
            $input['FirstName'] = implode(' ', $getFirstAndLastName);
        }

        $prospectData = [
            'Mobile_1' => $input['Mobile_1'],
            'Mobile_2' => $input['Mobile_2'],
            'FirstName' => $input['FirstName'],
            'LastName' => $input['LastName'],
            'FullName' => $input['FullName'],
        ];

        ProspectMaster::where(['CRM_ID' => $crmId])->update($prospectData);

        /**
         * Get total attempt
         */
        $getSignupWlcmCall = SignupWlcmCall::where(['CRM_ID' => $crmId])->first();
        $tempCallSid = (isset($getSignupWlcmCall->tempCallSid) && $getSignupWlcmCall->tempCallSid != null) ? $getSignupWlcmCall->tempCallSid : '';
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : $tempCallSid;
        $input['CRM_ID'] = $crmId;
        $input['Call_FinalStatus'] = SignupWlcmCall::Call_FinalStatus_rejected;
        WlcmCallDetail::create($input);
        $getTotalCount = 1;
        if ($getSignupWlcmCall) {
            $getTotalCount = 0;
            $getCallDetails = WlcmCallDetail::where(['CRM_ID' => $crmId])->get();
            $dateArray = [];
            foreach ($getCallDetails as $wlcmCalDetl) {
                $getDate = Common::fixDateFormat($wlcmCalDetl->Call_TimeStamp, 'Y-m-d H:i:s', 'Y-m-d');
                if (!in_array($getDate, $dateArray)) {
                    $getTotalCount++;
                    $dateArray[] = $getDate;
                }
            }
        }
        $input['Call_Attempt'] = $getTotalCount;
        if ($getSignupWlcmCall) {
            $signupWlcmCallUpdateData = $input;
            $signupWlcmCallUpdateData['tempCallSid'] = null;
            $getSignupWlcmCall->update($signupWlcmCallUpdateData);
        } else {
            SignupWlcmCall::create($input);
        }

        Signup::where(['CRM_ID' => $crmId])->update(['ModeOfDonation' => $input['ModeOfDonation']]);
        /**
         * Save Employee Score Detail Start
         */
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        $getAmmount = $getDonor->getSignupAccCheck->Amount;
        $getFrequency = $getDonor->getSignupAccCheck->Frequency;
        $scorePoints = SignupAccountChk::Score_Point_Price;
        $score = 0;
        if ($getFrequency == SignupAccountChk::Frequency_Monthly) {
            $createScore = $getAmmount / $scorePoints;
            $score = (float)number_format((float)$createScore, 2, '.', '');
        }
        if ($getFrequency == SignupAccountChk::Frequency_OneTime) {
            $score = 1;
            if ($getAmmount < SignupAccountChk::OneTimeAmount) {
                $score = 0;
            }
        }

        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->WCNachReject) && $getEmployeeScore->WCNachReject) ? $getEmployeeScore->WCNachReject + $score : $score;
//            $employeeScore['WCNachReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['WCNachReject'] = $generateScore;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ENACH) {
            $generateScore = (isset($getEmployeeScore->WCENachReject) && $getEmployeeScore->WCENachReject) ? $getEmployeeScore->WCENachReject + $score : $score;
//            $employeeScore['WCENachReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['WCENachReject'] = $generateScore;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ONLINE) {
            $generateScore = (isset($getEmployeeScore->WCOnlineReject) && $getEmployeeScore->WCOnlineReject) ? $getEmployeeScore->WCOnlineReject + $score : $score;
//            $employeeScore['WCOnlineReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['WCOnlineReject'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        /**
         * Save Employee Score Detail End
         */
        $donorName = $getDonor->getProspect->FullName ?? 'Donor';
        $employeeName = $getDonor->getEmployee->EName ?? 'Fr';
        $callOutcome = $input['CallOutcome'] ?? null;
        $rejectReason = null;
        if ($callOutcome) {
            $callOutcomeText = SignupWlcmCall::getCallOutcome()[$callOutcome];
            if ($callOutcome == SignupWlcmCall::Outcome_Other) {
                $callOutcomeText = $input['Call_FinalStatusRemark'] ?? null;
            }
            $rejectReason = "due to $callOutcomeText";
        }
        $message = "$donorName enrolled by $employeeName has been rejected in welcome call $rejectReason";
        PubNubHelper::publishChatToTeamGroup($getDonor->EID, $message);

        return $this->sendResponse(true, route('donorCallList'), 'call verification rejected.');
    }

    /**
     * Process Unverified Calls
     */
    public function proceedCallUnverified($crmId, WelcomeCallProccessUnverify $request) {
        $input = request()->all();
        /**
         * Call Agent Detail
         */
        $input['Call_Agent'] = $this->user->EID;   //Auth User logged in
        $input['Call_TimeStamp'] = date('Y-m-d H:i:s');
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : '';  //recording save path on exotel api

        $getFirstAndLastName = explode(' ', $input['FullName']);
        $input['FirstName'] = $input['FullName'];
        $input['LastName'] = '';
        if (count($getFirstAndLastName) > 1) {
            $input['LastName'] = end($getFirstAndLastName);
            array_pop($getFirstAndLastName);
            $input['FirstName'] = implode(' ', $getFirstAndLastName);
        }

        $prospectData = [
            'Mobile_1' => $input['Mobile_1'],
            'Mobile_2' => $input['Mobile_2'],
            'FirstName' => $input['FirstName'],
            'LastName' => $input['LastName'],
            'FullName' => $input['FullName'],
        ];

        ProspectMaster::where(['CRM_ID' => $crmId])->update($prospectData);

        /**
         * Get total attempt
         */
        $getSignupWlcmCall = SignupWlcmCall::where(['CRM_ID' => $crmId])->first();
        $tempCallSid = (isset($getSignupWlcmCall->tempCallSid) && $getSignupWlcmCall->tempCallSid != null) ? $getSignupWlcmCall->tempCallSid : '';
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : $tempCallSid;
        $input['CRM_ID'] = $crmId;
        $input['Call_FinalStatus'] = SignupWlcmCall::Call_FinalStatus_process_unverified;
        WlcmCallDetail::create($input);
        $getTotalCount = 1;
        if ($getSignupWlcmCall) {
            $getTotalCount = 0;
            $getCallDetails = WlcmCallDetail::where(['CRM_ID' => $crmId])->get();
            $dateArray = [];
            foreach ($getCallDetails as $wlcmCalDetl) {
                $getDate = Common::fixDateFormat($wlcmCalDetl->Call_TimeStamp, 'Y-m-d H:i:s', 'Y-m-d');
                if (!in_array($getDate, $dateArray)) {
                    $getTotalCount++;
                    $dateArray[] = $getDate;
                }
            }
        }
        $input['Call_Attempt'] = $getTotalCount;
        if ($getSignupWlcmCall) {
            $signupWlcmCallUpdateData = $input;
            $signupWlcmCallUpdateData['tempCallSid'] = null;
            $getSignupWlcmCall->update($signupWlcmCallUpdateData);
        } else {
            SignupWlcmCall::create($input);
        }

        $accountCheck = Signup::QUALITY_CHECK;
        Signup::where(['CRM_ID' => $crmId])->update(['accountCheck' => $accountCheck, 'ModeOfDonation' => $input['ModeOfDonation']]);

        return $this->sendResponse(true, route('donorCallList'), 'Call Proceed Unverified.');
    }

    /**
     * Process Unverified Calls with score deductino
     */

    public function proceedScoreDeduction($crmId, WelcomeCallProccessScoreDeduction $request) {
        $input = request()->all();
        /**
         * Call Agent Detail
         */
        $input['Call_Agent'] = $this->user->EID;   //Auth User logged in
        $input['Call_TimeStamp'] = date('Y-m-d H:i:s');
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : '';  //recording save path on exotel api

        $getFirstAndLastName = explode(' ', $input['FullName']);
        $input['FirstName'] = $input['FullName'];
        $input['LastName'] = '';
        if (count($getFirstAndLastName) > 1) {
            $input['LastName'] = end($getFirstAndLastName);
            array_pop($getFirstAndLastName);
            $input['FirstName'] = implode(' ', $getFirstAndLastName);
        }

        $prospectData = [
            'Mobile_1' => $input['Mobile_1'],
            'Mobile_2' => $input['Mobile_2'],
            'FirstName' => $input['FirstName'],
            'LastName' => $input['LastName'],
            'FullName' => $input['FullName'],
        ];

        ProspectMaster::where(['CRM_ID' => $crmId])->update($prospectData);

        /**
         * Get total attempt
         */
        $getSignupWlcmCall = SignupWlcmCall::where(['CRM_ID' => $crmId])->first();
        $tempCallSid = (isset($getSignupWlcmCall->tempCallSid) && $getSignupWlcmCall->tempCallSid != null) ? $getSignupWlcmCall->tempCallSid : '';
        $input['Call_Recording'] = (isset($input['Call_Recording']) && $input['Call_Recording']) ? $input['Call_Recording'] : $tempCallSid;
        $input['CRM_ID'] = $crmId;
        $input['Call_FinalStatus'] = SignupWlcmCall::Call_FinalStatus_process_unverified;
        WlcmCallDetail::create($input);
        $getTotalCount = 1;
        if ($getSignupWlcmCall) {
            $getTotalCount = 0;
            $getCallDetails = WlcmCallDetail::where(['CRM_ID' => $crmId])->get();
            $dateArray = [];
            foreach ($getCallDetails as $wlcmCalDetl) {
                $getDate = Common::fixDateFormat($wlcmCalDetl->Call_TimeStamp, 'Y-m-d H:i:s', 'Y-m-d');
                if (!in_array($getDate, $dateArray)) {
                    $getTotalCount++;
                    $dateArray[] = $getDate;
                }
            }
        }
        $input['Call_Attempt'] = $getTotalCount;
        if ($getSignupWlcmCall) {
            $signupWlcmCallUpdateData = $input;
            $signupWlcmCallUpdateData['tempCallSid'] = null;
            $getSignupWlcmCall->update($signupWlcmCallUpdateData);
        } else {
            SignupWlcmCall::create($input);
        }

        $accountCheck = Signup::QUALITY_CHECK;
        Signup::where(['CRM_ID' => $crmId])->update(['accountCheck' => $accountCheck, 'ModeOfDonation' => $input['ModeOfDonation']]);

        /**
         * Save Employee Score Detail Start
         */
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        $getAmmount = $getDonor->getSignupAccCheck->Amount;
        $getFrequency = $getDonor->getSignupAccCheck->Frequency;
        $scorePoints = SignupAccountChk::Score_Point_Price;
        $score = 0;
        if ($getFrequency == SignupAccountChk::Frequency_Monthly) {
            $createScore = $getAmmount / $scorePoints;
            $score = (float)number_format((float)$createScore, 2, '.', '');
        }
        if ($getFrequency == SignupAccountChk::Frequency_OneTime) {
            $score = 1;
            if ($getAmmount < SignupAccountChk::OneTimeAmount) {
                $score = 0;
            }
        }

        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->WCNachReject) && $getEmployeeScore->WCNachReject) ? $getEmployeeScore->WCNachReject + $score : $score;
//            $employeeScore['WCNachReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['WCNachReject'] = $generateScore;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ENACH) {
            $generateScore = (isset($getEmployeeScore->WCENachReject) && $getEmployeeScore->WCENachReject) ? $getEmployeeScore->WCENachReject + $score : $score;
//            $employeeScore['WCENachReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['WCENachReject'] = $generateScore;
        }
        if ($input['ModeOfDonation'] == Signup::MODEOFDONATION_ONLINE) {
            $generateScore = (isset($getEmployeeScore->WCOnlineReject) && $getEmployeeScore->WCOnlineReject) ? $getEmployeeScore->WCOnlineReject + $score : $score;
//            $employeeScore['WCOnlineReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['WCOnlineReject'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        /**
         * Save Employee Score Detail End
         */
        $donorName = $getDonor->getProspect->FullName ?? 'Donor';
        $employeeName = $getDonor->getEmployee->EName ?? 'Fr';
        $message = "$donorName enrolled by $employeeName has been rejected in welcome call.";
        PubNubHelper::publishChatToTeamGroup($getDonor->EID, $message);

        return $this->sendResponse(true, route('donorCallList'), 'Call Proceed Unverified with score eduction.');
    }

    /**
     *
     * For Quality Check
     *
     */
    public function donorQualityList() {
        $getDonorList = $this->getDonorList(Signup::QUALITY_CHECK);
        $getAllRemainDonor = $getDonorList['getAllRemainDonor'];
        return view('admin.donorQualityList', compact('getAllRemainDonor'));
    }

    public function donorQualitySelect($crmId) {
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();
        if (!$getDonor) {
            abort(404);
        }
        $getDonorList = $this->getDonorList(Signup::QUALITY_CHECK);
        $getAllRemainDonor = $getDonorList['getAllRemainDonor'];
        if (!$this->checkDonorIfInList($getAllRemainDonor, $crmId)) {
            abort(404);
        }
        return view('admin.donorQualitySelect', compact('getDonor', 'getAllRemainDonor'));
    }

    public function donorQualityAccept(DonorQualityAccept $request, $crmId) {
        $input = request()->all();
        $input['CRM_ID'] = $crmId;
        $input['FFPStatus'] = SignupFormChk::FFPStatus_Accept;

        $input['IsAddresComplete'] = (isset($input['IsAddresComplete']) && ($input['IsAddresComplete'] = 'on')) ? 0 : 1;
        $input['IsPinCodeCap'] = (isset($input['IsPinCodeCap']) && ($input['IsPinCodeCap'] = 'on')) ? 0 : 1;
        $input['IsPhoneMention'] = (isset($input['IsPhoneMention']) && ($input['IsPhoneMention'] = 'on')) ? 0 : 1;
        $input['IsEmailMention'] = (isset($input['IsEmailMention']) && ($input['IsEmailMention'] = 'on')) ? 0 : 1;
        $input['IsDOBMention'] = (isset($input['IsDOBMention']) && ($input['IsDOBMention'] = 'on')) ? 0 : 1;
        $input['SupFormAckSign'] = (isset($input['SupFormAckSign']) && ($input['SupFormAckSign'] = 'on')) ? 0 : 1;
        $input['IsFidMention'] = (isset($input['IsFidMention']) && ($input['IsFidMention'] = 'on')) ? 0 : 1;
        $input['IsOverwrite'] = (isset($input['IsOverwrite']) && ($input['IsOverwrite'] = 'on')) ? 0 : 1;
        $input['BCopySub'] = (isset($input['BCopySub']) && ($input['BCopySub'] = 'on')) ? 0 : 1;
        $input['IsActionTypeTick'] = (isset($input['IsActionTypeTick']) && ($input['IsActionTypeTick'] = 'on')) ? 0 : 1;
        $input['IsAccntTypeTick'] = (isset($input['IsAccntTypeTick']) && ($input['IsAccntTypeTick'] = 'on')) ? 0 : 1;
        $input['IsDebitTypeTick'] = (isset($input['IsDebitTypeTick']) && ($input['IsDebitTypeTick'] = 'on')) ? 0 : 1;
        $input['IsAccntHldrNameMention'] = (isset($input['IsAccntHldrNameMention']) && ($input['IsAccntHldrNameMention'] = 'on')) ? 0 : 1;
        $input['IsBankNameMention'] = (isset($input['IsBankNameMention']) && ($input['IsBankNameMention'] = 'on')) ? 0 : 1;
        $input['IsPhoneEmailMentionNACH'] = (isset($input['IsPhoneEmailMentionNACH']) && ($input['IsPhoneEmailMentionNACH'] = 'on')) ? 0 : 1;
        $input['IsAmountWordFigCheck'] = (isset($input['IsAmountWordFigCheck']) && ($input['IsAmountWordFigCheck'] = 'on')) ? 0 : 1;
        $input['IsStartDateMention'] = (isset($input['IsStartDateMention']) && ($input['IsStartDateMention'] = 'on')) ? 0 : 1;
        $input['IsPostDated'] = (isset($input['IsPostDated']) && ($input['IsPostDated'] = 'on')) ? 0 : 1;

        $IsOverwrite = $input['IsOverwrite'];
        $BCopySub = $input['BCopySub'];
        if (($IsOverwrite == 0) && ($BCopySub == 0)) {
            return $this->sendResponse(false, '', 'Cant Accept, Overwriting and B Copy Both Selected.');
        }
        $IsAmountWordFigCheck = $input['IsAmountWordFigCheck'];
        if (($BCopySub == 0) && ($IsAmountWordFigCheck == 0)) {
            return $this->sendResponse(false, '', 'Cant Accept, B Copy and Figure and words mismatch Both Selected.');
        }
        $NoOfSignBCopy = $input['NoOfSignBCopy'];
        if ($NoOfSignBCopy == 0) {
            $input['BCopySub'] = 0;
        }


        $checkSignupFormChk = SignupFormChk::where(['CRM_ID' => $crmId])->first();
        if ($checkSignupFormChk) {
            SignupFormChk::where(['CRM_ID' => $crmId])->update($input);
        } else {
            SignupFormChk::create($input);
        }


        Signup::where(['CRM_ID' => $crmId])->update(['accountCheck' => Signup::DATA_ENTRY_CHECK]);


        /**
         * Save Employee Score Detail Start
         */
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        $getAmmount = $getDonor->getSignupAccCheck->Amount;
        $getFrequency = $getDonor->getSignupAccCheck->Frequency;
        $scorePoints = SignupAccountChk::Score_Point_Price;
        $score = 0;
        if ($getFrequency == SignupAccountChk::Frequency_Monthly) {
            $createScore = $getAmmount / $scorePoints;
            $score = (float)number_format((float)$createScore, 2, '.', '');
        }
        if ($getFrequency == SignupAccountChk::Frequency_OneTime) {
            $score = 1;
            if ($getAmmount < SignupAccountChk::OneTimeAmount) {
                $score = 0;
            }
        }

        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->FPNachAccept) && $getEmployeeScore->FPNachAccept) ? $getEmployeeScore->FPNachAccept + $score : $score;
//            $employeeScore['FPNachAccept'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['FPNachAccept'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        /**
         * Save Employee Score Detail End
         */
        return $this->sendResponse(true, route('donorQualityList'), 'Form Checked Successfully.');
    }

    public function donorQualityReject(DonorQualityReject $request, $crmId) {
        $input = request()->all();
        $input['CRM_ID'] = $crmId;
        $input['FFPStatus'] = SignupFormChk::FFPStatus_Reject;


        $input['IsAddresComplete'] = (isset($input['IsAddresComplete']) && ($input['IsAddresComplete'] = 'on')) ? 0 : 1;
        $input['IsPinCodeCap'] = (isset($input['IsPinCodeCap']) && ($input['IsPinCodeCap'] = 'on')) ? 0 : 1;
        $input['IsPhoneMention'] = (isset($input['IsPhoneMention']) && ($input['IsPhoneMention'] = 'on')) ? 0 : 1;
        $input['IsEmailMention'] = (isset($input['IsEmailMention']) && ($input['IsEmailMention'] = 'on')) ? 0 : 1;
        $input['IsDOBMention'] = (isset($input['IsDOBMention']) && ($input['IsDOBMention'] = 'on')) ? 0 : 1;
        $input['SupFormAckSign'] = (isset($input['SupFormAckSign']) && ($input['SupFormAckSign'] = 'on')) ? 0 : 1;
        $input['IsFidMention'] = (isset($input['IsFidMention']) && ($input['IsFidMention'] = 'on')) ? 0 : 1;
        $input['IsOverwrite'] = (isset($input['IsOverwrite']) && ($input['IsOverwrite'] = 'on')) ? 0 : 1;
        $input['BCopySub'] = (isset($input['BCopySub']) && ($input['BCopySub'] = 'on')) ? 0 : 1;
        $input['IsActionTypeTick'] = (isset($input['IsActionTypeTick']) && ($input['IsActionTypeTick'] = 'on')) ? 0 : 1;
        $input['IsAccntTypeTick'] = (isset($input['IsAccntTypeTick']) && ($input['IsAccntTypeTick'] = 'on')) ? 0 : 1;
        $input['IsDebitTypeTick'] = (isset($input['IsDebitTypeTick']) && ($input['IsDebitTypeTick'] = 'on')) ? 0 : 1;
        $input['IsAccntHldrNameMention'] = (isset($input['IsAccntHldrNameMention']) && ($input['IsAccntHldrNameMention'] = 'on')) ? 0 : 1;
        $input['IsBankNameMention'] = (isset($input['IsBankNameMention']) && ($input['IsBankNameMention'] = 'on')) ? 0 : 1;
        $input['IsPhoneEmailMentionNACH'] = (isset($input['IsPhoneEmailMentionNACH']) && ($input['IsPhoneEmailMentionNACH'] = 'on')) ? 0 : 1;
        $input['IsAmountWordFigCheck'] = (isset($input['IsAmountWordFigCheck']) && ($input['IsAmountWordFigCheck'] = 'on')) ? 0 : 1;
        $input['IsStartDateMention'] = (isset($input['IsStartDateMention']) && ($input['IsStartDateMention'] = 'on')) ? 0 : 1;
        $input['IsPostDated'] = (isset($input['IsPostDated']) && ($input['IsPostDated'] = 'on')) ? 0 : 1;

        $checkSignupFormChk = SignupFormChk::where(['CRM_ID' => $crmId])->first();
        if ($checkSignupFormChk) {
            SignupFormChk::where(['CRM_ID' => $crmId])->update($input);
        } else {
            SignupFormChk::create($input);
        }
        /**
         * Save Employee Score Detail Start
         */
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        $getAmmount = $getDonor->getSignupAccCheck->Amount;
        $getFrequency = $getDonor->getSignupAccCheck->Frequency;
        $scorePoints = SignupAccountChk::Score_Point_Price;
        $score = 0;
        if ($getFrequency == SignupAccountChk::Frequency_Monthly) {
            $createScore = $getAmmount / $scorePoints;
            $score = (float)number_format((float)$createScore, 2, '.', '');
        }
        if ($getFrequency == SignupAccountChk::Frequency_OneTime) {
            $score = 1;
            if ($getAmmount < SignupAccountChk::OneTimeAmount) {
                $score = 0;
            }
        }

        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->FPNachReject) && $getEmployeeScore->FPNachReject) ? $getEmployeeScore->FPNachReject + $score : $score;
//            $employeeScore['FPNachReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['FPNachReject'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        /**
         * Save Employee Score Detail End
         */
        $donorName = $getDonor->getProspect->FullName ?? 'Donor';
        $employeeName = $getDonor->getEmployee->EName ?? 'Fr';
        $rejectReason = $input['Remarks'] ? "due to " . $input['Remarks'] : null;
        $message = "$donorName enrolled by $employeeName has been rejected in form quality check $rejectReason";
        PubNubHelper::publishChatToTeamGroup($getDonor->EID, $message);

        return $this->sendResponse(true, route('donorQualityList'), 'Form Checked Rejected.');
    }

    public function donorQualityModify(DonorQualityModify $request, $crmId) {
        $input = request()->all();
        $input['CRM_ID'] = $crmId;
        $input['FFPStatus'] = SignupFormChk::FFPStatus_Modify;

        $input['IsAddresComplete'] = (isset($input['IsAddresComplete']) && ($input['IsAddresComplete'] = 'on')) ? 0 : 1;
        $input['IsPinCodeCap'] = (isset($input['IsPinCodeCap']) && ($input['IsPinCodeCap'] = 'on')) ? 0 : 1;
        $input['IsPhoneMention'] = (isset($input['IsPhoneMention']) && ($input['IsPhoneMention'] = 'on')) ? 0 : 1;
        $input['IsEmailMention'] = (isset($input['IsEmailMention']) && ($input['IsEmailMention'] = 'on')) ? 0 : 1;
        $input['IsDOBMention'] = (isset($input['IsDOBMention']) && ($input['IsDOBMention'] = 'on')) ? 0 : 1;
        $input['SupFormAckSign'] = (isset($input['SupFormAckSign']) && ($input['SupFormAckSign'] = 'on')) ? 0 : 1;
        $input['IsFidMention'] = (isset($input['IsFidMention']) && ($input['IsFidMention'] = 'on')) ? 0 : 1;
        $input['IsOverwrite'] = (isset($input['IsOverwrite']) && ($input['IsOverwrite'] = 'on')) ? 0 : 1;
        $input['BCopySub'] = (isset($input['BCopySub']) && ($input['BCopySub'] = 'on')) ? 0 : 1;
        $input['IsActionTypeTick'] = (isset($input['IsActionTypeTick']) && ($input['IsActionTypeTick'] = 'on')) ? 0 : 1;
        $input['IsAccntTypeTick'] = (isset($input['IsAccntTypeTick']) && ($input['IsAccntTypeTick'] = 'on')) ? 0 : 1;
        $input['IsDebitTypeTick'] = (isset($input['IsDebitTypeTick']) && ($input['IsDebitTypeTick'] = 'on')) ? 0 : 1;
        $input['IsAccntHldrNameMention'] = (isset($input['IsAccntHldrNameMention']) && ($input['IsAccntHldrNameMention'] = 'on')) ? 0 : 1;
        $input['IsBankNameMention'] = (isset($input['IsBankNameMention']) && ($input['IsBankNameMention'] = 'on')) ? 0 : 1;
        $input['IsPhoneEmailMentionNACH'] = (isset($input['IsPhoneEmailMentionNACH']) && ($input['IsPhoneEmailMentionNACH'] = 'on')) ? 0 : 1;
        $input['IsAmountWordFigCheck'] = (isset($input['IsAmountWordFigCheck']) && ($input['IsAmountWordFigCheck'] = 'on')) ? 0 : 1;
        $input['IsStartDateMention'] = (isset($input['IsStartDateMention']) && ($input['IsStartDateMention'] = 'on')) ? 0 : 1;
        $input['IsPostDated'] = (isset($input['IsPostDated']) && ($input['IsPostDated'] = 'on')) ? 0 : 1;


        $IsOverwrite = $input['IsOverwrite'];
        $BCopySub = $input['BCopySub'];
        if (($IsOverwrite == 0) && ($BCopySub == 0)) {
            return $this->sendResponse(false, '', 'Cant Accept, Overwriting and B Copy Both Selected.');
        }
        $IsAmountWordFigCheck = $input['IsAmountWordFigCheck'];
        if (($BCopySub == 0) && ($IsAmountWordFigCheck == 0)) {
            return $this->sendResponse(false, '', 'Cant Accept, B Copy and Figure and words mismatch Both Selected.');
        }
        $NoOfSignBCopy = $input['NoOfSignBCopy'];
        if ($NoOfSignBCopy == 0) {
            $input['BCopySub'] = 0;
        }

        $checkSignupFormChk = SignupFormChk::where(['CRM_ID' => $crmId])->first();
        if ($checkSignupFormChk) {
            SignupFormChk::where(['CRM_ID' => $crmId])->update($input);
        } else {
            SignupFormChk::create($input);
        }

        Signup::where(['CRM_ID' => $crmId])->update(['accountCheck' => Signup::DATA_ENTRY_CHECK]);

        /**
         * Save Employee Score Detail Start
         */
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        $getAmmount = $getDonor->getSignupAccCheck->Amount;
        $getFrequency = $getDonor->getSignupAccCheck->Frequency;
        $scorePoints = SignupAccountChk::Score_Point_Price;
        $score = 0;
        if ($getFrequency == SignupAccountChk::Frequency_Monthly) {
            $createScore = $getAmmount / $scorePoints;
            $score = (float)number_format((float)$createScore, 2, '.', '');
        }
        if ($getFrequency == SignupAccountChk::Frequency_OneTime) {
            $score = 1;
            if ($getAmmount < SignupAccountChk::OneTimeAmount) {
                $score = 0;
            }
        }

        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->FPNachModify) && $getEmployeeScore->FPNachModify) ? $getEmployeeScore->FPNachModify + $score : $score;
//            $employeeScore['FPNachModify'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['FPNachModify'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        /**
         * Save Employee Score Detail End
         */
        return $this->sendResponse(true, route('donorQualityList'), 'Form Checked Modified.');
    }

    /**
     * For Data Entry
     */
    public function donorDataEntryList() {
        $getDonorList = $this->getDonorList(Signup::DATA_ENTRY_CHECK);
        $getAllRemainDonor = $getDonorList['getAllRemainDonor'];
        return view('admin.donorDataEntryList', compact('getAllRemainDonor'));
    }

    public function donorDataEntrySelect($crmId) {
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();
        if (!$getDonor) {
            abort(404);
        }
        $getDonorList = $this->getDonorList(Signup::DATA_ENTRY_CHECK);
        $getAllRemainDonor = $getDonorList['getAllRemainDonor'];
        if (!$this->checkDonorIfInList($getAllRemainDonor, $crmId)) {
            abort(404);
        }
        return view('admin.donorDataEntrySelect', compact('getDonor', 'getAllRemainDonor'));
    }

    public function donorDataEntryAccept(DonorDataEntryAccept $request, $crmId) {
        $input = request()->all();
        $input['DoNotCall'] = (isset($input['DoNotCall']) && ($input['DoNotCall'] == 'on')) ? SignupDataEntry::DoNotCall_Yes : SignupDataEntry::DoNotCall_No;
        $input['FullName'] = $input['FirstName'] . ' ' . $input['LastName'];
        $input['CRM_ID'] = $crmId;
        $input['dataEntryStatus'] = SignupDataEntry::dataEntryStatus_Accept;
        $input['DateOfBirth'] = Common::fixDateFormat($input['DateOfBirth'], 'd-m-Y', 'Y-m-d');
        $input['PledgeStartDate'] = Common::fixDateFormat($input['PledgeStartDate'], 'd-m-Y', 'Y-m-d');
        $input['PledgeEndDate'] = Common::fixDateFormat($input['PledgeEndDate'], 'd-m-Y', 'Y-m-d');

        /**
         * Get CampaignCode
         */
        $input['CampaignCode'] = null;
        $getSignup = Signup::where(['CRM_ID' => $crmId])->first();
        if ($getSignup) {
            $input['CampaignCode'] = $getSignup->getEmployee->CampaignCode ?? null;
        }

        /**
         * Set BankName and Branch
         */
        $input['BankName'] = $input['BankName'] ?? null;
        $input['Branch'] = NULL;
        $getIfscCode = SignupAccountChk::where(['CRM_ID' => $crmId])->first();
        if ($getIfscCode && $getIfscCode->IFSCCode) {
            $getIfscDetail = IfscMaster::where(['ifsc' => $getIfscCode->IFSCCode])->first();
            $bankName = $getIfscDetail ? $getIfscDetail->bank : null;
            $input['BankName'] = $input['BankName'] ? $input['BankName'] : $bankName;
            $input['Branch'] = $getIfscDetail ? $getIfscDetail->branch : null;
        }

        $prospectData = [
            'FullName' => $input['FullName'],
            'FirstName' => $input['FirstName'],
            'LastName' => $input['LastName'],
            'Title' => $input['Title'],
            'Mobile_1' => $input['Mobile_1'],
            'Mobile_2' => $input['Mobile_2'],
            'eMail_Address' => $input['eMail_Address']
        ];

        $getProspect = ProspectMaster::where(['CRM_ID' => $crmId])->first();
        $input['Gender'] = $getProspect->Gender;

        ProspectMaster::where(['CRM_ID' => $crmId])->update($prospectData);
        SignupAccountChk::where(['CRM_ID' => $crmId])->update(['AccountHolderName_PayNimo' => $input['AccountHolderName']]);
        $checkSignupDataEntry = SignupDataEntry::where(['CRM_ID' => $crmId])->first();
        /**
         * Remove welcome call remark from input
         */
//        $wlcmCallData = $input['Call_FinalStatusRemark'] ?? '';
//        unset($input['Call_FinalStatusRemark']);
//        SignupWlcmCall::where(['CRM_ID' => $crmId])->update(['Call_FinalStatusRemark' => $wlcmCallData]);

        if ($checkSignupDataEntry) {
            $checkSignupDataEntry->update($input);
        } else {
            $input['FormType'] = SignupDataEntry::FormTypeA;
            SignupDataEntry::create($input);
        }
        Signup::where(['CRM_ID' => $crmId])->update(['accountCheck' => Signup::CALL_COMPLETE]);
        /**
         * Save Employee Score Detail Start
         */
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        $getAmmount = $getDonor->getSignupAccCheck->Amount;
        $getFrequency = $getDonor->getSignupAccCheck->Frequency;
        $scorePoints = SignupAccountChk::Score_Point_Price;
        $score = 0;
        if ($getFrequency == SignupAccountChk::Frequency_Monthly) {
            $createScore = $getAmmount / $scorePoints;
            $score = (float)number_format((float)$createScore, 2, '.', '');
        }
        if ($getFrequency == SignupAccountChk::Frequency_OneTime) {
            $score = 1;
            if ($getAmmount < SignupAccountChk::OneTimeAmount) {
                $score = 0;
            }
        }

        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->DataEntryNachAccept) && $getEmployeeScore->DataEntryNachAccept) ? $getEmployeeScore->DataEntryNachAccept + $score : $score;
//            $employeeScore['DataEntryNachAccept'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['DataEntryNachAccept'] = $generateScore;
        }
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_ENACH) {
            $generateScore = (isset($getEmployeeScore->DataEntryENachAccept) && $getEmployeeScore->DataEntryENachAccept) ? $getEmployeeScore->DataEntryENachAccept + $score : $score;
//            $employeeScore['DataEntryENachAccept'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['DataEntryENachAccept'] = $generateScore;
        }
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_ONLINE) {
            $generateScore = (isset($getEmployeeScore->DataEntryOnlineAccept) && $getEmployeeScore->DataEntryOnlineAccept) ? $getEmployeeScore->DataEntryOnlineAccept + $score : $score;
//            $employeeScore['DataEntryOnlineAccept'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['DataEntryOnlineAccept'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        /**
         * Save Employee Score Detail End
         */
        return $this->sendResponse(true, route('donorDataEntryList'), 'Data Entry Accepted.');
    }

    public function donorDataEntryReject(DonorDataEntryReject $request, $crmId) {
        $input = request()->all();

        $input['DoNotCall'] = (isset($input['DoNotCall']) && ($input['DoNotCall'] == 'on')) ? SignupDataEntry::DoNotCall_Yes : SignupDataEntry::DoNotCall_No;
        $input['FullName'] = $input['FirstName'] . ' ' . $input['LastName'];
        $input['CRM_ID'] = $crmId;
        $input['dataEntryStatus'] = SignupDataEntry::dataEntryStatus_Reject;

        /**
         * Get CampaignCode
         */
        $input['CampaignCode'] = null;
        $getSignup = Signup::where(['CRM_ID' => $crmId])->first();
        if ($getSignup) {
            $input['CampaignCode'] = $getSignup->getEmployee->CampaignCode ?? null;
        }

        /**
         * Set BankName and Branch
         */
        $input['BankName'] = $input['BankName'] ?? NULL;
        $input['Branch'] = NULL;
        $getIfscCode = SignupAccountChk::where(['CRM_ID' => $crmId])->first();
        if ($getIfscCode && $getIfscCode->IFSCCode) {
            $getIfscDetail = IfscMaster::where(['ifsc' => $getIfscCode->IFSCCode])->first();
            $bankName = $getIfscDetail ? $getIfscDetail->bank : null;
            $input['BankName'] = $input['BankName'] ? $input['BankName'] : $bankName;
            $input['Branch'] = $getIfscDetail ? $getIfscDetail->branch : null;
        }

        $maleArray = [ProspectMaster::TITLE_MR, ProspectMaster::TITLE_DR];

        $prospectData = [
            'FullName' => $input['FullName'],
            'FirstName' => $input['FirstName'],
            'LastName' => $input['LastName'],
            'Title' => $input['Title'],
            'Mobile_1' => $input['Mobile_1'],
            'Mobile_2' => $input['Mobile_2'],
            'eMail_Address' => $input['eMail_Address']
        ];
        $getProspect = ProspectMaster::where(['CRM_ID' => $crmId])->first();
        $input['Gender'] = $getProspect->Gender;

        ProspectMaster::where(['CRM_ID' => $crmId])->update($prospectData);
        SignupAccountChk::where(['CRM_ID' => $crmId])->update(['AccountHolderName_PayNimo' => $input['AccountHolderName']]);
        $checkSignupDataEntry = SignupDataEntry::where(['CRM_ID' => $crmId])->first();
        /**
         * Remove welcome call remark from input
         */
//        $wlcmCallData = $input['Call_FinalStatusRemark'] ?? '';
//        unset($input['Call_FinalStatusRemark']);
//        SignupWlcmCall::where(['CRM_ID' => $crmId])->update(['Call_FinalStatusRemark' => $wlcmCallData]);

        if ($checkSignupDataEntry) {
            $checkSignupDataEntry->update($input);
        } else {
            $input['FormType'] = SignupDataEntry::FormTypeA;
            SignupDataEntry::create($input);
        }
        /**
         * Save Employee Score Detail Start
         */
        $getDonor = Signup::where(['CRM_ID' => $crmId])->first();

        $getAmmount = $getDonor->getSignupAccCheck->Amount;
        $getFrequency = $getDonor->getSignupAccCheck->Frequency;
        $scorePoints = SignupAccountChk::Score_Point_Price;
        $score = 0;
        if ($getFrequency == SignupAccountChk::Frequency_Monthly) {
            $createScore = $getAmmount / $scorePoints;
            $score = (float)number_format((float)$createScore, 2, '.', '');
        }
        if ($getFrequency == SignupAccountChk::Frequency_OneTime) {
            $score = 1;
            if ($getAmmount < SignupAccountChk::OneTimeAmount) {
                $score = 0;
            }
        }

        $eid = $getDonor->EID;
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_NACH) {
            $generateScore = (isset($getEmployeeScore->DataEntryNachReject) && $getEmployeeScore->DataEntryNachReject) ? $getEmployeeScore->DataEntryNachReject + $score : $score;
//            $employeeScore['DataEntryNachReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['DataEntryNachReject'] = $generateScore;
        }
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_ENACH) {
            $generateScore = (isset($getEmployeeScore->DataEntryENachReject) && $getEmployeeScore->DataEntryENachReject) ? $getEmployeeScore->DataEntryENachReject + $score : $score;
//            $employeeScore['DataEntryENachReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['DataEntryENachReject'] = $generateScore;
        }
        if ($getDonor->ModeOfDonation == Signup::MODEOFDONATION_ONLINE) {
            $generateScore = (isset($getEmployeeScore->DataEntryOnlineReject) && $getEmployeeScore->DataEntryOnlineReject) ? $getEmployeeScore->DataEntryOnlineReject + $score : $score;
//            $employeeScore['DataEntryOnlineReject'] = ($generateScore <= 0 ) ? 0 : $generateScore;
            $employeeScore['DataEntryOnlineReject'] = $generateScore;
        }
        if ($getEmployeeScore) {
            if (count($employeeScore)) {
                $getEmployeeScore->update($employeeScore);
            }
        } else {
            if (count($employeeScore)) {
                $employeeScore['CurrentDate'] = date('Y-m-d H:i:s');
                $employeeScore['EID'] = $eid;
                EmployeeScore::create($employeeScore);
            }
        }
        /**
         * Save Employee Score Detail End
         */
        $donorName = $getDonor->getProspect->FullName ?? 'Donor';
        $employeeName = $getDonor->getEmployee->EName ?? 'Fr';
        $rejectReason = $input['DataEntryRemarks'] ? 'due to ' . $input['DataEntryRemarks'] : null;
        $message = "$donorName enrolled by $employeeName has been rejected in data entry $rejectReason";
        PubNubHelper::publishChatToTeamGroup($getDonor->EID, $message);

        return $this->sendResponse(true, route('donorDataEntryList'), 'Data Entry Rejected.');
    }

    public function formReceivableView(Request $request) {
        $input = request()->all();
        $cities = EmployeeECity::get();
        $teams = Team::get();
        $tableData = null;
        $file = '';
        if (request()->isMethod('post')) {
            $cityId = null;
            $teamId = null;
            if (isset($input['ECity']) && !empty($input['ECity'])) {
                $checkCity = EmployeeECity::find($input['ECity']);
                if ($checkCity) {
                    $cityId = $checkCity->id;
                }
            }
            if (isset($input['TeamId']) && !empty($input['TeamId'])) {
                $checkTeam = Team::find($input['TeamId']);
                if ($checkTeam) {
                    $teamId = $checkTeam->id;
                }
            }
            $getTableData = Signup::where(['Signup_DataEntry.dataEntryStatus' => SignupDataEntry::dataEntryStatus_Accept]);
            if ($cityId) {
                $getTableData = $getTableData->where(['ECity' => $cityId]);
            }
            if ($teamId) {
                $getTableData = $getTableData->where(['TeamId' => $teamId]);
            }
            $tableData = $getTableData->select('Signup.*', 'Employee_Master.EID', 'Employee_Master.ECity')
                ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                ->leftJoin('Signup_WlcmCall', 'Signup.CRM_ID', 'Signup_WlcmCall.CRM_ID')
                ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                ->leftJoin('Signup_FormChk','Signup.CRM_ID','Signup_FormChk.CRM_ID')
                ->where('FFPStatus','!=',SignupFormChk::FFPStatus_Reject)
                ->where(['Signup_DataEntry.FormReceiveDate' => null])
                ->where(['Signup_DataEntry.dataEntryStatus' => SignupDataEntry::dataEntryStatus_Accept])
                ->where(fn($q) => $q->where(['Signup_DataEntry.FormReceiveDate' => null])
                    ->orWhere('accountCheck', '<>', Signup::FORM_RECEIVABLE))
                ->where('Signup_WlcmCall.Call_FinalStatus', '<>', SignupWlcmCall::Call_FinalStatus_rejected)
                ->groupBy('Signup.CRM_ID')
                ->orderBy('Signup.CRM_ID', 'asc')
                ->get();
        }

        return view('admin.formReceivableView', compact('cities', 'teams', 'tableData'));
    }

    public function downloadFormReceivableData() {
        $input = request()->all();

        unset($input['_token']);

        if (!isset($input['IsReceivable'])) {
            return $this->sendResponse(false, '', 'Please Select Record.');
        }

        $crmIdList = array_keys($input['IsReceivable']);

        $getTableData = Signup::select('*');
//        $getTableData = Signup::where(['accountCheck' => Signup::CALL_COMPLETE]);
        if (count($crmIdList)) {
            $getTableData = $getTableData->whereIn('Signup.CRM_ID', $crmIdList);
        }
        $tableData = $getTableData->select('Signup.*', 'Employee_Master.EID', 'Employee_Master.ECity')
            ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
            ->leftJoin('Signup_WlcmCall', 'Signup.CRM_ID', 'Signup_WlcmCall.CRM_ID')
            ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
            ->leftJoin('Signup_FormChk','Signup.CRM_ID','Signup_FormChk.CRM_ID')
            ->where('FFPStatus','!=',SignupFormChk::FFPStatus_Reject)
            ->where(['Signup_DataEntry.FormReceiveDate' => null])
            ->where(['Signup_DataEntry.dataEntryStatus' => SignupDataEntry::dataEntryStatus_Accept])
            ->where(fn($q) => $q->where(['Signup_DataEntry.FormReceiveDate' => null])
                ->orWhere('accountCheck', '<>', Signup::FORM_RECEIVABLE))
            ->where('Signup_WlcmCall.Call_FinalStatus', '<>', SignupWlcmCall::Call_FinalStatus_rejected)
            ->groupBy('Signup.CRM_ID')
            ->orderBy('Signup.CRM_ID', 'asc')
            ->get();
        /**
         *
         *
         * Generate Excel File
         *
         *
         */
        $excelData = [];
        foreach ($tableData as $val) {
            $row['CRM_ID'] = $val->CRM_ID;
            $row['FR Name'] = $val->EName;
            $row['Donor Name'] = $val->getProspect->FirstName;
            $row['Mobile'] = $val->getProspect->Mobile_1;
            $row['Signup Date'] = Common::fixDateFormat($val->PDate, 'Y-m-d', 'd-m-Y');
            $modeOfDonation = '';
            if ($val->ModeOfDonation == Signup::MODEOFDONATION_NACH) {
                $modeOfDonation = Signup::MODEOFDONATION_NACH_TEXT;
            }
            if ($val->ModeOfDonation == Signup::MODEOFDONATION_ONLINE) {
                $modeOfDonation = Signup::MODEOFDONATION_ONLINE_TEXT;
            }
            if ($val->ModeOfDonation == Signup::MODEOFDONATION_CHEQUE) {
                $modeOfDonation = Signup::MODEOFDONATION_CHEQUE_TEXT;
            }
            if ($val->ModeOfDonation == Signup::MODEOFDONATION_ENACH) {
                $modeOfDonation = Signup::MODEOFDONATION_ENACH_TEXT;
            }
            $row['Mode Of Donation'] = $modeOfDonation;
            $ffpStatus = '';
            if ($val->getSignupFormChk->FFPStatus == SignupFormChk::FFPStatus_Accept) {
                $ffpStatus = SignupFormChk::FFPStatus_Accept_Text;
            };
            if ($val->getSignupFormChk->FFPStatus == SignupFormChk::FFPStatus_Reject) {
                $ffpStatus = SignupFormChk::FFPStatus_Reject_Text;
            };
            if ($val->getSignupFormChk->FFPStatus == SignupFormChk::FFPStatus_Modify) {
                $ffpStatus = SignupFormChk::FFPStatus_Modify_Text;
            };
            $row['FFPStatus'] = $ffpStatus;
            $getFormQualityData = SignupFormChk::select('Remarks')->where(['CRM_ID' => $val->CRM_ID])->first();
            $row['Form Quality Remark'] = $getFormQualityData->Remarks ?? '';
            $excelData[] = $row;
        }
        $file['fileUrl'] = Common::writeXlsxFile($excelData, 'Form_Receivable');
        return $this->sendResponse(true, '', 'File Export Successfully', $file);
    }

    public function formReceivableViewSubmit(FormReceivable $request) {
        $input = request()->all();
        if (count($input['IsReceivable'])) {
            foreach ($input['IsReceivable'] as $crmId => $val) {
                $checkDataExist = Signup::where(['Signup_DataEntry.dataEntryStatus' => SignupDataEntry::dataEntryStatus_Accept])
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->leftJoin('Signup_WlcmCall', 'Signup.CRM_ID', 'Signup_WlcmCall.CRM_ID')
                    ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                    ->leftJoin('Signup_FormChk','Signup.CRM_ID','Signup_FormChk.CRM_ID')
                    ->where('FFPStatus','!=',SignupFormChk::FFPStatus_Reject)
                    ->where('Signup_WlcmCall.Call_FinalStatus', '<>', SignupWlcmCall::Call_FinalStatus_rejected)
                    ->where(['Signup.CRM_ID' => $crmId])
                    ->first();
                if ($checkDataExist) {
                    Signup::where(['CRM_ID' => $crmId])->update(['accountCheck' => Signup::FORM_RECEIVABLE]);
                    SignupDataEntry::where(['CRM_ID' => $crmId])->update(['FormReceiveDate' => date('Y-m-d')]);
                }
            }
        }

        return $this->sendResponse(true, route('formReceivableView'), 'Record Update Successfully');
    }

}
