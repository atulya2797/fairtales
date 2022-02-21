<?php

namespace App\Http\Controllers\Api\V1;

use App\Model\User;
use App\Helper\Common;
use App\Model\ChatGroups;
use App\Model\Attendance;
use Illuminate\Support\Str;
use App\Model\ChatTeamGroup;
use App\Model\ChatCityGroup;
use Illuminate\Http\Request;
use App\Model\EmployeeEDesg;
use App\Model\EmployeeMaster;
use App\Model\EmployeeEAccess;
use Illuminate\Support\Carbon;
use App\Model\AttendanceStamps;
use App\Http\Requests\ResetPassword;
use App\Http\Controllers\Api\ApiController;

class AuthController extends ApiController {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
//        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function login(Request $request) {
        $credential = [
            'EID' => $request->input('eid'),
            'password' => $request->input('password')
        ];

        if (auth()->attempt($credential)) {
            // Authentication passed...
            $user = auth()->user();
            $user->api_token = Str::random(80);
            $user->save();
            $getEmployeMaster = EmployeeMaster::where(['EID' => $user->EID])->first();
            if (!$getEmployeMaster) {
                auth()->logout();
                return $this->jsonResponse(false, 401, "Unauthenticated.", []);
            }
            if ($getEmployeMaster->EStatus == EmployeeMaster::EStatusLeft) {
                auth()->logout();
                return $this->jsonResponse(false, 401, "Employee Left.", []);
            }
            if ($getEmployeMaster->accountStatus == EmployeeMaster::AccountStatusDiactivated) {
                auth()->logout();
                return $this->jsonResponse(false, 401, "Need account approval.", []);
            }
            $allowedUser = [EmployeeEAccess::ACCESS_FR, EmployeeEAccess::ACCESS_TL, EmployeeEAccess::ACCESS_STL, EmployeeEAccess::ACCESS_PM, EmployeeEAccess::ACCESS_ADMIN, EmployeeEAccess::ACCESS_SUPER_ADMIN];
            if (!in_array($getEmployeMaster->EAccess, $allowedUser)) {
                auth()->logout();
                return $this->jsonResponse(false, 401, "Unauthenticated.", []);
            }
            $getEmployeMaster->api_token = $user->api_token;
            $getEmployeMaster->groupDetail = $this->getGroupDetail($user->EID);
            $this->user = $getEmployeMaster;
            $this->checkIfFundraiser();
            return $this->jsonResponse(true, 200, "Login Success", $getEmployeMaster);
        }

