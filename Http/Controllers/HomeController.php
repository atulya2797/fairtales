<?php

namespace App\Http\Controllers;

use App\Model\WlcmCallDetail;
use SimpleXLSX;
use App\Model\Signup;
use App\Helper\Common;
use App\Model\Attendance;
use App\Model\ChatGroups;
use App\Model\CharityCode;
use App\Model\ChatTeamGroup;
use App\Model\EmployeeEDesg;
use App\Model\EmployeeECity;
use App\Model\EmployeeScore;
use App\Model\SignupFormChk;
use App\Model\SignupWlcmCall;
use App\Model\EmployeeMaster;
use App\Model\SignupDataEntry;
use App\Model\SignupAccountChk;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller {

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
    public function test() {

    }

    public function getDashboardScore() {
        $getAllCity = EmployeeECity::get();

        $allowedDesg = [EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN];
        $whereInDesg = [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL];

        $getDayScore = [];
        $getMonthScore = [];
        $monthlyTotalMandayProductivity = 0;
        if ($this->user) {
            if (!in_array($this->user->EDesg, $allowedDesg)) {
                return [$getDayScore, $getMonthScore, $monthlyTotalMandayProductivity];
            }
//            if ($this->user->EDesg == EmployeeEDesg::DESG_PM) {
//                $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
//            }
            if ($this->user->EDesg == EmployeeEDesg::DESG_CH) {
                $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
            }
        }

        foreach ($getAllCity as $ckey => $cval) {
            $getScore = EmployeeScore::select(
                'Employee_Master.EID',
                'Employee_Master.ECity',
                DB::raw('sum(EmployeeScore.CountProspect) as CountProspect'),
                DB::raw('sum(EmployeeScore.CountNACHSignup) as NACH'),
                DB::raw('sum(EmployeeScore.CountNachAVReject) as NachAVReject'),
                DB::raw('sum(EmployeeScore.CountENACHSignup) as ENACH'),
                DB::raw('sum(EmployeeScore.CountENachAVReject) as ENachAVReject'),
                DB::raw('sum(EmployeeScore.CountOnlineSignup) as Online'),
                DB::raw('sum(EmployeeScore.CountOnlineAVReject) as OnlineAVReject')
            )
                ->whereDate('CurrentDate', '=', date('Y-m-d'))
                ->join('Employee_Master', 'Employee_Master.EID', 'EmployeeScore.EID')
                ->whereIn('EDesg', $whereInDesg)
                ->where('Employee_Master.ECity', '=', $cval->id)
                ->groupBy('Employee_Master.ECity')
                ->first();

            $signUpCount = Signup::whereDate('PDate', date('Y-m-d'))
                ->join('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                ->whereIn('EDesg', $whereInDesg)
                ->where('Employee_Master.ECity', '=', $cval->id)
                ->count();

            /* Signup rejected in today's date */
            $signUpReject = Signup::whereDate('Signup_AccountChk.created_at', date('Y-m-d'))
                ->whereDate('Signup.PDate', date('Y-m-d'))
                ->join('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                ->join('Signup_AccountChk', 'Signup.CRM_ID', 'Signup_AccountChk.CRM_ID')
                ->whereIn('EDesg', $whereInDesg)
                ->where('Employee_Master.ECity', '=', $cval->id)
                ->where(['Signup_AccountChk.BOStatUpdate' => SignupAccountChk::STATUS_REJECTED])
                ->count();

            $actualSignup = $signUpCount - $signUpReject;

            $getTotalEmployeeinCity = EmployeeMaster::select('EID')
                ->whereHas('getUserInfo')
                ->whereIn('EDesg', $whereInDesg)
                ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                ->where(['ECity' => $cval->id]);

            $getAllEmployeeId = $getTotalEmployeeinCity->get();
            $totalNoOfEmployeeInCity = count($getAllEmployeeId);
            $activeEmployeeToday = Attendance::whereIn('Attendance.EID', $getAllEmployeeId)
                ->leftJoin('Employee_Master', 'Attendance.EID', 'Employee_Master.EID')
                ->whereIn('EDesg', $whereInDesg)
                ->whereDate('InStamp', date('Y-m-d'))
                ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                ->groupBy('Attendance.EID')
                ->get()
                ->count();

            $currentEmployeeToday = Attendance::whereIn('Attendance.EID', $getAllEmployeeId)
                ->leftJoin('Employee_Master', 'Attendance.EID', 'Employee_Master.EID')
                ->whereIn('EDesg', $whereInDesg)
                ->whereDate('InStamp', date('Y-m-d'))
                ->where(['OutStamp' => null])
                ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                ->groupBy('Attendance.EID')
                ->get()
                ->count();

            $nach = $getScore ? $getScore['NACH'] - $getScore['NachAVReject'] : 0;
            $enach = $getScore ? $getScore['ENACH'] - $getScore['ENachAVReject'] : 0;
            $online = $getScore ? $getScore['Online'] - $getScore['OnlineAVReject'] : 0;

            $getScoreData = [];
            $getScoreData['isSub'] = false;
            $getScoreData['ECityName'] = $cval->Ecity ?? '';
            $getScoreData['TotalFr'] = $totalNoOfEmployeeInCity;
            $getScoreData['ActiveFr'] = $activeEmployeeToday;
            $getScoreData['CurrentActive'] = $currentEmployeeToday;
            $getScoreData['Presentation'] = $getScore['CountProspect'] ?? 0;
            $getScoreData['NACH'] = $nach ?? 0;
            $getScoreData['ENACH'] = $enach ?? 0;
            $getScoreData['Online'] = $online ?? 0;
            $getScoreData['Total'] = $getScoreData['NACH'] + $getScoreData['ENACH'] + $getScoreData['Online'];
            $getScoreData['SignupCount'] = $signUpCount;
            $getScoreData['SignUpReject'] = $signUpReject;
            $getScoreData['ActualSignup'] = $actualSignup;
            $getScoreData['AccValQueue'] = Common::getDonorCount(null, $cval->id);
            $getScoreData['WelcomeCallQueue'] = Common::getDonorCount(Signup::ACCOUNT_CHECK, $cval->id);
            $getScoreData['FormProcessingQueue'] = Common::getDonorCount(Signup::QUALITY_CHECK, $cval->id);
            $getScoreData['DataEntryQueue'] = Common::getDonorCount(Signup::DATA_ENTRY_CHECK, $cval->id);
            $getDayScore[] = $getScoreData;
            $groupWiseScore = $this->getGroupWiseScore($cval);
            $getDayScore = array_merge($getDayScore, $groupWiseScore);
        }
        foreach ($getAllCity as $ckey => $cval) {
            $getScoreMonthly = EmployeeScore::select(
                'Employee_Master.EID',
                'Employee_Master.ECity',
                DB::raw('sum(EmployeeScore.CountProspect) as CountProspect'),
                DB::raw('sum(EmployeeScore.CountNACHSignup) as NACH'),
                DB::raw('sum(EmployeeScore.CountNachAVReject) as NachAVReject'),
                DB::raw('sum(EmployeeScore.WCNachReject) as WCNachReject'),
                DB::raw('sum(EmployeeScore.FPNachReject) as FPNachReject'),
                DB::raw('sum(EmployeeScore.DataEntryNachReject) as DataEntryNachReject'),
                DB::raw('sum(EmployeeScore.CountENACHSignup) as ENACH'),
                DB::raw('sum(EmployeeScore.WCENachReject) as WCENachReject'),
                DB::raw('sum(EmployeeScore.DataEntryENachReject) as DataEntryENachReject'),
                DB::raw('sum(EmployeeScore.CountENachAVReject) as ENachAVReject'),
                DB::raw('sum(EmployeeScore.CountOnlineSignup) as Online'),
                DB::raw('sum(EmployeeScore.WCOnlineReject) as WCOnlineReject'),
                DB::raw('sum(EmployeeScore.DataEntryOnlineReject) as DataEntryOnlineReject'),
                DB::raw('sum(EmployeeScore.CountOnlineAVReject) as OnlineAVReject')
            )
                ->whereYear('CurrentDate', '=', date('Y'))
                ->whereMonth('CurrentDate', '=', date('m'))
                ->join('Employee_Master', 'Employee_Master.EID', 'EmployeeScore.EID')
                ->whereIn('EDesg', $whereInDesg)
                ->where('Employee_Master.ECity', '=', $cval->id)
                ->groupBy('Employee_Master.ECity')
                ->first();


            $getTotalEmployeeinCity = EmployeeMaster::select('EID')
                ->whereHas('getUserInfo')
                ->whereIn('EDesg', $whereInDesg)
                ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                ->where(['ECity' => $cval->id]);

            $getAllEmployeeId = $getTotalEmployeeinCity->get();
            $totalNoOfEmployeeInCity = count($getAllEmployeeId);
            $activeEmployeeMonthly = Attendance::whereIn('Attendance.EID', $getAllEmployeeId)
                ->leftJoin('Employee_Master', 'Attendance.EID', 'Employee_Master.EID')
                ->whereIn('EDesg', $whereInDesg)
                ->whereMonth('InStamp', date('m'))
                ->whereYear('InStamp', date('Y'))
                ->whereIn('Attendance', [Attendance::ATTENDANCE_P, Attendance::ATTENDANCE_HD])
                ->where(function ($q) {
                    return $q->whereIn('AttnRemarks', [Attendance::AttnRemarks_Present, Attendance::AttnRemarks_HalfDay, null])
                        ->orWhere(['AttnRemarks' => null]);
                })
                ->count();

            $signUpCount = Signup::whereMonth('Signup.PDate', date('m'))
                ->whereYear('Signup.PDate', date('Y'))
                ->join('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                ->whereIn('EDesg', $whereInDesg)
                ->where('Employee_Master.ECity', '=', $cval->id)
                ->count();

            /* Signup rejected in today's date */
            $signUpReject = Signup::whereMonth('Signup_AccountChk.created_at', date('m'))
                ->whereYear('Signup_AccountChk.created_at', date('Y'))
                ->whereMonth('Signup.PDate', date('m'))
                ->whereYear('Signup.PDate', date('Y'))
                ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                ->leftJoin('Signup_AccountChk', 'Signup.CRM_ID', 'Signup_AccountChk.CRM_ID')
                ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                ->leftJoin('Signup_FormChk', 'Signup.CRM_ID', 'Signup_FormChk.CRM_ID')
                ->leftJoin('Signup_WlcmCall', 'Signup.CRM_ID', 'Signup_WlcmCall.CRM_ID')
                ->whereIn('Employee_Master.EDesg', $whereInDesg)
                ->where('Employee_Master.ECity', '=', $cval->id)
                ->where(function ($q) {
                    return $q->where(['Signup_AccountChk.BOStatUpdate' => SignupAccountChk::STATUS_REJECTED])
                        ->orWhere(['Signup_DataEntry.dataEntryStatus' => SignupDataEntry::dataEntryStatus_Reject])
                        ->orWhere(['Signup_FormChk.FFPStatus' => SignupFormChk::FFPStatus_Reject])
                        ->orWhere(['Signup_WlcmCall.Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_rejected]);
                })
                ->count();
            $actualSignup = $signUpCount - $signUpReject;
            $nach = $getScoreMonthly ? $getScoreMonthly['NACH'] - $getScoreMonthly['NachAVReject'] - $getScoreMonthly['WCNachReject'] - $getScoreMonthly['FPNachReject'] - $getScoreMonthly['DataEntryNachReject'] : 0;
            $enach = $getScoreMonthly ? $getScoreMonthly['ENACH'] - $getScoreMonthly['ENachAVReject'] - $getScoreMonthly['WCENachReject'] - $getScoreMonthly['DataEntryENachReject'] : 0;
            $online = $getScoreMonthly ? $getScoreMonthly['Online'] - $getScoreMonthly['OnlineAVReject'] - $getScoreMonthly['WCOnlineReject'] - $getScoreMonthly['DataEntryOnlineReject'] : 0;

            $nach = ($nach > 0) ? $nach : 0;
            $enach = ($enach > 0) ? $enach : 0;
            $online = ($online > 0) ? $online : 0;

            $totalSignUpMonthly = $nach + $enach + $online;

            $getScoreMonthlyData = [];
            $getScoreMonthlyData['isSub'] = false;
            $getScoreMonthlyData['ECityName'] = $cval->Ecity ?? '';
            $getScoreMonthlyData['TotalFr'] = $totalNoOfEmployeeInCity;
            $getScoreMonthlyData['TotalManday'] = $activeEmployeeMonthly;
            $getScoreMonthlyData['Presentation'] = $getScoreMonthly['CountProspect'] ?? 0;
            $getScoreMonthlyData['NACH'] = $nach ?? 0;
            $getScoreMonthlyData['ENACH'] = $enach ?? 0;
            $getScoreMonthlyData['Online'] = $online ?? 0;
            $getScoreMonthlyData['Total'] = $totalSignUpMonthly;
            $getScoreMonthlyData['SignupCount'] = $signUpCount;
            $getScoreMonthlyData['SignUpReject'] = $signUpReject;
            $getScoreMonthlyData['ActualSignup'] = $actualSignup;
            $mandayProd = 0;
            if ($activeEmployeeMonthly != 0) {
                $mandayProd = $totalSignUpMonthly / $activeEmployeeMonthly;
            }
            $getScoreMonthlyData['Manday'] = number_format((float)$mandayProd, '2', '.', '');

            $getMonthScore[] = $getScoreMonthlyData;
            $groupWiseScoreMonthly = $this->getGroupWiseScoreMonthly($cval);
            $getMonthScore = array_merge($getMonthScore, $groupWiseScoreMonthly);
        }

        /*
         * $monthlyTotalMandayProductivity start
         */

        $mandayAllCity = $getAllCity->toArray();
        $mandayAllCityId = array_column($mandayAllCity, 'id');

        $getMandayScoreMonthly = EmployeeScore::select(
            DB::raw('sum(EmployeeScore.CountProspect) as CountProspect'),
            DB::raw('sum(EmployeeScore.CountNACHSignup) as NACH'),
            DB::raw('sum(EmployeeScore.CountNachAVReject) as NachAVReject'),
            DB::raw('sum(EmployeeScore.WCNachReject) as WCNachReject'),
            DB::raw('sum(EmployeeScore.FPNachReject) as FPNachReject'),
            DB::raw('sum(EmployeeScore.DataEntryNachReject) as DataEntryNachReject'),
            DB::raw('sum(EmployeeScore.CountENACHSignup) as ENACH'),
            DB::raw('sum(EmployeeScore.WCENachReject) as WCENachReject'),
            DB::raw('sum(EmployeeScore.DataEntryENachReject) as DataEntryENachReject'),
            DB::raw('sum(EmployeeScore.CountENachAVReject) as ENachAVReject'),
            DB::raw('sum(EmployeeScore.CountOnlineSignup) as Online'),
            DB::raw('sum(EmployeeScore.WCOnlineReject) as WCOnlineReject'),
            DB::raw('sum(EmployeeScore.DataEntryOnlineReject) as DataEntryOnlineReject'),
            DB::raw('sum(EmployeeScore.CountOnlineAVReject) as OnlineAVReject')
        )
            ->whereYear('CurrentDate', '=', date('Y'))
            ->whereMonth('CurrentDate', '=', date('m'))
            ->join('Employee_Master', 'Employee_Master.EID', 'EmployeeScore.EID')
            ->whereIn('EDesg', $whereInDesg)
            ->whereIn('Employee_Master.ECity', $mandayAllCityId)
            ->first();
        $mandayNach = $getMandayScoreMonthly ? $getMandayScoreMonthly['NACH'] - $getMandayScoreMonthly['NachAVReject'] - $getMandayScoreMonthly['WCNachReject'] - $getMandayScoreMonthly['FPNachReject'] - $getMandayScoreMonthly['DataEntryNachReject'] : 0;
        $mandayEnach = $getMandayScoreMonthly ? $getMandayScoreMonthly['ENACH'] - $getMandayScoreMonthly['ENachAVReject'] - $getMandayScoreMonthly['WCENachReject'] - $getMandayScoreMonthly['DataEntryENachReject'] : 0;
        $mandayOnline = $getMandayScoreMonthly ? $getMandayScoreMonthly['Online'] - $getMandayScoreMonthly['OnlineAVReject'] - $getMandayScoreMonthly['WCOnlineReject'] - $getMandayScoreMonthly['DataEntryOnlineReject'] : 0;

        $mandayNach = ($mandayNach > 0) ? $mandayNach : 0;
        $mandayEnach = ($mandayEnach > 0) ? $mandayEnach : 0;
        $mandayOnline = ($mandayOnline > 0) ? $mandayOnline : 0;

        $totalSignUpMonthly = $mandayNach + $mandayEnach + $mandayOnline;

        if ($getMandayScoreMonthly) {
            $getTotalEmployeeinCity = EmployeeMaster::select('EID')
                ->whereHas('getUserInfo')
                ->whereIn('EDesg', $whereInDesg)
                ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                ->whereIn('ECity', $mandayAllCityId);
            $getAllEmployeeId = $getTotalEmployeeinCity->get();

            $activeEmployeeMonthly = Attendance::leftJoin('Employee_Master', 'Attendance.EID', 'Employee_Master.EID')
                ->whereIn('Employee_Master.EDesg', [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL])
                ->whereIn('Employee_Master.ECity', $mandayAllCityId)
                ->whereMonth('InStamp', date('m'))
                ->whereYear('InStamp', date('Y'))
                ->whereIn('Attendance.EID', $getAllEmployeeId)
                ->whereIn('Attendance', [Attendance::ATTENDANCE_P, Attendance::ATTENDANCE_HD])
                ->where(function ($q) {
                    return $q->whereIn('AttnRemarks', [Attendance::AttnRemarks_Present, Attendance::AttnRemarks_HalfDay])
                        ->orWhere(['AttnRemarks' => null]);
                })
                ->count();

            $getScoreMonthlyData = [];

            $mandayProd = 0;
            if ($activeEmployeeMonthly != 0) {
                $mandayProd = $totalSignUpMonthly / $activeEmployeeMonthly;
            }
            $monthlyTotalMandayProductivity = number_format((float)$mandayProd, '2', '.', '');
        }


        /*
         * $monthlyTotalMandayProductivity end
         */
        return [$getDayScore, $getMonthScore, $monthlyTotalMandayProductivity];
    }

    public function getGroupWiseScore($cval) {
        $groupWiseScore = [];
        $whereInDesg = [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL];
        $getAllCharityCode = CharityCode::get();
        foreach ($getAllCharityCode as $val) {
            $chatiryCode = str_replace(' ', '%', $val->CharityCode);
            $cityName = $cval->Ecity;
            $getTeamGroup = ChatGroups::where(['type' => ChatGroups::TypeTeam])
                ->where('GrpName', 'LIKE', $chatiryCode . '%' . $cityName . '%')
//                ->orderBy('GrpName')
                ->orderBy('id')
                ->get();

            $eidCounter = [];
            foreach ($getTeamGroup as $key => $tgd) {

                $getAllEidByGroupName = ChatTeamGroup::select('EID')
                    ->where(['GrpId' => $tgd->GrpId])
                    ->orderBy('GrpName')
                    ->get();
                /* fix $getAllEidByGroupName because one employee EID is in multiple group */
                $newEidList = [];
                foreach ($getAllEidByGroupName as $eidC) {
                    if (!in_array($eidC->EID, $eidCounter)) {
                        $eidCounter[] = $eidC->EID;
                        $newEidList[] = $eidC->EID;
                    }
                }
                $getAllEidByGroupName = $newEidList;
                /* fix $getAllEidByGroupName because one employee EID is in multiple group */

                $getTotalEmployeeinCity = EmployeeMaster::select('EID')
                    ->whereHas('getUserInfo')
                    ->whereIn('EDesg', $whereInDesg)
                    ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                    ->whereIn('Employee_Master.EID', $getAllEidByGroupName)
                    ->where(['ECity' => $cval->id]);

                $getAllEmployeeId = $getTotalEmployeeinCity->get();
                $totalNoOfEmployeeInCity = count($getAllEmployeeId);
                if ($totalNoOfEmployeeInCity == 0) {
                    continue;
                }

                /* Start adding group wise score */
                $getScore = EmployeeScore::select(
                    'Employee_Master.EID',
                    'Employee_Master.ECity',
                    DB::raw('sum(EmployeeScore.CountProspect) as CountProspect'),
                    DB::raw('sum(EmployeeScore.CountNACHSignup) as NACH'),
                    DB::raw('sum(EmployeeScore.CountNachAVReject) as NachAVReject'),
                    DB::raw('sum(EmployeeScore.CountENACHSignup) as ENACH'),
                    DB::raw('sum(EmployeeScore.CountENachAVReject) as ENachAVReject'),
                    DB::raw('sum(EmployeeScore.CountOnlineSignup) as Online'),
                    DB::raw('sum(EmployeeScore.CountOnlineAVReject) as OnlineAVReject')
                )
                    ->whereDate('CurrentDate', '=', date('Y-m-d'))
                    ->join('Employee_Master', 'Employee_Master.EID', 'EmployeeScore.EID')
                    ->whereIn('EDesg', $whereInDesg)
                    ->whereIn('Employee_Master.EID', $getAllEidByGroupName)
                    ->where('Employee_Master.ECity', '=', $cval->id)
                    ->groupBy('Employee_Master.ECity')
                    ->first();

                $signUpCount = Signup::whereDate('PDate', date('Y-m-d'))
                    ->join('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->whereIn('EDesg', $whereInDesg)
                    ->where('Employee_Master.ECity', '=', $cval->id)
                    ->whereIn('Employee_Master.EID', $getAllEidByGroupName)
                    ->count();

                /* Signup rejected in today's date */
                $signUpReject = Signup::whereDate('Signup_AccountChk.created_at', date('Y-m-d'))
                    ->whereDate('Signup.PDate', date('Y-m-d'))
                    ->join('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->join('Signup_AccountChk', 'Signup.CRM_ID', 'Signup_AccountChk.CRM_ID')
                    ->whereIn('EDesg', $whereInDesg)
                    ->where('Employee_Master.ECity', '=', $cval->id)
                    ->where(['Signup_AccountChk.BOStatUpdate' => SignupAccountChk::STATUS_REJECTED])
                    ->whereIn('Employee_Master.EID', $getAllEidByGroupName)
                    ->count();

                $actualSignup = $signUpCount - $signUpReject;


                $activeEmployeeToday = Attendance::whereIn('Attendance.EID', $getAllEmployeeId)
                    ->leftJoin('Employee_Master', 'Attendance.EID', 'Employee_Master.EID')
                    ->whereIn('EDesg', $whereInDesg)
                    ->whereDate('InStamp', date('Y-m-d'))
                    ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                    ->groupBy('Attendance.EID')
                    ->get()
                    ->count();

                $currentEmployeeToday = Attendance::whereIn('Attendance.EID', $getAllEmployeeId)
                    ->leftJoin('Employee_Master', 'Attendance.EID', 'Employee_Master.EID')
                    ->whereIn('EDesg', $whereInDesg)
                    ->whereDate('InStamp', date('Y-m-d'))
                    ->where(['OutStamp' => null])
                    ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                    ->groupBy('Attendance.EID')
                    ->get()
                    ->count();

                $nach = $getScore ? $getScore['NACH'] - $getScore['NachAVReject'] : 0;
                $enach = $getScore ? $getScore['ENACH'] - $getScore['ENachAVReject'] : 0;
                $online = $getScore ? $getScore['Online'] - $getScore['OnlineAVReject'] : 0;

                /* name change */
                $getCityFirst3Letter = substr(strtolower($cval->Ecity), 0, 3);
                $groupName = strtolower($tgd->GrpName);
                $newString = str_replace(strtolower($cval->Ecity), $getCityFirst3Letter, $groupName);
                $newGroupName = strtoupper($newString);
                /**/


                $getScoreData = [];
                $getScoreData['isSub'] = true;
                $getScoreData['ECityName'] = $newGroupName ?? '';
                $getScoreData['TotalFr'] = $totalNoOfEmployeeInCity;
                $getScoreData['ActiveFr'] = $activeEmployeeToday;
                $getScoreData['CurrentActive'] = $currentEmployeeToday;
                $getScoreData['Presentation'] = $getScore['CountProspect'] ?? 0;
                $getScoreData['NACH'] = $nach ?? 0;
                $getScoreData['ENACH'] = $enach ?? 0;
                $getScoreData['Online'] = $online ?? 0;
                $getScoreData['Total'] = $getScoreData['NACH'] + $getScoreData['ENACH'] + $getScoreData['Online'];
                $getScoreData['SignupCount'] = $signUpCount;
                $getScoreData['SignUpReject'] = $signUpReject;
                $getScoreData['ActualSignup'] = $actualSignup;
                $getScoreData['AccValQueue'] = Common::getDonorCount(null, $cval->id, $getAllEidByGroupName);
                $getScoreData['WelcomeCallQueue'] = Common::getDonorCount(Signup::ACCOUNT_CHECK, $cval->id, $getAllEidByGroupName);
                $getScoreData['FormProcessingQueue'] = Common::getDonorCount(Signup::QUALITY_CHECK, $cval->id, $getAllEidByGroupName);
                $getScoreData['DataEntryQueue'] = Common::getDonorCount(Signup::DATA_ENTRY_CHECK, $cval->id, $getAllEidByGroupName);

                $groupWiseScore[] = $getScoreData;
                /* end adding group wise score */
            }
        }
        return $groupWiseScore;
    }

    public function getGroupWiseScoreMonthly($cval) {
        $groupWiseScoreMonthly = [];
        $whereInDesg = [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL];
        $getAllCharityCode = CharityCode::get();
        foreach ($getAllCharityCode as $val) {
            $chatiryCode = str_replace(' ', '%', $val->CharityCode);
            $cityName = $cval->Ecity;
            $getTeamGroup = ChatGroups::where(['type' => ChatGroups::TypeTeam])
                ->where('GrpName', 'LIKE', $chatiryCode . '%' . $cityName . '%')
//                ->orderBy('GrpName')
                ->orderBy('id')
                ->get();

            $eidCounter = [];
            foreach ($getTeamGroup as $key => $tgd) {

                $getAllEidByGroupName = ChatTeamGroup::select('EID')
                    ->where(['GrpId' => $tgd->GrpId])
                    ->groupBy('EID')
                    ->get();
                /* fix $getAllEidByGroupName because one employee EID is in multiple group */
                $newEidList = [];
                foreach ($getAllEidByGroupName as $eidC) {
                    if (!in_array($eidC->EID, $eidCounter)) {
                        $eidCounter[] = $eidC->EID;
                        $newEidList[] = $eidC->EID;
                    }
                }
                $getAllEidByGroupName = $newEidList;
                /* fix $getAllEidByGroupName because one employee EID is in multiple group */

                $getTotalEmployeeinCity = EmployeeMaster::select('EID')
                    ->whereHas('getUserInfo')
                    ->whereIn('EDesg', $whereInDesg)
                    ->whereIn('Employee_Master.EID', $getAllEidByGroupName)
                    ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
                    ->where(['ECity' => $cval->id]);

                $getAllEmployeeId = $getTotalEmployeeinCity->get();
                $totalNoOfEmployeeInCity = count($getAllEmployeeId);
                if ($totalNoOfEmployeeInCity == 0) {
                    continue;
                }

                /* Start adding group wise score */
                $getScoreMonthly = EmployeeScore::select(
                    'Employee_Master.EID',
                    'Employee_Master.ECity',
                    DB::raw('sum(EmployeeScore.CountProspect) as CountProspect'),
                    DB::raw('sum(EmployeeScore.CountNACHSignup) as NACH'),
                    DB::raw('sum(EmployeeScore.CountNachAVReject) as NachAVReject'),
                    DB::raw('sum(EmployeeScore.WCNachReject) as WCNachReject'),
                    DB::raw('sum(EmployeeScore.FPNachReject) as FPNachReject'),
                    DB::raw('sum(EmployeeScore.DataEntryNachReject) as DataEntryNachReject'),
                    DB::raw('sum(EmployeeScore.CountENACHSignup) as ENACH'),
                    DB::raw('sum(EmployeeScore.WCENachReject) as WCENachReject'),
                    DB::raw('sum(EmployeeScore.DataEntryENachReject) as DataEntryENachReject'),
                    DB::raw('sum(EmployeeScore.CountENachAVReject) as ENachAVReject'),
                    DB::raw('sum(EmployeeScore.CountOnlineSignup) as Online'),
                    DB::raw('sum(EmployeeScore.WCOnlineReject) as WCOnlineReject'),
                    DB::raw('sum(EmployeeScore.DataEntryOnlineReject) as DataEntryOnlineReject'),
                    DB::raw('sum(EmployeeScore.CountOnlineAVReject) as OnlineAVReject')
                )
                    ->whereYear('CurrentDate', '=', date('Y'))
                    ->whereMonth('CurrentDate', '=', date('m'))
                    ->join('Employee_Master', 'Employee_Master.EID', 'EmployeeScore.EID')
                    ->whereIn('EDesg', $whereInDesg)
                    ->whereIn('Employee_Master.EID', $getAllEidByGroupName)
                    ->where('Employee_Master.ECity', '=', $cval->id)
                    ->groupBy('Employee_Master.ECity')
                    ->first();


                $activeEmployeeMonthly = Attendance::whereIn('Attendance.EID', $getAllEmployeeId)
                    ->leftJoin('Employee_Master', 'Attendance.EID', 'Employee_Master.EID')
                    ->whereIn('EDesg', $whereInDesg)
                    ->whereIn('Employee_Master.EID', $getAllEidByGroupName)
                    ->whereMonth('InStamp', date('m'))
                    ->whereYear('InStamp', date('Y'))
                    ->whereIn('Attendance', [Attendance::ATTENDANCE_P, Attendance::ATTENDANCE_HD])
                    ->where(function ($q) {
                        return $q->whereIn('AttnRemarks', [Attendance::AttnRemarks_Present, Attendance::AttnRemarks_HalfDay, null])
                            ->orWhere(['AttnRemarks' => null]);
                    })
                    ->count();

                $signUpCount = Signup::whereMonth('Signup.PDate', date('m'))
                    ->whereYear('Signup.PDate', date('Y'))
                    ->join('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->whereIn('EDesg', $whereInDesg)
                    ->whereIn('Employee_Master.EID', $getAllEidByGroupName)
                    ->where('Employee_Master.ECity', '=', $cval->id)
                    ->count();

                /* Signup rejected in today's date */
                $signUpReject = Signup::whereMonth('Signup_AccountChk.created_at', date('m'))
                    ->whereYear('Signup_AccountChk.created_at', date('Y'))
                    ->whereMonth('Signup.PDate', date('m'))
                    ->whereYear('Signup.PDate', date('Y'))
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->leftJoin('Signup_AccountChk', 'Signup.CRM_ID', 'Signup_AccountChk.CRM_ID')
                    ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                    ->leftJoin('Signup_FormChk', 'Signup.CRM_ID', 'Signup_FormChk.CRM_ID')
                    ->leftJoin('Signup_WlcmCall', 'Signup.CRM_ID', 'Signup_WlcmCall.CRM_ID')
                    ->whereIn('EDesg', $whereInDesg)
                    ->whereIn('Employee_Master.EID', $getAllEidByGroupName)
                    ->where('Employee_Master.ECity', '=', $cval->id)
                    ->where(function ($q) {
                        return $q->where(['Signup_AccountChk.BOStatUpdate' => SignupAccountChk::STATUS_REJECTED])
                            ->orWhere(['Signup_DataEntry.dataEntryStatus' => SignupDataEntry::dataEntryStatus_Reject])
                            ->orWhere(['Signup_FormChk.FFPStatus' => SignupFormChk::FFPStatus_Reject])
                            ->orWhere(['Signup_WlcmCall.Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_rejected]);
                    })
                    ->count();

                $actualSignup = $signUpCount - $signUpReject;
                $nach = $getScoreMonthly ? $getScoreMonthly['NACH'] - $getScoreMonthly['NachAVReject'] - $getScoreMonthly['WCNachReject'] - $getScoreMonthly['FPNachReject'] - $getScoreMonthly['DataEntryNachReject'] : 0;
                $enach = $getScoreMonthly ? $getScoreMonthly['ENACH'] - $getScoreMonthly['ENachAVReject'] - $getScoreMonthly['WCENachReject'] - $getScoreMonthly['DataEntryENachReject'] : 0;
                $online = $getScoreMonthly ? $getScoreMonthly['Online'] - $getScoreMonthly['OnlineAVReject'] - $getScoreMonthly['WCOnlineReject'] - $getScoreMonthly['DataEntryOnlineReject'] : 0;

                $nach = ($nach > 0) ? $nach : 0;
                $enach = ($enach > 0) ? $enach : 0;
                $online = ($online > 0) ? $online : 0;

                $totalSignUpMonthly = $nach + $enach + $online;

                /* name change */
                $getCityFirst3Letter = substr(strtolower($cval->Ecity), 0, 3);
//                echo $getCityFirst3Letter;die;
                $groupName = strtolower($tgd->GrpName);
                $newString = str_replace(strtolower($cval->Ecity), $getCityFirst3Letter, $groupName);
                $newGroupName = strtoupper($newString);
                /**/

                $getScoreMonthlyData = [];
                $getScoreMonthlyData['isSub'] = true;
                $getScoreMonthlyData['ECityName'] = $newGroupName ?? '';
                $getScoreMonthlyData['TotalFr'] = $totalNoOfEmployeeInCity;
                $getScoreMonthlyData['TotalManday'] = $activeEmployeeMonthly;
                $getScoreMonthlyData['Presentation'] = $getScoreMonthly['CountProspect'] ?? 0;
                $getScoreMonthlyData['NACH'] = $nach ?? 0;
                $getScoreMonthlyData['ENACH'] = $enach ?? 0;
                $getScoreMonthlyData['Online'] = $online ?? 0;
                $getScoreMonthlyData['Total'] = $totalSignUpMonthly;
                $getScoreMonthlyData['SignupCount'] = $signUpCount;
                $getScoreMonthlyData['SignUpReject'] = $signUpReject;
                $getScoreMonthlyData['ActualSignup'] = $actualSignup;
                $mandayProd = 0;
                if ($activeEmployeeMonthly != 0) {
                    $mandayProd = $totalSignUpMonthly / $activeEmployeeMonthly;
                }
                $getScoreMonthlyData['Manday'] = number_format((float)$mandayProd, '2', '.', '');
                $groupWiseScoreMonthly[] = $getScoreMonthlyData;
                /* end adding group wise score */
            }
        }
        return $groupWiseScoreMonthly;
    }

    public function index() {
        $this->test();
        list($getScore, $getScoreMonthly, $monthlyTotalMandayProductivity) = $this->getDashboardScore();
        $expended = false;
        if (request()->isMethod('post')) {
            return view('dashboardTable', compact('getScore', 'getScoreMonthly', 'monthlyTotalMandayProductivity', 'expended'));
            return $this->sendResponse(true, '', '', ['view' => $view], 'setDashboardTable');
        }
        return view('home', compact('getScore', 'getScoreMonthly', 'monthlyTotalMandayProductivity', 'expended'));
    }

    public function employeeManagement() {
        return view('employeeManagement');
    }

    public function getTableOnlyForPdf() {
        list($getScore, $getScoreMonthly, $monthlyTotalMandayProductivity) = $this->getDashboardScore();
        $expended = true;
        return view('dashboardTablePdf', compact('getScore', 'getScoreMonthly', 'monthlyTotalMandayProductivity', 'expended'));
    }

    public function dashboardReport() {
        $input = request()->all();
        $date = Common::fixDateFormat($input['date'], 'd-m-Y', 'Y-m-d');
        $fileName = $date . '_dashboard_result.pdf';
        $checkFilePath = public_path() . '/pdf/' . $fileName;
        if (file_exists($checkFilePath)) {
            $fileData = [
                'fileUrl' => asset('/pdf/' . $fileName)
            ];
            return $this->sendResponse(true, '', 'Report exported successfully.', $fileData, 'exportDataTable');
        } else {
            return $this->sendResponse(false, '', 'No Record Exist.');
        }
    }

    public function checkAccNoAndTranId() {
        $input = request()->all();
        if ($input['AccountNo'] != '' || $input['OnlineTransactionID'] != '') {
            $check = SignupAccountChk::select('*');
            if ($input['AccountNo'] != '') {
                $check = $check->orWhere(['AccountNo' => $input['AccountNo']]);
            }
            if ($input['OnlineTransactionID'] != '') {
                $check = $check->orWhere(['OnlineTransactionID' => $input['OnlineTransactionID']]);
            }
            $check = $check->first();
            if ($check) {
                $AccountNo = $check->AccountNo ? true : false;
                $OnlineTransactionID = $check->OnlineTransactionID ? true : false;
                if ($AccountNo && $OnlineTransactionID) {
                    $message = 'Account No and Online Transaction ID both available in system';
                } elseif ($AccountNo) {
                    $message = 'Account No available in system';
                } elseif ($OnlineTransactionID) {
                    $message = 'Online Transaction ID available in system';
                }
                return $this->sendResponse(true, '', $message);
            }
        }
    }

    public function saveTempSid() {
        $input = request()->all();
        if ($input['crmId'] != '') {
            $checkWelcomeCall = SignupWlcmCall::where(['CRM_ID' => $input['crmId']])->first();
            if ($checkWelcomeCall) {
                $checkWelcomeCall->update(['tempCallSid' => $input['CallSid']]);
            }
        }
    }

}
