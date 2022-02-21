<?php

namespace App\Http\Controllers\Api\V1;

use App\Model\Signup;
use App\Helper\Common;
use App\Model\Attendance;
use App\Model\EmployeeScore;
use App\Model\EmployeeEDesg;
use App\Model\EmployeeMaster;
use App\Model\ProspectMaster;
use Illuminate\Support\Carbon;
use App\Model\AttendanceStamps;
use Illuminate\Support\Facades\DB;
use App\Model\EmployeeTargetQaulity;
use App\Http\Requests\Api\CreateDonor;
use App\Http\Requests\MemberAttendance;
use App\Http\Requests\Api\CreateProspect;
use App\Http\Requests\Api\MarkAttendance;
use App\Http\Controllers\Api\ApiController;

class FundraiserController extends ApiController {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
//        $this->middleware('auth');
    }

    public function markAttendance(MarkAttendance $request) {
        $input = request()->all();
        $authUser = $this->user;

        $allRequest = [
            'EID' => $authUser->EID,
            'EName' => $authUser->EName,
            'ECity' => $authUser->ECity,
            'InStamp' => date("Y-m-d H:i:s"),
            /**
             *
             * request from Application
             *
             */
            'InLoc' => $input['InLoc'], //geocode
            'ManualLoc' => $input['ManualLoc'],
            'LocType' => $input['LocType'], //1-Permission ,2-Street
            'LogInIP' => $input['LogInIP'] //login Ip Address
        ];

        $checkAttendance = Attendance::where(['EID' => $authUser->EID])
            ->whereDate('InStamp', date('Y-m-d'))
            ->first();

        $AttendanceStampsData = [
            'InStamp' => $allRequest['InStamp']
        ];

        if ($checkAttendance) {
            $attendanceId = $checkAttendance->SR_NO ?: $checkAttendance->id;
            $AttendanceStampsData['attendance_id'] = $attendanceId;
            $allRequest['OutStamp'] = null;
            Attendance::where(['SR_NO' => $checkAttendance->SR_NO])->update($allRequest);
            $message = 'Attendance Already Started.';
        } else {
            $attendanceData = Attendance::create($allRequest);
            $AttendanceStampsData['attendance_id'] = $attendanceData->SR_NO ?: $attendanceData->id;
            $message = 'Attendance Started.';
        }
        /* check attendance stamp if he ended day then insert data for new stamp else leave empty */
        $checkStamp = AttendanceStamps::where(['attendance_id' => $AttendanceStampsData['attendance_id'], 'OutStamp' => null])->count();
        if (!$checkStamp) {
            AttendanceStamps::create($AttendanceStampsData);
        }

        return $this->jsonResponse(true, 200, $message);
    }

    public function generateProspect($input) {
        $authUser = $this->user;

        /**
         * For as a team member register
         */
        if (isset($input['EID']) && ($input['EID'] != '')) {
            $eid = $input['EID'];
            unset($input['EID']);
            $getEmployeeByEid = EmployeeMaster::where(['EID' => $eid])->first();
            $authUser = $getEmployeeByEid;
        }

        $lastName = (isset($input['LastName'])) ? $input['LastName'] : '';

        $allRequest = [
            'EID' => $authUser->EID,
            'FID' => $authUser->FID,
            'EName' => $authUser->EName,
            'ETLID' => $authUser->ETLID,
            'EPMID' => $authUser->EPMID,
            'ETL' => $authUser->ETL,
            'EPM' => $authUser->EPM,
            /**
             *
             * request from Application
             *
             */
            'RecordType' => $input['RecordType'], // 1-Prospect, 2-Supporter
            'CharityCode' => $input['CharityCode'], //1- UNICEF / 2-CRY / 3-Action Aid
            'GeoLocationAcc' => $input['GeoLocationAcc'], //Geo Location
            'LocType' => $input['LocType'], //1-Permission,2-Street
            'PDate' => date("Y-m-d"), //Presentation Date
            'PTime' => date("H:i:s"), //Presentation Time
            'Channel' => ProspectMaster::CHANNEL_F2F, //1-F2F / 2-D2D / 3-TM-A / 4-TM-R
            'FullName' => $input['FirstName'] . ' ' . $lastName,
            'FirstName' => $input['FirstName'],
            'LastName' => $lastName,
            'Gender' => $input['Gender'], //1 - Male / 2 - Female / 3 - Other
            'Mobile_1' => isset($input['Mobile_1']) ? $input['Mobile_1'] : '',
            'eMail_Address' => isset($input['eMail_Address']) ? $input['eMail_Address'] : '',
            'tempUid' => isset($input['tempUid']) ? $input['tempUid'] : null,
        ];
        /*         * fetch pincode and put in pincode column* */
        $pinCodeRejex = '/([0-9]{6,6})/';
        $rejexFromString = $input['GeoLocationAcc'];
        preg_match($pinCodeRejex, $rejexFromString, $getPinCodeMatches);
        $getPinCode = $getPinCodeMatches[0] ?? '';
        $allRequest['pinCode'] = $getPinCode;
        /*         * fetch pincode and put in pincode column* */


        $allRequest['Title'] = Signup::TITLE_MR; //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
        if ($input['Gender'] == Signup::GENDER_FEMALE) {
            $allRequest['Title'] = Signup::TITLE_MISS; //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
        }

        /* This is to prevent duplicate record from api call*/
        if (isset($input['tempUid']) && $input['tempUid']) {
            $checkIfRecordAlreadyExist = ProspectMaster::where(['tempUid' => $input['tempUid']])->first();
            if ($checkIfRecordAlreadyExist) {
                return $allRequest;
            }
        }
        if (!isset($input['tempUid']) || ($input['tempUid'] == '') || ($input['tempUid'] == null)) {
            return $allRequest;
        }

        ProspectMaster::create($allRequest);

        /**
         * Save Employee Score Detail Start
         */
        $eid = $allRequest['EID'];
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        $employeeScore['CountProspect'] = (isset($getEmployeeScore->CountProspect) && $getEmployeeScore->CountProspect) ? $getEmployeeScore->CountProspect + 1 : 1;
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
        unset($allRequest['ETL']);
        unset($allRequest['FID']);
        return $allRequest;
    }

    public function createProspect(CreateProspect $request) {
        $input = request()->all();
        $allRequest = $this->generateProspect($input);
        return $this->jsonResponse(true, 200, 'Prospect Created', $allRequest);
    }

    public function bulkCreateProspect() {
        $input = request()->all();
        $allRequest = [];
        foreach ($input as $val) {
            $allRequest[] = $this->generateProspect($val);
        }
        return $this->jsonResponse(true, 200, 'All Prospect Created', $allRequest);
    }

    public function generateDonor($input) {
        $authUser = $this->user;
        /**
         * For as a team member register
         */
        if (isset($input['EID']) && ($input['EID'] != '')) {
            $eid = $input['EID'];
            unset($input['EID']);
            $getEmployeeByEid = EmployeeMaster::where(['EID' => $eid])->first();
            $authUser = $getEmployeeByEid;
        }


        $lastName = (isset($input['LastName'])) ? $input['LastName'] : '';

        $allRequest = [
            'EID' => $authUser->EID,
            'FID' => $authUser->FID,
            'EName' => $authUser->EName,
            'ETLID' => $authUser->ETLID,
            'EPMID' => $authUser->EPMID,
            'ETL' => $authUser->ETL,
            'EPM' => $authUser->EPM,
            /**
             *
             * request from Application
             *
             */
            'RecordType' => $input['RecordType'], // 1-Prospect, 2-Supporter
            'CharityCode' => $input['CharityCode'], //1- UNICEF / 2-CRY / 3-Action Aid
            'SignupRemarks' => (isset($input['SignupRemarks'])) ? $input['SignupRemarks'] : '',
            'GeoLocationAcc' => $input['GeoLocationAcc'], //Geo Location
            'LocType' => $input['LocType'], //1-Permission,2-Street
            'PDate' => date("Y-m-d"), //Presentation Date
            'PTime' => date("H:i:s"), //Presentation Time
            'ModeOfDonation' => $input['ModeOfDonation'], //1-NACH,2-ONLINE,3-CHEQUE
            'MethodOfFundraising' => Signup::METHODOFFUNDRAISING_F2F, //1-F2F / 2-D2D / 3-TM-A / 4-TM-R
            'Channel' => Signup::METHODOFFUNDRAISING_F2F, //1-F2F / 2-D2D / 3-TM-A / 4-TM-R
            'FullName' => $input['FirstName'] . ' ' . $lastName,
            'FirstName' => $input['FirstName'],
            'LastName' => $lastName,
            'Gender' => $input['Gender'], //1 - Male / 2 - Female / 3 - Other
            'Mobile_1' => isset($input['Mobile_1']) ? $input['Mobile_1'] : '',
            'eMail_Address' => isset($input['eMail_Address']) ? $input['eMail_Address'] : '',
        ];


        /*         * fetch pincode and put in pincode column* */
        $pinCodeRejex = '/([0-9]{6,6})/';
        $rejexFromString = $input['GeoLocationAcc'];
        preg_match($pinCodeRejex, $rejexFromString, $getPinCodeMatches);
        $getPinCode = $getPinCodeMatches[0] ?? '';
        $allRequest['pinCode'] = $getPinCode;
        /*         * fetch pincode and put in pincode column* */

        $allRequest['Title'] = Signup::TITLE_MR; //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
        if ($input['Gender'] == Signup::GENDER_FEMALE) {
            $allRequest['Title'] = Signup::TITLE_MISS; //1-Mr. / 2-Miss / 3-Mrs. / 4-Dr.
        }

        /* This is to prevent duplicate record from api call*/
        if (isset($input['tempUid']) && $input['tempUid']) {
            $checkIfRecordAlreadyExist = ProspectMaster::where(['tempUid' => $input['tempUid']])->first();
            if ($checkIfRecordAlreadyExist) {
                return $allRequest;
            }
        }
        if (!isset($input['tempUid']) || ($input['tempUid'] == '') || ($input['tempUid'] == null)) {
            return $allRequest;
        }

        $prospectData = ProspectMaster::create($allRequest);
        $prospectCrmId = (isset($prospectData->CRM_ID)) ? $prospectData->CRM_ID : $prospectData->id;
        $allRequest['CRM_ID'] = $prospectCrmId;
        /**
         * add all images
         */
        $allRequest['Photo1'] = (isset($input['Photo1'])) ? Common::transferOriginalImage($input['Photo1']) : '';
        $allRequest['Photo2'] = (isset($input['Photo2'])) ? Common::transferOriginalImage($input['Photo2']) : '';
        $allRequest['Photo3'] = (isset($input['Photo3'])) ? Common::transferOriginalImage($input['Photo3']) : '';
        $allRequest['Photo4'] = (isset($input['Photo4'])) ? Common::transferOriginalImage($input['Photo4']) : '';
        $allRequest['Photo5'] = (isset($input['Photo5'])) ? Common::transferOriginalImage($input['Photo5']) : '';
        $allRequest['Photo6'] = (isset($input['Photo6'])) ? Common::transferOriginalImage($input['Photo6']) : '';
        /**
         * add all images
         */
        $donorCreate = Signup::create($allRequest);
        if (!$donorCreate) {
            ProspectMaster::where(['CRM_ID' => $prospectCrmId])->delete();
            return $this->jsonResponse(false, 500, 'Something went wrong.');
        }
        /**
         * Save Employee Score Detail Start
         */
        $eid = $allRequest['EID'];
        $getEmployeeScore = EmployeeScore::whereDate('CurrentDate', Date('Y-m-d'))
            ->where(['EID' => $eid])->first();
        $employeeScore = [];
        $employeeScore['CountProspect'] = (isset($getEmployeeScore->CountProspect) && $getEmployeeScore->CountProspect) ? $getEmployeeScore->CountProspect + 1 : 1;
        if ($allRequest['ModeOfDonation'] == Signup::MODEOFDONATION_NACH) {
            $employeeScore['CountNACHSignup'] = (isset($getEmployeeScore->CountNACHSignup) && $getEmployeeScore->CountNACHSignup) ? $getEmployeeScore->CountNACHSignup + 1 : 1;
        }
        if ($allRequest['ModeOfDonation'] == Signup::MODEOFDONATION_ENACH) {
            $employeeScore['CountENACHSignup'] = (isset($getEmployeeScore->CountENACHSignup) && $getEmployeeScore->CountENACHSignup) ? $getEmployeeScore->CountENACHSignup + 1 : 1;
        }
        if ($allRequest['ModeOfDonation'] == Signup::MODEOFDONATION_ONLINE) {
            $employeeScore['CountOnlineSignup'] = (isset($getEmployeeScore->CountOnlineSignup) && $getEmployeeScore->CountOnlineSignup) ? $getEmployeeScore->CountOnlineSignup + 1 : 1;
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
        unset($allRequest['ETL']);
        unset($allRequest['FID']);
        return $allRequest;
    }

    public function createDonor(CreateDonor $request) {
        $input = request()->all();
        $allRequest = $this->generateDonor($input);
        return $this->jsonResponse(true, 200, 'Donor Created', $allRequest);
    }

    public function bulkCreateDonor() {
        $input = request()->all();
        $allRequest = [];
        foreach ($input as $val) {
            $allRequest[] = $this->generateDonor($val);
        }
        return $this->jsonResponse(true, 200, 'All Donor Created', $allRequest);
    }

    public function teamMembers() {
        $authUser = $this->user;
        $managersDesg = [
            EmployeeEDesg::DESG_TL => 'ETLID',
            EmployeeEDesg::DESG_PM => 'EPMID',
            EmployeeEDesg::DESG_CH => 'ECHID',
            EmployeeEDesg::DESG_RM => 'ERMID'
        ];
        $managersDesgList = [
            EmployeeEDesg::DESG_TL,
            EmployeeEDesg::DESG_PM,
            EmployeeEDesg::DESG_CH,
            EmployeeEDesg::DESG_RM
        ];
        $getAllMembers = [];
        if (in_array($authUser->EDesg, $managersDesgList)) {
            $col = $managersDesg[$authUser->EDesg];
            $teamLeader = EmployeeMaster::select(['EID', 'EName'])
                ->where(['EID' => $authUser->EID])
                ->first();
            if (!$teamLeader) {
                return $this->jsonResponse(false, 422, 'Team Leader Not Exist.');
            }
            $allMembers = EmployeeMaster::select(['EID', 'EName'])
                ->whereHas('getUserInfo')
                ->where([$col => $authUser->EID])
                ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                ->where('EID', '<>', $authUser->EID)
                ->get();
            $getAllMembers = [
                'leader' => $teamLeader,
                'members' => $allMembers
            ];
        } else {
            foreach ($managersDesg as $key => $val) {
                if ($authUser->$val && ($authUser->$val != null) && ($authUser->$val != '')) {
                    $teamLeader = EmployeeMaster::select(['EID', 'EName'])
                        ->where(['EID' => $authUser->$val])
                        ->first();
                    if (!$teamLeader) {
                        return $this->jsonResponse(false, 422, 'Team Leader Not Exist.');
                    }
                    $allMembers = EmployeeMaster::select(['EID', 'EName'])
                        ->whereHas('getUserInfo')
                        ->where([$val => $authUser->$val])
                        ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                        ->get();
                    $getAllMembers = [
                        'leader' => $teamLeader,
                        'members' => $allMembers
                    ];
                    break;
                }
            }
        }
        return $this->jsonResponse(true, 200, 'Team Member List', $getAllMembers);
    }

    public function getUserPerformance($eid) {
        /**
         * Per day report
         */
        $getUserPerformance = EmployeeScore::select(
            DB::raw('sum(CountProspect) as CountProspect'),
            DB::raw('sum(CountNACHSignup) as CountNACHSignup'),
            DB::raw('sum(CountENACHSignup) as CountENACHSignup'),
            DB::raw('sum(CountOnlineSignup) as CountOnlineSignup')
        )
            ->whereDate('CurrentDate', '=', date('Y-m-d'))
            ->where(['EID' => $eid])
            ->groupBy('EID')
            ->first();

        $totalSignup = 0;
        $totalProspect = 0;
        if ($getUserPerformance) {
            $totalSignup = $getUserPerformance->CountNACHSignup + $getUserPerformance->CountENACHSignup + $getUserPerformance->CountOnlineSignup;
            $totalProspect = $getUserPerformance->CountProspect;
        }

        /*
         *
         * Monthly report
         */
//        $getEmployeeScore = EmployeeScore::select(
//                        /* For NACH                        * **** */
//                        DB::raw('sum(CountNACHAVSuccess) as CountNACHAVSuccessTotal'),
//                        DB::raw('sum(CountNachAVReject) as CountNachAVReject'),
//                        DB::raw('sum(WCNachReject) as WCNachRejectTotal'),
//                        DB::raw('sum(FPNachReject) as FPNachRejectTotal'),
//                        DB::raw('sum(DataEntryNachReject) as DataEntryNachRejectTotal'),
//                        /* For ENACH                        * **** */
//                        DB::raw('sum(CountENACHAVSuccess) as CountENACHAVSuccessTotal'),
//                        DB::raw('sum(WCENachReject) as WCENachRejectTotal'),
//                        DB::raw('sum(DataEntryENachReject) as DataEntryENachRejectTotal'),
//                        /* For Online                        * **** */
//                        DB::raw('sum(CountOnlineAVSuccess) as CountOnlineAVSuccessTotal'),
//                        DB::raw('sum(CountOnlineAVReject) as CountOnlineAVReject'),
//                        DB::raw('sum(WCOnlineReject) as WCOnlineRejectTotal'),
//                        DB::raw('sum(DataEntryOnlineReject) as DataEntryOnlineReject'),
//                        /*       SaperateValue                  * ****************** */
//                        DB::raw('sum(CountNACHSignup) as NACH'),
//                        DB::raw('sum(CountENACHSignup) as ENACH'),
//                        DB::raw('sum(CountOnlineSignup) as Online')
//                )
//                ->whereYear('CurrentDate', date('Y'))
//                ->whereMonth('CurrentDate', date('m'))
//                ->where(['EID' => $employee->EID])
//                ->groupBy('EID')
//                ->first();
//
//        if ($getEmployeeScore) {
//            $countNachForMonth = $getEmployeeScore->NACH -
//                    $getEmployeeScore->CountNachAVReject -
//                    $getEmployeeScore->WCNachRejectTotal -
//                    $getEmployeeScore->FPNachRejectTotal -
//                    $getEmployeeScore->DataEntryNachRejectTotal;
//            $nachForMonth = ($countNachForMonth > 0) ? $countNachForMonth : 0;
////            $nachForMonth = $getEmployeeScore->NACH;
//
//
//            $countEnachForMonth = $getEmployeeScore->CountENACHAVSuccessTotal -
//                    $getEmployeeScore->WCENachRejectTotal -
//                    $getEmployeeScore->DataEntryENachRejectTotal;
//            $enachForMonth = ($countEnachForMonth > 0) ? $countEnachForMonth : 0;
////            $enachForMonth = $getEmployeeScore->ENACH;
//
//
//            $countOnlineForMonth = $getEmployeeScore->Online -
//                    $getEmployeeScore->CountOnlineAVReject -
//                    $getEmployeeScore->WCOnlineRejectTotal -
//                    $getEmployeeScore->DataEntryOnlineReject;
//            $onlineForMonth = ($countOnlineForMonth > 0) ? $countOnlineForMonth : 0;
////            $onlineForMonth = $getEmployeeScore->Online;
//        }
        /*
         *
         * Monthly report
         */
        $getEmployeeScore = EmployeeScore::select(
            DB::raw('sum(CountProspect) as CountProspect'),
            DB::raw('sum(CountNACHSignup) as CountNACHSignup'),
            DB::raw('sum(CountENACHSignup) as CountENACHSignup'),
            DB::raw('sum(CountOnlineSignup) as CountOnlineSignup'),
            /**
             * New Columns For Formulas
             */
            /* For NACH                        * **** */
            DB::raw('sum(CountNACHAVSuccess) as CountNACHAVSuccess'),
            DB::raw('sum(CountNachAVReject) as CountNachAVReject'),
            DB::raw('sum(WCNachReject) as WCNachReject'),
            DB::raw('sum(FPNachReject) as FPNachReject'),
            DB::raw('sum(DataEntryNachReject) as DataEntryNachReject'),
            /* For ENACH                        * **** */
            DB::raw('sum(CountENACHAVSuccess) as CountENACHAVSuccess'),
            DB::raw('sum(CountENACHAVReject) as CountENACHAVReject'),
            DB::raw('sum(WCENachReject) as WCENachReject'),
            DB::raw('sum(DataEntryENachReject) as DataEntryENachReject'),
            /* For Online                        * **** */
            DB::raw('sum(CountOnlineAVSuccess) as CountOnlineAVSuccess'),
            DB::raw('sum(CountOnlineAVReject) as CountOnlineAVReject'),
            DB::raw('sum(WCOnlineReject) as WCOnlineReject'),
            DB::raw('sum(DataEntryOnlineReject) as DataEntryOnlineReject')
        )
            ->whereYear('CurrentDate', date('Y'))
            ->whereMonth('CurrentDate', date('m'))
            ->where(['EID' => $eid])
            ->groupBy('EID')
            ->first();
        $nachTotal = $enachTotal = $onlineTotal = 0;
        if ($getEmployeeScore) {
            $nachTotal = $getEmployeeScore->CountNACHSignup -
                $getEmployeeScore->CountNachAVReject -
                $getEmployeeScore->WCNachReject -
                $getEmployeeScore->FPNachReject -
                $getEmployeeScore->DataEntryNachReject;
            $nachTotal = ($nachTotal > 0) ? $nachTotal : 0;
            $enachTotal = $getEmployeeScore->CountENACHSignup -
                $getEmployeeScore->CountENACHAVReject -
                $getEmployeeScore->WCENachReject -
                $getEmployeeScore->DataEntryENachReject;
            $enachTotal = ($enachTotal > 0) ? $enachTotal : 0;
            $onlineTotal = $getEmployeeScore->CountOnlineSignup -
                $getEmployeeScore->CountOnlineAVReject -
                $getEmployeeScore->WCOnlineReject -
                $getEmployeeScore->DataEntryOnlineReject;
            $onlineTotal = ($onlineTotal > 0) ? $onlineTotal : 0;
        }


        $nachForMonth = $nachTotal ?? 0;
        $enachForMonth = $enachTotal ?? 0;
        $onlineForMonth = $onlineTotal ?? 0;
        $achdTotal = $nachForMonth + $onlineForMonth + $enachForMonth;
        /*
         * Target and quality
         */
        $getTargetAndQuality = EmployeeTargetQaulity::where(['EID' => $eid])
            ->whereYear('date', date('Y'))
            ->whereMonth('date', date('m'))
            ->first();

        $monthlyTarget = ($getTargetAndQuality) ? $getTargetAndQuality->target : 0;
        $getQualityTarget = ($getTargetAndQuality) ? $getTargetAndQuality->quality : 0;
        $qualityTarget = number_format((float)$getQualityTarget, 2, '.', '');
        $totalUploadedDays = ($getTargetAndQuality) ? $getTargetAndQuality->workingDays : 0;
        /*         * ******************             */


        /**
         * Calculate RRR
         */
        $totalDayOfMonth = cal_days_in_month(CAL_GREGORIAN, date('m'), date('Y'));
        $allDays = range('1', $totalDayOfMonth);
        $countProspectWorkingDays = 0;
        foreach ($allDays as $val) {
            $getPerDayScore = EmployeeScore::select(
                DB::raw('sum(CountNACHSignup) as CountNACHSignup'),
                DB::raw('sum(CountENACHSignup) as CountENACHSignup'),
                DB::raw('sum(CountOnlineSignup) as CountOnlineSignup')
            )
                ->whereDate('CurrentDate', '=', date('Y') . '-' . date('m') . '-' . $val)
                ->where(['EID' => $eid])
                ->groupBy('EID')
                ->first();
            if ($getPerDayScore) {
                $countProspectWorkingDays++;
            }
        }

        $totalTargetRemaining = $monthlyTarget - $achdTotal;
        $getRemainWorkingDays = $totalUploadedDays - $countProspectWorkingDays;
        $remainWorkingDays = ($getRemainWorkingDays > 0) ? $getRemainWorkingDays : 0;
        $rrr = 0;
        if ($remainWorkingDays != 0) {
            $getTotalRRR = $totalTargetRemaining / $remainWorkingDays;
            $rrr = number_format((float)$getTotalRRR, 2, '.', '');
        }

        $responseData = [
            'signup' => (float)number_format((float)$totalSignup, 2, '.', ''), //(Int)
            'Presentations' => (float)number_format((float)$totalProspect, 2, '.', ''), //(Int)
            'performance' => [
                'target' => (float)number_format((float)$monthlyTarget, 2, '.', ''), //(Int) //this will deside in backend for each month for each employee by admin or superadmin
                'nach' => (float)number_format((float)$nachForMonth, 2, '.', ''), //(Float)
                'online' => (float)number_format((float)$onlineForMonth, 2, '.', ''), //(Float)
                'enach' => (float)number_format((float)$enachForMonth, 2, '.', ''), //(Float)
                'achd' => (float)number_format((float)$achdTotal, 2, '.', ''), //(Float) //total of nach, online, enach
                'rrr' => (float)$rrr, //(Currntly this is in string format)
                'quality' => (float)number_format((float)$qualityTarget, 2, '.', '')//(Float)
            ]
        ];
        return $responseData;
    }

    public function userPerformance() {
        $auth = $this->user;
        $responseData = $this->getUserPerformance($auth->EID);
        return $this->jsonResponse(true, 200, 'LoggedIn User Performance.', $responseData);
    }

    public function teamPerformance() {
        $authUser = $this->user;
        $managersDesg = [
            EmployeeEDesg::DESG_TL => 'ETLID',
            EmployeeEDesg::DESG_PM => 'EPMID',
            EmployeeEDesg::DESG_CH => 'ECHID',
            EmployeeEDesg::DESG_RM => 'ERMID'
        ];
        $managersDesgList = [
            EmployeeEDesg::DESG_TL,
            EmployeeEDesg::DESG_PM,
            EmployeeEDesg::DESG_CH,
            EmployeeEDesg::DESG_RM
        ];
        $getAllMembersPerformance = [];
        if (in_array($authUser->EDesg, $managersDesgList)) {
            $col = $managersDesg[$authUser->EDesg];
            $teamLeader = EmployeeMaster::select(['EID', 'EName'])
                ->where(['EID' => $authUser->EID])
                ->first();
            if (!$teamLeader) {
                return $this->jsonResponse(false, 422, 'Team Leader Not Exist.');
            }
            $allMembers = EmployeeMaster::select(['EID', 'EName'])
                ->whereHas('getUserInfo')
                ->where(fn($q) => $q->where([$col => $authUser->EID])->orWhere(['EID' => $authUser->EID]))
                ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                ->get();
            /**
             *
             * Get Average of the all members record.
             *
             */
            $targetAvg = 0;
            $achdAvg = 0;
            $nachAvg = 0;
            $enachAvg = 0;
            $onlineAvg = 0;
            $rrrAvg = 0;
            $qualityAvg = 0;
            $totalMember = count($allMembers);
            /**
             *
             * Get Average of the all members record.
             *
             */
            foreach ($allMembers as $val) {
                $responseData = $this->getUserPerformance($val->EID);
                $userDetail = [
                    'EID' => $val->EID,
                    'Name' => $val->EName,
                ];
                $responseData = array_merge($userDetail, $responseData);
                $getAllMembersPerformance['teamRecord'][] = $responseData;
                /**
                 * add data for count average
                 */
                $targetAvg += $responseData['performance']['target'];
                $achdAvg += $responseData['performance']['achd'];
                $nachAvg += $responseData['performance']['nach'];
                $enachAvg += $responseData['performance']['enach'];
                $onlineAvg += $responseData['performance']['online'];
                $rrrAvg += $responseData['performance']['rrr'];
                $qualityAvg += $responseData['performance']['quality'];
            }

//            $getTotalTarget = $targetAvg / $totalMember;
//            $getTotalNach = $nachAvg / $totalMember;
//            $getTotalOnline = $onlineAvg / $totalMember;
//            $getTotalEnach = $enachAvg / $totalMember;
//            $getTotalAchd = $achdAvg / $totalMember;
//            $getTotalRRR = $rrrAvg / $totalMember;
            $getTotalQuality = 0;
            if ($totalMember != 0) {
                $getTotalQuality = $qualityAvg / $totalMember;
            }

            $totalTarget = number_format((float)$targetAvg, 2, '.', '');
            $totalNach = number_format((float)$nachAvg, 2, '.', '');
            $totalOnline = number_format((float)$onlineAvg, 2, '.', '');
            $totalEnach = number_format((float)$enachAvg, 2, '.', '');
            $totalAchd = number_format((float)$achdAvg, 2, '.', '');
            $totalRRR = number_format((float)$rrrAvg, 2, '.', '');
            $totalQuality = number_format((float)$getTotalQuality, 2, '.', '');

            $getAllMembersPerformance['totalAvg'] = [
                'title' => 'TEAM SCORE',
                'teamperformance' => [
                    'target' => (float)$totalTarget, //this will deside in backend for each month for each employee by admin or superadmin
                    'nach' => (float)$totalNach,
                    'online' => (float)$totalOnline,
                    'enach' => (float)$totalEnach,
                    'achd' => (float)$totalAchd, //total of nach, online, enach
                    'rrr' => (float)$totalRRR,
                    'quality' => (float)$totalQuality
                ]
            ];
            /**
             * count average
             */
        } else {
            return $this->jsonResponse(false, 401, 'You are not allow to perform this action.');
        }
        return $this->jsonResponse(true, 200, 'Member Performance', $getAllMembersPerformance);
    }

    public function memberAttendance(MemberAttendance $request) {
        $input = request()->all();
        $authUser = $this->user;
        $managersDesgList = [
            EmployeeEDesg::DESG_TL,
            EmployeeEDesg::DESG_PM,
            EmployeeEDesg::DESG_CH,
            EmployeeEDesg::DESG_RM
        ];
        if ($input['EID'] != $authUser->EID) {
            return $this->jsonResponse(false, 401, 'Unauthenticated.');
        }
        if (!in_array($authUser->EDesg, $managersDesgList)) {
            return $this->jsonResponse(false, 422, 'You are not allowed to perform this action.');
        }
        $data = $input['memberAttendance'];
        $getAllAttRemarks = array_flip(Attendance::attnRemarks());
        foreach ($data as $val) {
            $updateData = [
                'Attendance' => $val['Attendance'],
                'AttnRemarks' => $getAllAttRemarks[$val['AttnRemarks']] ?? null,
                'AttnApproveBy' => $authUser['EID']
            ];
            $updateAttendance = Attendance::where(['EID' => $val['EID']])
                ->whereDate('InStamp', date('Y-m-d'))
                ->first();
            if ($updateAttendance) {
                Attendance::where(['EID' => $val['EID']])
                    ->whereDate('InStamp', date('Y-m-d'))
                    ->update($updateData);
            } else {
                $getEmployee = EmployeeMaster::where(['EID' => $val['EID']])->first();
                $updateData['EID'] = $getEmployee->EID;
                $updateData['EName'] = $getEmployee->EName;
                //p, HD
                if (in_array($val['AttnRemarks'], ['P', 'HD', 'Present', 'Half Day'])) { //JIC 'Present', 'Half Day'
                    $getAuthUserAttendance = Attendance::where(['EID' => $authUser->EID])
                        ->whereDate('InStamp', date('Y-m-d'))
                        ->first();
                    $updateData['InStamp'] = $getAuthUserAttendance->InStamp ?? date("Y-m-d H:i:s");
                    $updateData['OutStamp'] = date("Y-m-d H:i:s");
                } else {
                    $updateData['InStamp'] = date("Y-m-d H:i:s");
                    $updateData['OutStamp'] = date("Y-m-d H:i:s");
                }
                //absent
                $updateData['ECity'] = $authUser->ECity;
                Attendance::create($updateData);
            }
        }
        return $this->jsonResponse(true, 200, 'Member Attendance Marked.');
    }

    public function memberAttendanceStatus() {
        $authUser = $this->user;
        $managersDesg = [
            EmployeeEDesg::DESG_TL => 'ETLID',
            EmployeeEDesg::DESG_PM => 'EPMID',
            EmployeeEDesg::DESG_CH => 'ECHID',
            EmployeeEDesg::DESG_RM => 'ERMID'
        ];
        $managersDesgList = [
            EmployeeEDesg::DESG_TL,
            EmployeeEDesg::DESG_PM,
            EmployeeEDesg::DESG_CH,
            EmployeeEDesg::DESG_RM
        ];
        $getAllMembers = [];
        if (in_array($authUser->EDesg, $managersDesgList)) {
            $col = $managersDesg[$authUser->EDesg];
            $teamLeader = EmployeeMaster::select(['EID', 'EName'])
                ->where(['EID' => $authUser->EID])
                ->first();
            if (!$teamLeader) {
                return $this->jsonResponse(false, 422, 'Team Leader Not Exist.');
            }
            $allMembers = EmployeeMaster::select(['EID', 'EName'])
                ->whereHas('getUserInfo')
                ->where([$col => $authUser->EID])
                ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                ->get();
            $getAllAttRemarks = Attendance::attnRemarks();
            if ($allMembers) {
                foreach ($allMembers as $k => $v) {
                    $att = Attendance::select('Attendance', 'AttnRemarks', 'InStamp')
                        ->where('EID', $v->EID)
                        ->whereDate('InStamp', date('Y-m-d'))
                        ->first();
                    $v->Attendance = Attendance::ATTENDANCE_A;
                    $v->AttnRemarks = null;
                    if ($att) {
                        $v->AttnRemarks = isset($getAllAttRemarks[$att->AttnRemarks]) ? $getAllAttRemarks[$att->AttnRemarks] : null;
                        if ($att->Attendance) {
                            $v->Attendance = $att->Attendance;
                        } else {
                            /**
                             * calculate attendance
                             *
                             */
                            $OutStamp = date("Y-m-d H:i:s");
                            $datetime1 = new \DateTime($att->InStamp);
                            $datetime2 = new \DateTime($OutStamp);
                            $interval = $datetime1->diff($datetime2);
                            /**
                             * echo $interval->format('%Y-%m-%d %H:%i:%s');
                             */
                            $TotProdHrs = $interval->h;
                            /**
                             * If TotProdHrs>4 $$ <6, HD TotProdHrs>6,P
                             */
                            $attendance = Attendance::ATTENDANCE_A;
                            if ($TotProdHrs >= 3 && $TotProdHrs < 6) {
                                $attendance = Attendance::ATTENDANCE_HD;
                            } elseif ($TotProdHrs >= 6) {
                                $attendance = Attendance::ATTENDANCE_P;
                            }
                            $v->Attendance = $attendance;
                            /**
                             * calculate attendance
                             *
                             */
                        }
                    }
                }
            }
            $getAllMembers = [
                'Attendance' => [
                    Attendance::ATTENDANCE_P,
                    Attendance::ATTENDANCE_HD,
                    Attendance::ATTENDANCE_A,
                ],
                'AttnRemarks' => array_values($getAllAttRemarks),
                'members' => $allMembers
            ];
        } else {
            return $this->jsonResponse(false, 401, 'You are not allow to perform this action.');
        }
        return $this->jsonResponse(true, 200, 'Member Attendance Status.', $getAllMembers);
    }

}