        return $this->jsonResponse(false, 401, "Unauthenticated.", []);
    }

    public function chatGroupDetails() {
        if ($this->user) {
            $getGroupDetail = $this->getGroupDetail($this->user->EID);
            return $this->jsonResponse(true, 200, 'Group Details', $getGroupDetail);
        }
        return $this->jsonResponse(true, 200, 'Group Details');
    }

    public function getGroupDetail($eid) {
        $getTeamGroups = ChatTeamGroup::select('GrpName', 'GrpId')->where(['EID' => $eid])->groupBy('GrpId')->get();
        $getTeamGroupDetail = $this->getMemberDetail($getTeamGroups, ChatGroups::TypeTeam);
        $getCityGroups = ChatCityGroup::select('GrpName', 'GrpId')->where(['EID' => $eid])->groupBy('GrpId')->get();
        $getCityGroupDetail = $this->getMemberDetail($getCityGroups, ChatGroups::TypeCity);
        $groups = [
            'TeamGroup' => $getTeamGroupDetail,
            'CityGroup' => $getCityGroupDetail
        ];
        return $groups;
    }

    public function getMemberDetail($getGroups, $type) {
        foreach ($getGroups as $key => $val) {
            if ($type == ChatGroups::TypeCity) {
                $getMemberDetail = ChatCityGroup::where(['GrpId' => $val->GrpId])->groupBy('EID')->get();
            } else {
                $getMemberDetail = ChatTeamGroup::where(['GrpId' => $val->GrpId])->groupBy('EID')->get();
            }
            $getGrpDetail = ChatGroups::where(['GrpId' => $val->GrpId])->first();
            $getFixedData = $getGrpDetail->created_at ? Common::fixDateFormat($getGrpDetail->created_at, 'Y-m-d H:i:s') : '';
            $groupCreatedDate = $getFixedData ?? '';
            $members = [];
            foreach ($getMemberDetail as $detail) {
                $employeeDetail = EmployeeMaster::where(['EID' => $detail->EID])
                    ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                    ->first();
                if ($employeeDetail) {
                    $memberData['EID'] = $detail->EID;
                    $memberData['EName'] = $employeeDetail->EName ?? '';
                    $memberData['EDesg'] = $employeeDetail->getDesg->EDesg ?? '';
                    $members[] = $memberData;
                }
            }
            $getGroups[$key]['createdAt'] = $groupCreatedDate;
            $getGroups[$key]['groupMembers'] = $members;
        }
        return $getGroups;
    }

    public function checkIfFundraiser() {
        if ($this->user) {
//            if ($this->user->EDesg == ) {
            if (in_array($this->user->EDesg, [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL])) {
                $update = ['password' => ''];
                User::where(['EID' => $this->user->EID])->update($update);
                $update = ['Epwd' => ''];
                EmployeeMaster::where(['EID' => $this->user->EID])->update($update);
            }
        }
    }

    public function user() {
        $user = $this->user;
        return $this->jsonResponse(true, 200, "User Detail", $user);
    }

    public function resetPassword(ResetPassword $request) {
        $generetePassword = bcrypt($request->input('password'));
        $user = auth()->user();
        $user->password = $generetePassword;
        $user->save();
        EmployeeMaster::where(['EID' => $this->user->EID])->upate(['Epwd' => $generetePassword]);
        return $this->jsonResponse(true, 200, "Password Reset Successfully", $user);
    }

    public function profileUpdate(Request $request) {
        $authUser = $this->user;

        $password = request()->input('Epwd');

        $getUniqueEmail = request()->input('EMail');
        $checkUniqueEmail = User::where(['email' => $getUniqueEmail])
            ->where('EID', '<>', $authUser->EID)
            ->first();
//        if ($checkUniqueEmail) {
//            return $this->jsonResponse(false, 422, "Invalid Input Data", ['EMail' =>]);
//        }

        $userData = [
            'name' => request()->input('EName'),
            'email' => request()->input('EMail'),
            'phone' => request()->input('EPhoneNo'),
            'password' => bcrypt($password),
        ];
        User::where(['EID' => $authUser->EID])->update($userData);
        $employeeData = [
            'EName' => request()->input('EName'),
            'EMail' => request()->input('EMail'),
            'EPhoneNo' => request()->input('EPhoneNo'),
            'Epwd' => bcrypt($password),
        ];
        EmployeeMaster::where(['EID' => $authUser->EID])->update($employeeData);
        return $this->jsonResponse(true, 200, "Profile Update Successfully", $authUser);
    }

    public function refreshToken(Request $request) {
        $token = Str::random(80);
        $user = auth()->user();
        $user->api_token = $token;
        $user->save();

        $getUser = EmployeeMaster::where(['EID' => $this->user->EID])->first();
        $getUser->api_token = $token;
        $user = $getUser;

        return $this->jsonResponse(true, 200, "Token Changed", $user);
    }

    public function logout() {
        $user = $this->user;
        $input = request()->all();
        $OutStamp = date("Y-m-d H:i:s");

        $getAttendance = Attendance::where(['EID' => $user->EID])
            ->whereDate('InStamp', date('Y-m-d'))
            ->first();

        if (!$getAttendance) {
            return $this->jsonResponse(false, 422, "You must day start first.", []);
        }

        AttendanceStamps::where(['attendance_id' => $getAttendance->SR_NO, 'OutStamp' => null])
            ->update(['OutStamp' => $OutStamp]);

//        $datetime1 = new \DateTime($getAttendance->InStamp);
//        $datetime2 = new \DateTime($OutStamp);
//        $interval = $datetime1->diff($datetime2);
////        echo $interval->format('%Y-%m-%d %H:%i:%s');
//        $TotProdHrs = $interval->format('%H:%i:%s');


        $TotProdHrs = $this->getProductionHour($getAttendance->SR_NO);


//        If TotProdHrs>4 $$ <6, HD TotProdHrs>6,P
        $attendance = Attendance::ATTENDANCE_A;
        if ($TotProdHrs >= 3 && $TotProdHrs < 6) {
            $attendance = Attendance::ATTENDANCE_HD;
        } elseif ($TotProdHrs > 6) {
            $attendance = Attendance::ATTENDANCE_P;
        }

        $allRequest = [
            'OutStamp' => $OutStamp,
            'Attendance' => $attendance,
            'TotProdHrs' => $TotProdHrs,
            'InOutDist' => null, //*to be discussed
            /**
             *
             * From input
             *
             */
            'OutLoc' => $input['OutLoc'], //Geo Location
        ];
        /**
         * check for team leader to all there member are check out
         */
        /**
         * Confirm Team Members Attendance Start
         */
        $getEmployeeData = EmployeeMaster::where(['EID' => $user->EID])->first();
        if ($getEmployeeData->EDesg == EmployeeEDesg::DESG_TL) {
            $totalMemberEid = $this->attendanceMemberEid();
            if (!empty($totalMemberEid)) {
                $checkRemainAttandance = Attendance::where('EID', '<>', $user->EID)
                    ->whereIn('EID', $totalMemberEid)
                    ->whereDate('InStamp', date('Y-m-d'))
                    ->where(function ($q) {
                        return $q->where('AttnApproveBy', '=', null)
                            ->orWhere('AttnApproveBy', '=', '');
                    })
                    ->count();
                if ($checkRemainAttandance) {
                    return $this->jsonResponse(false, 422, "Please Confirm All your team member Attendance", []);
                }
            }
        }
        /**
         * Confirm Team Members Attendance End
         */
        Attendance::where(['EID' => $user->EID])
            ->whereDate('created_at', Carbon::today())
            ->update($allRequest);
        User::where(['EID' => $user->EID])->update(['api_token' => null]);
        return $this->jsonResponse(true, 200, "User Logged Out", []);
    }

    public function endDay() {
        $user = $this->user;
        $input = request()->all();
        $whereToday = date('Y-m-d');
        $OutStamp = date("Y-m-d H:i:s");

        $getAttendance = Attendance::where(['EID' => $user->EID])
            ->whereDate('InStamp', $whereToday)
            ->first();
        if (!$getAttendance) {
            return $this->jsonResponse(false, 422, "You must day start first.", []);
        }
        /**
         * save AttendanceStamps outstamp start
         */
        AttendanceStamps::where(['attendance_id' => $getAttendance->SR_NO, 'OutStamp' => null])
            ->update(['OutStamp' => $OutStamp]);
        /**
         * save AttendanceStamps outstamp end
         */
//        $datetime1 = new \DateTime($getAttendance->InStamp);
//        $datetime2 = new \DateTime($OutStamp);
//        $interval = $datetime1->diff($datetime2);
////        echo $interval->format('%Y-%m-%d %H:%i:%s');
//        $TotProdHrs = $interval->format('%H:%i:%s');

        $TotProdHrs = $this->getProductionHour($getAttendance->SR_NO);


//        If TotProdHrs>4 $$ <6, HD TotProdHrs>6,P
        $attendance = Attendance::ATTENDANCE_A;
        if ($TotProdHrs >= 3 && $TotProdHrs < 6) {
            $attendance = Attendance::ATTENDANCE_HD;
        } elseif ($TotProdHrs >= 6) {
            $attendance = Attendance::ATTENDANCE_P;
        }

        $allRequest = [
            'OutStamp' => $OutStamp,
            'Attendance' => $attendance,
            'TotProdHrs' => $TotProdHrs,
            'InOutDist' => null, //*to be discussed
            /**
             *
             * From input
             *
             */
            'OutLoc' => $input['OutLoc'], //Geo Location
        ];
        /**
         * Confirm Team Members Attendance Start
         */
        $getEmployeeData = EmployeeMaster::where(['EID' => $user->EID])->first();
        if ($getEmployeeData->EDesg == EmployeeEDesg::DESG_TL) {
            $totalMemberEid = $this->attendanceMemberEid();
            if (!empty($totalMemberEid)) {
                $checkRemainAttandance = Attendance::where('EID', '<>', $user->EID)
                    ->whereIn('EID', $totalMemberEid)
                    ->whereDate('InStamp', $whereToday)
                    ->where(function ($q) {
                        return $q->where('AttnApproveBy', '=', null)
                            ->orWhere('AttnApproveBy', '=', '');
                    })
                    ->count();
                /**
                 *
                 * Check if there member started there day
                 *
                 */
//                $checkMemberAttendanceForToday = Attendance::where('EID', '<>', $user->EID)
//                        ->whereIn('EID', $totalMemberEid)
//                        ->whereDate('InStamp', $whereToday)
//                        ->count();
//                if ($checkRemainAttandance || ($checkMemberAttendanceForToday == 0)) {
                if ($checkRemainAttandance) {
                    return $this->jsonResponse(false, 422, "Please Confirm All your team member Attendance", []);
                }
            }
        }
        /**
         * Confirm Team Members Attendance End
         */
        Attendance::where(['EID' => $user->EID])
            ->whereDate('InStamp', $whereToday)
            ->update($allRequest);

        return $this->jsonResponse(true, 200, "Day Ended.", []);
    }

    public function getProductionHour($attendanceId) {
        $getAllLoginAttemps = \App\Model\AttendanceStamps::where(['attendance_id' => $attendanceId])->get();
        $totalProdHour = '00:00:00';
        foreach ($getAllLoginAttemps as $val) {
            $datetime1 = new \DateTime($val->InStamp);
            $datetime2 = new \DateTime($val->OutStamp);
            $interval = $datetime1->diff($datetime2);
            $hour = $interval->format('%H');
            $min = $interval->format('%i');
            $sec = $interval->format('%s');

            /**
             *
             *
             */
            $t1 = new \DateTime($totalProdHour);
            $new = $t1->add(new \DateInterval('PT' . $hour . 'H' . $min . 'M' . $sec . 'S'));
            $totalProdHour = $new->format('H:i:s');
        }

        return $totalProdHour;
    }

    public function attendanceMemberEid() {
        $authUser = $this->user;
        $managersDesg = [
            EmployeeEDesg::DESG_TL => 'ETLID',
            EmployeeEDesg::DESG_PM => 'EPMID',
            EmployeeEDesg::DESG_CH => 'ECHID',
            EmployeeEDesg::DESG_RM => 'ERMID'
        ];
        $col = $managersDesg[$authUser->EDesg];
        $allMembers = EmployeeMaster::select(['EID'])
            ->whereHas('getUserInfo')
            ->where([$col => $authUser->EID])
            ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
            ->get()
            ->toArray();
        $allMembersEid = [];
        if ($allMembers) {
            $allMembersEid = array_column($allMembers, 'EID');
        }
        return $allMembersEid;
    }

}
