<?php

namespace App\Http\Controllers;

use App\Model\ChatCityGroup;
use App\Model\ChatTeamGroup;
use App\Model\Signup;
use App\Helper\Common;
use App\Model\Attendance;
use App\Model\CharityCode;
use App\Model\SignupFormChk;
use App\Model\EmployeeECity;
use App\Model\EmployeeEDesg;
use App\Model\EmployeeScore;
use App\Model\EmployeeMaster;
use App\Model\SignupWlcmCall;
use App\Model\ProspectMaster;
use App\Model\SignupDataEntry;
use App\Model\ClientDataExport;
use App\Model\SignupAccountChk;
use App\Model\BankingDebitStatus;
use App\Model\BankingEnrolStatus;
use App\Model\WlcmCallDetail;
use Illuminate\Support\Facades\DB;
use App\Model\EmployeeTargetQaulity;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller {

    public function __construct() {
        parent::__construct();
        ini_set('max_execution_time', -1);
        set_time_limit(0);
    }

    public function getBackOfficeFilter() {
        $input = request()->all();
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d');
        if (isset($input['dateYearMonth']) && $input['dateYearMonth']) {
            $year = Common::fixDateFormat($input['dateYearMonth'], 'd-m-Y', 'Y');
            $month = Common::fixDateFormat($input['dateYearMonth'], 'd-m-Y', 'm');
            $day = Common::fixDateFormat($input['dateYearMonth'], 'd-m-Y', 'd');
        }
        if (isset($input['YearMonth']) && $input['YearMonth']) {
            $year = Common::fixDateFormat($input['YearMonth'], 'm-Y', 'Y');
            $month = Common::fixDateFormat($input['YearMonth'], 'm-Y', 'm');
            $day = date('d');
        }
        $city = '';
        if (isset($input['city']) && $input['city']) {
            $checkCity = EmployeeECity::find($input['city']);
            $city = $checkCity ? $checkCity->id : '';
        }

        $allowECity = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowECity)) {
            $getCity = $this->user->ECity;
            if ($getCity) {
                $checkCity = EmployeeECity::find($getCity);
                $city = $checkCity ? $checkCity->id : '';
            }
        }

        $charityCode = '';
        if (isset($input['CharityCode']) && $input['CharityCode']) {
            $checkCharityCode = CharityCode::find($input['CharityCode']);
            $charityCode = $checkCharityCode ? $checkCharityCode->id : '';
        }
        $locType = '';
        if (isset($input['LocType']) && $input['LocType']) {
            $locTypes = [Signup::LOCTYPE_PERMISSION, Signup::LOCTYPE_STREET];
            $locType = (in_array($input['LocType'], $locTypes)) ? $input['LocType'] : '';
        }
        if (isset($input['dateFrom']) && $input['dateFrom']) {
            $dateFrom = Common::fixDateFormat($input['dateFrom'], 'd-m-Y', 'Y-m-d');
        }
        if (isset($input['dateTo']) && $input['dateTo']) {
            $dateTo = Common::fixDateFormat($input['dateTo'], 'd-m-Y', 'Y-m-d');
        }
        /**
         * Update dateTo
         */
//        $dateTo = $this->upateDateTo($dateTo);
        return [$year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType];
    }

    public function productivityReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.productivityReport', compact('getAllCity', 'getCharityCode'));
    }

    public function productivityReport(Request $request) {

        $input = request()->all();

        if (!$input['YearMonth']) {
            return $this->sendResponse(false, '', 'Please Select Month.');
        }


        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();
        /**
         *
         * Filters
         *
         */
        $dataArray = [];
        $getEmployeeList = EmployeeMaster::whereIn('EDesg', [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_STL])
            ->whereHas('getUserInfo')
            ->where('ECity', '!=', '')
            ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
            ->where('ECity', '!=', null);
        if ($city) {
            $getEmployeeList = $getEmployeeList->where(['ECity' => $city]);
        }
        if ($charityCode) {
            $getEmployeeList = $getEmployeeList->where(['CharityCode' => $charityCode]);
        }
        $getEmployeeList = $getEmployeeList->get();

        foreach ($getEmployeeList as $employee) {
            //Add group name and info
            $getCityGroup = ChatCityGroup::select('GrpName')
                ->where(['EID' => $employee->EID])
                ->groupBy('GrpId')->get()->toArray();
            $getTeamGroup = ChatTeamGroup::select('GrpName')
                ->where(['EID' => $employee->EID])
                ->groupBy('GrpId')->get()->toArray();
            $cityGroupNames = implode(', ', array_column($getCityGroup, 'GrpName'));
            $teamGroupNames = implode(', ', array_column($getTeamGroup, 'GrpName'));

            $record = [];
            $record['EID'] = $employee->EID;
            $record['FRName'] = $employee->EName;
            $record['City'] = $employee->getCity->Ecity ?? '';
            $record['Designation'] = $employee->getDesg->EDesg ?? '';
            $record['City Group'] = $cityGroupNames ?? '';
            $record['Team Group'] = $teamGroupNames ?? '';
            $record['PresentationPerDay'] = 0;
            $record['P2S'] = 0;
            $record['NACH'] = 0;
            $record['ENACH'] = 0;
            $record['ONLINE'] = 0;
            $record['Total'] = 0;
            $record['Target'] = 0;
            $record['RRR'] = 0;
            $record['Current RR'] = 0;
            $employeeScore = EmployeeScore::select(
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
                ->whereYear('CurrentDate', $year)
                ->whereMonth('CurrentDate', $month)
                ->where(['EID' => $employee->EID])
                ->groupBy('EID')
                ->first();
            $nachTotal = $enachTotal = $onlineTotal = 0;
            if ($employeeScore) {
                $nachTotal = $employeeScore->CountNACHSignup -
                    $employeeScore->CountNachAVReject -
                    $employeeScore->WCNachReject -
                    $employeeScore->FPNachReject -
                    $employeeScore->DataEntryNachReject;
                $nachTotal = ($nachTotal > 0) ? $nachTotal : 0;
                $enachTotal = $employeeScore->CountENACHSignup -
                    $employeeScore->CountENACHAVReject -
                    $employeeScore->WCENachReject -
                    $employeeScore->DataEntryENachReject;
                $enachTotal = ($enachTotal > 0) ? $enachTotal : 0;
                $onlineTotal = $employeeScore->CountOnlineSignup -
                    $employeeScore->CountOnlineAVReject -
                    $employeeScore->WCOnlineReject -
                    $employeeScore->DataEntryOnlineReject;
                $onlineTotal = ($onlineTotal > 0) ? $onlineTotal : 0;
            }
            $countProspectTotal = $employeeScore ? $employeeScore->CountProspect : 0;
            $countNachTotal = $nachTotal ?? 0;
            $countENACHTotal = $enachTotal ?? 0;
            $countOnlineTotal = $onlineTotal ?? 0;
            $totalSignupScoreCount = $countNachTotal + $countENACHTotal + $countOnlineTotal;

            $record['NACH'] = number_format((float)$countNachTotal, 2, '.', '');
            $record['ENACH'] = number_format((float)$countENACHTotal, 2, '.', '');
            $record['ONLINE'] = number_format((float)$countOnlineTotal, 2, '.', '');
            $record['Total'] = number_format((float)$totalSignupScoreCount, 2, '.', '');

            $fechTarget = EmployeeTargetQaulity::where(['EID' => $employee->EID])
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->first();
            $getUploadedTarget = $fechTarget ? $fechTarget->target : 0;
            $totalUploadedDays = $fechTarget ? $fechTarget->workingDays : 0;

            $record['Target'] = number_format((float)$getUploadedTarget, 2, '.', '');

            if ($totalSignupScoreCount != 0) {
                $countP2s = $countProspectTotal / $totalSignupScoreCount;
                $record['P2S'] = number_format((float)$countP2s, 2, '.', '');
            }


            $totalDayOfMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $allDays = range('1', $totalDayOfMonth);
            $countProspectWorkingDays = 0;
            $countProspectWorkingDaysForRR = 0;
            foreach ($allDays as $val) {
                /**
                 * Check if employee was present on that date
                 */
                $checkEmpPresent = Attendance::whereDate('InStamp', '=', $year . '-' . $month . '-' . $val)
                    ->where(['EID' => $employee->EID])
                    ->where(function ($arr) {
                        return $arr->where(['AttnRemarks' => Attendance::AttnRemarks_Present])
                            ->orWhere(['AttnRemarks' => Attendance::AttnRemarks_HalfDay])
                            ->orWhere(['Attendance' => Attendance::ATTENDANCE_P])
                            ->orWhere(['Attendance' => Attendance::ATTENDANCE_HD])
                            ->orWhere(['Attendance' => NULL]);
                    })
//                        ->where(function($att) {
//                            return $att->where(['Attendance' => Attendance::ATTENDANCE_P])
//                                    ->orWhere(['Attendance' => Attendance::ATTENDANCE_HD])
//                                    ->orWhere(['Attendance' => NULL]);
//                        })
                    ->first();
                $checkEmpPresentForRR = Attendance::whereDate('InStamp', '=', $year . '-' . $month . '-' . $val)
                    ->where(['EID' => $employee->EID])
                    ->first();
                if ($checkEmpPresentForRR) {
                    $countProspectWorkingDaysForRR++;
                }
                if ($checkEmpPresent) {
                    $countProspectWorkingDays++;
                }
                $getPerDayScore = EmployeeScore::select(
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
                    ->whereDate('CurrentDate', '=', $year . '-' . $month . '-' . $val)
                    ->where(['EID' => $employee->EID])
                    ->groupBy('EID')
                    ->first();
                $nachTotal = $enachTotal = $onlineTotal = 0;
                if ($getPerDayScore) {
                    $nachTotal = $getPerDayScore->CountNACHSignup -
                        $getPerDayScore->CountNachAVReject -
                        $getPerDayScore->WCNachReject -
                        $getPerDayScore->FPNachReject -
                        $getPerDayScore->DataEntryNachReject;
                    $nachTotal = ($nachTotal > 0) ? $nachTotal : 0;
                    $enachTotal = $getPerDayScore->CountENACHSignup -
                        $getPerDayScore->CountENACHAVReject -
                        $getPerDayScore->WCENachReject -
                        $getPerDayScore->DataEntryENachReject;
                    $enachTotal = ($enachTotal > 0) ? $enachTotal : 0;
                    $onlineTotal = $getPerDayScore->CountOnlineSignup -
                        $getPerDayScore->CountOnlineAVReject -
                        $getPerDayScore->WCOnlineReject -
                        $getPerDayScore->DataEntryOnlineReject;
                    $onlineTotal = ($onlineTotal > 0) ? $onlineTotal : 0;
                }
                $countNachTotal = $nachTotal ?? 0;
                $countENACHTotal = $enachTotal ?? 0;
                $countOnlineTotal = $onlineTotal ?? 0;


                if ($getPerDayScore) {
                    $totalSignup = $countNachTotal + $countENACHTotal + $countOnlineTotal;
                    $record[$val] = number_format((float)$totalSignup, 2, '.', '');
                } else {
                    $record[$val] = $checkEmpPresent ? 0 : null;
                }
            }
            if ($countProspectWorkingDays != 0) {
                $presentataionPerDay = $employeeScore ? ($employeeScore->CountProspect / $countProspectWorkingDays) : 0;
                $record['PresentationPerDay'] = number_format((float)$presentataionPerDay, 2, '.', '');
            }
            /**
             * Calculate RRR
             */
            $totalTargetRemaining = $getUploadedTarget - $totalSignupScoreCount;
            $getRemainWorkingDays = $totalUploadedDays - $countProspectWorkingDaysForRR;
            $remainWorkingDays = ($getRemainWorkingDays > 0) ? $getRemainWorkingDays : 0;
            if ($remainWorkingDays != 0) {
                $getTotalRRR = $totalTargetRemaining / $remainWorkingDays;
                $record['RRR'] = number_format((float)$getTotalRRR, 2, '.', '');
            }
            /**
             *
             * Current Drr
             *
             */
            if ($countProspectWorkingDays != 0) {
                $getNewDrr = $totalSignupScoreCount / $countProspectWorkingDays;
                $record['Current RR'] = number_format((float)$getNewDrr, 2, '.', '');
            }
            $dataArray[] = $record;
        }

        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, 'productivityCustomChanges', 'ProductivityReport');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
//            return redirect($getFileUrl);
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function streetVsPermissionReportView(Request $request) {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.streetVsPermissionReport', compact('getAllCity', 'getCharityCode'));
    }

    public function streetVsPermissionReport(Request $request) {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();

        $date1 = date_create($dateFrom);
        $date2 = date_create($dateTo);
        if ($date1 > $date2) {
            return $this->sendResponse(false, '', 'Invalid Date Difference.');
        }
        /**
         *
         * Filters
         *
         */
        $dataArray = [];
        $getEmployeeList = EmployeeMaster::whereIn('EDesg', [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_STL])
            ->whereHas('getUserInfo')
            ->where('ECity', '!=', '')
            ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
            ->where('ECity', '!=', null);
        if ($city) {
            $getEmployeeList = $getEmployeeList->where(['ECity' => $city]);
        }
        if ($charityCode) {
            $getEmployeeList = $getEmployeeList->where(['CharityCode' => $charityCode]);
        }
        $getEmployeeList = $getEmployeeList->get();


        foreach ($getEmployeeList as $employee) {

            //Add group name and info
            $getCityGroup = ChatCityGroup::select('GrpName')
                ->where(['EID' => $employee->EID])
                ->groupBy('GrpId')->get()->toArray();
            $getTeamGroup = ChatTeamGroup::select('GrpName')
                ->where(['EID' => $employee->EID])
                ->groupBy('GrpId')->get()->toArray();
            $cityGroupNames = implode(', ', array_column($getCityGroup, 'GrpName'));
            $teamGroupNames = implode(', ', array_column($getTeamGroup, 'GrpName'));

            $report = [];
            $report['EID'] = $employee->EID;
            $report['FRName'] = $employee->EName;
            $report['City'] = $employee->getCity->Ecity ?? '';
            $report['Designation'] = $employee->getDesg->EDesg ?? '';
            $report['City Group'] = $cityGroupNames ?? '';
            $report['Team Group'] = $teamGroupNames ?? '';

            $getStreetAttendanceCount = Attendance::where(['EID' => $employee->EID])
                ->whereBetween('InStamp', [$dateFrom, $dateTo])
                ->where(['LocType' => Attendance::LOCTYPE_STREET])
                ->count();
            $getPermissionAttendanceCount = Attendance::where(['EID' => $employee->EID])
                ->whereBetween('InStamp', [$dateFrom, $dateTo])
                ->where(['LocType' => Attendance::LOCTYPE_PERMISSION])
                ->count();


            $getStreetSignup = Signup::where(['EID' => $employee->EID])->whereBetween('PDate', [$dateFrom, $dateTo])
                ->where(['LocType' => Attendance::LOCTYPE_STREET])
                ->count();
            $getPermissionSignup = Signup::where(['EID' => $employee->EID])->whereBetween('PDate', [$dateFrom, $dateTo])
                ->where(['LocType' => Signup::LOCTYPE_PERMISSION])
                ->count();

            $report['Street-Day'] = $getStreetAttendanceCount;
            $report['Street-Signup'] = $getStreetSignup;
            $report['Street-Productivity'] = 0;
            if ($getStreetAttendanceCount != 0) {
                $streetProductivity = $getStreetSignup / $getStreetAttendanceCount;
                $report['Street-Productivity'] = number_format((float)$streetProductivity, 2, '.', '');
            }
            $report['Permission-Day'] = $getPermissionAttendanceCount;
            $report['Permission-Signup'] = $getPermissionSignup;
            $report['Permission-Productivity'] = 0;
            if ($getPermissionAttendanceCount != 0) {
                $permissionProductivity = $getPermissionSignup / $getPermissionAttendanceCount;
                $report['Permission-Productivity'] = number_format((float)$permissionProductivity, 2, '.', '');
            }
            $dataArray[] = $report;
        }
        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'streetVsPermission');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
//            return redirect($getFileUrl);
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function frPerformanceReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.frPerformanceReport', compact('getAllCity', 'getCharityCode'));
    }

    public function frPerformanceReport() {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();

        /**
         *
         * Filters
         *
         */
        $dataArray = [];
        $getEmployeeList = EmployeeMaster::whereHas('getUserInfo')
            ->whereIn('EDesg', [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_STL])
            ->where('ECity', '!=', '')
            ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
            ->where('ECity', '!=', null);
        if ($city) {
            $getEmployeeList = $getEmployeeList->where(['ECity' => $city]);
        }
        if ($charityCode) {
            $getEmployeeList = $getEmployeeList->where(['CharityCode' => $charityCode]);
        }
        $getEmployeeList = $getEmployeeList->get();

        foreach ($getEmployeeList as $employee) {
            //Add group name and info
            $getCityGroup = ChatCityGroup::select('GrpName')
                ->where(['EID' => $employee->EID])
                ->groupBy('GrpId')->get()->toArray();
            $getTeamGroup = ChatTeamGroup::select('GrpName')
                ->where(['EID' => $employee->EID])
                ->groupBy('GrpId')->get()->toArray();
            $cityGroupNames = implode(', ', array_column($getCityGroup, 'GrpName'));
            $teamGroupNames = implode(', ', array_column($getTeamGroup, 'GrpName'));


            $record = [];
            $record['FRName'] = $employee->EName;
            $record['City'] = $employee->getCity->Ecity ?? '';
            $record['Designation'] = $employee->getDesg->EDesg ?? '';
            $record['City Group'] = $cityGroupNames ?? '';
            $record['Team Group'] = $teamGroupNames ?? '';
            $employeeScore = EmployeeScore::select(
                DB::raw('sum(CountProspect) as CountProspect'),
                DB::raw('sum(CountNACHSignup) as CountNACHSignup'),
                DB::raw('sum(CountENACHSignup) as CountENACHSignup'),
                DB::raw('sum(CountOnlineSignup) as CountOnlineSignup')
            )
//                    ->whereYear('CurrentDate', $year)
//                    ->whereMonth('CurrentDate', $month)
                ->whereBetween('CurrentDate', [$dateFrom, $dateTo])
                ->where(['EID' => $employee->EID])
                ->groupBy('EID')
                ->first();
            $fechTarget = EmployeeTargetQaulity::where(['EID' => $employee->EID])
                ->whereMonth('date', date('m'))
                ->whereYear('date', date('Y'))
                ->first();
            $getQualityScore = $fechTarget ? $fechTarget->quality : 0;

            $getAttendance = Attendance::where(['EID' => $employee->EID])
                ->whereBetween('InStamp', [$dateFrom, $dateTo])
                ->where(function ($arr) {
                    return $arr->where(['AttnRemarks' => Attendance::AttnRemarks_Present])
                        ->orWhere(['AttnRemarks' => Attendance::AttnRemarks_HalfDay])
                        ->orWhere(['Attendance' => Attendance::ATTENDANCE_P])
                        ->orWhere(['Attendance' => Attendance::ATTENDANCE_HD])
                        ->orWhere(['Attendance' => NULL]);
                })
//                    ->where(function($att) {
//                        return $att->where(['Attendance' => Attendance::ATTENDANCE_P])
//                                ->orWhere(['Attendance' => Attendance::ATTENDANCE_HD])
//                                ->orWhere(['Attendance' => NULL]);
//                    })
                ->count();


            $totalDays = $getAttendance;

            $presentataionPerDay = 0;
            if ($totalDays != 0) {
                $presentataionPerDay = $employeeScore ? ($employeeScore->CountProspect / $totalDays) : 0;
            }
            $record['TotalPresentation'] = '0.00';
            if ($employeeScore) {
                $record['TotalPresentation'] = number_format((float)$employeeScore->CountProspect, 2, '.', '');
            }
            $record['PresentationPerDay'] = number_format((float)$presentataionPerDay, 2, '.', '');

            $countProspectTotal = $employeeScore ? $employeeScore->CountProspect : 0;
            $countNachTotal = $employeeScore ? $employeeScore->CountNACHSignup : 0;
            $countENACHTotal = $employeeScore ? $employeeScore->CountENACHSignup : 0;
            $countOnlineTotal = $employeeScore ? $employeeScore->CountOnlineSignup : 0;
            $totalCount = $countNachTotal + $countENACHTotal + $countOnlineTotal;

//            $getP2s = 0;
//            if ($totalCount != 0) {
//                $getP2s = $countProspectTotal / $totalCount;
//            }
//            $record['P2S'] = number_format((float) $getP2s, '2', '.', '');
            /**
             * Count from signup table
             */
            $signupNachTotal = Signup::where(['EID' => $employee->EID])
                ->where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
//                    ->whereYear('PDate', $year)
//                    ->whereMonth('PDate', $month)
                ->whereBetween('PDate', [$dateFrom, $dateTo])
                ->count();
            $signupEnachTotal = Signup::where(['EID' => $employee->EID])
                ->where(['ModeOfDonation' => Signup::MODEOFDONATION_ENACH])
//                    ->whereYear('PDate', $year)
//                    ->whereMonth('PDate', $month)
                ->whereBetween('PDate', [$dateFrom, $dateTo])
                ->count();
            $signupOnlineTotal = Signup::where(['EID' => $employee->EID])
                ->where(['ModeOfDonation' => Signup::MODEOFDONATION_ONLINE])
//                    ->whereYear('PDate', $year)
//                    ->whereMonth('PDate', $month)
                ->whereBetween('PDate', [$dateFrom, $dateTo])
                ->count();
            $getTotalSignup = $signupNachTotal + $signupEnachTotal + $signupOnlineTotal;

            $getP2s = 0;
            if ($getTotalSignup != 0) {
                $getP2s = $countProspectTotal / $getTotalSignup;
            }
            $record['P2S'] = number_format((float)$getP2s, '2', '.', '');
            $record['NACH'] = $signupNachTotal;
            $record['ENACH'] = $signupEnachTotal;
            $record['ONLINE'] = $signupOnlineTotal;
            $record['Total'] = $getTotalSignup;

            /**
             * Internal reject
             */
            $rejectOnAv = Signup::whereHas('getSignupAccCheck', fn($q) => $q->whereBetween('updated_at', [$dateFrom, $dateTo])
                ->where(['BOStatUpdate' => SignupAccountChk::STATUS_REJECTED]))
                ->where(['EID' => $employee->EID])
                ->count();
            $rejectOnWlcmCall = Signup::whereHas('getWelcomeCall', fn($q) => $q->whereBetween('updated_at', [$dateFrom, $dateTo])
                ->where(['Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_rejected]))
                ->where(['EID' => $employee->EID])
                ->count();
            $rejectOnFormCheck = Signup::whereHas('getSignupFormChk', fn($q) => $q->whereBetween('updated_at', [$dateFrom, $dateTo])
                ->where(['FFPStatus' => SignupFormChk::FFPStatus_Reject]))
                ->where(['EID' => $employee->EID])
                ->count();

            $rejectOnDataEntry = Signup::whereHas('getSignupDataEntry', fn($q) => $q->whereBetween('updated_at', [$dateFrom, $dateTo])
                ->where(['dataEntryStatus' => SignupDataEntry::dataEntryStatus_Reject]))
                ->where(['EID' => $employee->EID])
                ->count();

            $internalReject = $rejectOnAv + $rejectOnWlcmCall + $rejectOnFormCheck + $rejectOnDataEntry;
            $record['Internal Reject'] = $internalReject;
            /**
             * Internal rejection %
             */
            $getInternalRejPer = 0;
            if ($getTotalSignup != 0) {
                $getInternalRejPer = ($internalReject / $getTotalSignup) * 100;
            }
            $record['Internal Rejection %'] = number_format((float)$getInternalRejPer, '2', '.', '') . ' %';
            /**
             * Get enrol $
             */
            /**
             * 90 day before date
             */
            $date = new \DateTime();
            $date90DayBefore = $date->modify('-90 days')->format('Y-m-d');
            /*
             * 90 day before date
             */

            $getEnrolPerSuccess = BankingEnrolStatus::where(['EID' => $employee->EID])
                ->where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusSuccess])
                ->where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getEnrolPerFail = BankingEnrolStatus::where(['EID' => $employee->EID])
                ->where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusFail])
                ->where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();

            $enrolDividedBy = $getEnrolPerSuccess + $getEnrolPerFail;
            $getEnrolPer = 0;
            if ($enrolDividedBy != 0) {
                $getEnrolPer = ($getEnrolPerSuccess / $enrolDividedBy) * 100;
            }
            $record['Enrol'] = number_format((float)$getEnrolPer, '2', '.', '') . ' %';

            /**
             * get debit %
             */
            $getDebitPerSuccess = BankingDebitStatus::where(['EID' => $employee->EID])
                ->where(['Debit_Attempt' => 1])
                ->where(['DebitStatus' => BankingDebitStatus::DebitStatusSuccess])
                ->where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getDebitPerFail = BankingDebitStatus::where(['EID' => $employee->EID])
                ->where(['Debit_Attempt' => 1])
                ->where(['DebitStatus' => BankingDebitStatus::DebitStatusFail])
                ->where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();

            $debitDividedBy = $getDebitPerSuccess + $getDebitPerFail;
            $getDebitPer = 0;
            if ($debitDividedBy != 0) {
                $getDebitPer = ($getDebitPerSuccess / $debitDividedBy) * 100;
            }
            $record['Debit Nach'] = number_format((float)$getDebitPer, '2', '.', '') . ' %';

            $getDebitPerSuccess = BankingDebitStatus::where(['EID' => $employee->EID])
                ->where(['Debit_Attempt' => 1])
                ->where(['DebitStatus' => BankingDebitStatus::DebitStatusSuccess])
                ->where(['ModeOfDonation' => Signup::MODEOFDONATION_ENACH])
                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getDebitPerFail = BankingDebitStatus::where(['EID' => $employee->EID])
                ->where(['Debit_Attempt' => 1])
                ->where(['DebitStatus' => BankingDebitStatus::DebitStatusFail])
                ->where(['ModeOfDonation' => Signup::MODEOFDONATION_ENACH])
                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();

            $debitDividedBy = $getDebitPerSuccess + $getDebitPerFail;
            $getDebitPer = 0;
            if ($debitDividedBy != 0) {
                $getDebitPer = ($getDebitPerSuccess / $debitDividedBy) * 100;
            }
            $record['Debit ENach'] = number_format((float)$getDebitPer, '2', '.', '') . ' %';
            /*
             * get enrol cancel
             */
            $getEnrolCancel = BankingEnrolStatus::where(['EID' => $employee->EID])
                ->where(['PledgeStatus' => BankingEnrolStatus::PledgeStatusCancel])
                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getEnrolActive = BankingEnrolStatus::where(['EID' => $employee->EID])
                ->where(['PledgeStatus' => BankingEnrolStatus::PledgeStatusActive])
                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getEnrolNull = BankingEnrolStatus::where(['EID' => $employee->EID])
                ->where(['PledgeStatus' => null])
                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();

            $pledgeDividedBy = $getEnrolCancel + $getEnrolActive + $getEnrolNull;
            $getCancelPer = 0;
            if ($pledgeDividedBy != 0) {
                $getCancelPer = ($getEnrolCancel / $pledgeDividedBy) * 100;
            }

            $record['Canx'] = number_format((float)$getCancelPer, '2', '.', '') . ' %';

            $record['Quality'] = $getQualityScore . ' %';

            $dataArray[] = $record;
        }
        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'frPerformance');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function donorQualityReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.donorQualityReport', compact('getAllCity', 'getCharityCode'));
    }

    public function donorQualityReport() {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();
        $date1 = date_create($dateFrom);
        $date2 = date_create($dateTo);
        if ($date1 > $date2) {
            return $this->sendResponse(false, '', 'Invalid Date Difference.');
        }
        /*         * ***************************************************************************** */
        $getAllCity = EmployeeECity::get();
        if ($city) {
            $getAllCity = EmployeeECity::where(['id' => $city])->get();
        }

        $onlineData = [];
        $nachData = [];
        $enachData = [];
        foreach ($getAllCity as $key => $val) {
            $nachData[] = $this->getQualityReportRecord($val, Signup::MODEOFDONATION_NACH);
            $enachData[] = $this->getQualityReportRecord($val, Signup::MODEOFDONATION_ENACH);
            $onlineData[] = $this->getQualityReportRecord($val, Signup::MODEOFDONATION_ONLINE);
        }

        /**
         * Export
         */
        $fileUrl = [];
        if (count($onlineData)) {
            $fileUrl[] = $this->writeXlsxFile($onlineData, null, 'donorQualityOnline');
        }
        if (count($nachData)) {
            $fileUrl[] = $this->writeXlsxFile($nachData, null, 'donorQualityNach');
        }
        if (count($enachData)) {
            $fileUrl[] = $this->writeXlsxFile($enachData, null, 'donorQualityEnach');
        }

        if (count($fileUrl)) {
            $responseData = [
                'fileUrl' => $fileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function getQualityReportRecord($cityObj, $modeOfDonation) {
        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();
        $date = new \DateTime();
        $date90DayBefore = $date->modify('-30 days')->format('Y-m-d');
        $val = $cityObj;
        $record = [];
        $record['City'] = $val->Ecity;
        $totalSignupRecord = Signup::whereHas('getEmployee', function ($q) use ($val, $charityCode) {
            return $q->where(['ECity' => $val->id]);
        })
            ->where(['ModeOfDonation' => $modeOfDonation]);
        if ($charityCode) {
            $totalSignupRecord = $totalSignupRecord->where(['CharityCode' => $charityCode]);
        }
        if ($dateFrom && $dateTo) {
            $totalSignupRecord = $totalSignupRecord->whereBetween('PDate', [$dateFrom, $dateTo]);
        }
        $totalSignupRecord = $totalSignupRecord->count();


        if ($modeOfDonation == Signup::MODEOFDONATION_NACH) {
            $record['Nach Signup'] = $totalSignupRecord;
        }
        if ($modeOfDonation == Signup::MODEOFDONATION_ENACH) {
            $record['ENach Signup'] = $totalSignupRecord;
        }
        if ($modeOfDonation == Signup::MODEOFDONATION_ONLINE) {
            $record['Online Signup'] = $totalSignupRecord;
        }

        /*         * ** Account validateion * */
        $acceptedFromAccountCheck = SignupAccountChk::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->where(['BOStatUpdate' => SignupAccountChk::STATUS_ACCEPTED])
            ->count();
        $totalFromAccountCheck = SignupAccountChk::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->whereIn('BOStatUpdate', [SignupAccountChk::STATUS_ACCEPTED, SignupAccountChk::STATUS_REJECTED])
            ->count();
        $accValCol = '0';
        if ($totalFromAccountCheck != 0) {
            $accValCol = ($acceptedFromAccountCheck / $totalFromAccountCheck) * 100;
            $accValCol = (int)$accValCol;
        }
        $record['Account Validation'] = $accValCol . ' %';

        /*         * ** Verification % * */
        $acceptedWlcmCall = SignupWlcmCall::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->where(['Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_verified])
            ->count();

        $totalWlcmCall = SignupWlcmCall::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->where('Call_FinalStatus', '!=', null)
            ->count();
        $wlcmCol = '0';
        if ($totalWlcmCall != 0) {
            $wlcmCol = ($acceptedWlcmCall / $totalWlcmCall) * 100;
            $wlcmCol = (int)$wlcmCol;
        }
        $record['Verification %'] = $wlcmCol . ' %';
        /*         * ** Account rejection* */
        $rejectedWlcmCall = SignupWlcmCall::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->where(['Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_rejected])
            ->count();

        $wlcmCol = '0 ';
        if ($totalWlcmCall != 0) {
            $wlcmCol = ($rejectedWlcmCall / $totalWlcmCall) * 100;
            $wlcmCol = (int)$wlcmCol;
        }
        $record['Rejection %'] = $wlcmCol . ' %';
        /*         * ** FFP %* */
        $formQualityAcceptRecord = SignupFormChk::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->where(['FFPStatus' => SignupFormChk::FFPStatus_Accept])
            ->count();
        $formQualityRecord = SignupFormChk::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->where('FFPStatus', '!=', null)
            ->count();
        $ffpCol = '0';
        if ($formQualityRecord != 0) {
            $ffpCol = ($formQualityAcceptRecord / $formQualityRecord) * 100;
            $ffpCol = (int)$ffpCol;
        }
        $record['FFP%'] = $ffpCol . ' %';
        /*         * ** Form Receipt* */
        $minSignupDateRecord = SignupDataEntry::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->where('FormReceiveDate', '<>', null)
//                ->where('FormReceiveDate', '<>', '')
            ->count();
        $totalSignupDateRecord = SignupDataEntry::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->count();
        $formRecCol = '0';
        if ($totalSignupDateRecord != 0) {
            $formRecCol = ($minSignupDateRecord / $totalSignupDateRecord) * 100;
            $formRecCol = (int)$formRecCol;
        }
        $record['Form Receipt'] = $formRecCol . ' %';
        /*         * ** Submission* */
        $signupDateEntryExportDate = SignupDataEntry::whereHas('getSignup', function ($q) use ($charityCode, $val, $modeOfDonation, $dateFrom, $dateTo) {
            if ($charityCode) {
                $q = $q->where(['CharityCode' => $charityCode]);
            }
            if ($dateFrom && $dateTo) {
                $q = $q->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            return $q->whereHas('getEmployee', function ($em) use ($val) {
                $em->where(['ECity' => $val->id]);
            })
                ->where(['ModeOfDonation' => $modeOfDonation]);
        })
            ->where('exportDate', '<>', null)
//                ->where('exportDate', '<>', '')
            ->count();

        $submCol = '0';
        if ($totalSignupDateRecord != 0) {
            $submCol = ($signupDateEntryExportDate / $totalSignupDateRecord) * 100;
            $submCol = (int)$submCol;
        }
        $record['Submission'] = $submCol . ' %';
        /*         * ** Enrol %* */
        $succesEnrol = BankingEnrolStatus::where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusSuccess])
            ->whereHas('getEmployee', function ($q) use ($val) {
                $q->where(['ECity' => $val->id]);
            })
            ->where(['ModeOfDonation' => $modeOfDonation])
            ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
            ->count();
        $failtEnrol = BankingEnrolStatus::where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusFail])
            ->whereHas('getEmployee', function ($q) use ($val) {
                $q->where(['ECity' => $val->id]);
            })
            ->where(['ModeOfDonation' => $modeOfDonation])
            ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
            ->count();
        $enrolDivided = $succesEnrol + $failtEnrol;
        $enrolPerCol = '0';
        if ($enrolDivided != 0) {
            $enrolPerCol = ($succesEnrol / $enrolDivided) * 100;
            $enrolPerCol = (int)$enrolPerCol;
        }
        $record['Enrol %'] = $enrolPerCol . ' %';
        /*         * ** Debit %* */
        $succesDebit = BankingDebitStatus::where(['DebitStatus' => BankingDebitStatus::DebitStatusSuccess])
            ->whereHas('getEmployee', function ($q) use ($val) {
                $q->where(['ECity' => $val->id]);
            })
            ->where(['ModeOfDonation' => $modeOfDonation])
            ->where(['Debit_Attempt' => 1])
            ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
            ->count();
        $failDebit = BankingDebitStatus::where(['DebitStatus' => BankingDebitStatus::DebitStatusFail])
            ->whereHas('getEmployee', function ($q) use ($val) {
                $q->where(['ECity' => $val->id]);
            })
            ->where(['ModeOfDonation' => $modeOfDonation])
            ->where(['Debit_Attempt' => 1])
            ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
            ->count();
        $debitDivided = $succesDebit + $failDebit;
        $debitPerCol = '0';
        if ($debitDivided != 0) {
            $debitPerCol = ($succesDebit / $debitDivided) * 100;
            $debitPerCol = (int)$debitPerCol;
        }
        $record['Debit %'] = $debitPerCol . ' %';
        /*         * ** Canx %* */
//        $cnxBankingEnrolCancel = BankingEnrolStatus::where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusSuccess])
//            ->whereHas('getEmployee', function ($q) use ($val) {
//                $q->where(['ECity' => $val->id]);
//            })
//            ->where(['PledgeStatus' => BankingEnrolStatus::PledgeStatusCancel])
//            ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
//            ->count();
//        $cnxBankingEnrolTotal = BankingEnrolStatus::where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusSuccess])
//            ->whereHas('getEmployee', function ($q) use ($val) {
//                $q->where(['ECity' => $val->id]);
//            })
//            ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
//            ->count();

        $cnxBankingCancel = BankingEnrolStatus::where(['PledgeStatus' => BankingEnrolStatus::PledgeStatusCancel])
            ->whereHas('getEmployee', function ($q) use ($val) {
                $q->where(['ECity' => $val->id]);
            })
            ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
            ->count();
        $cnxBankingActive = BankingEnrolStatus::where(['PledgeStatus' => BankingEnrolStatus::PledgeStatusActive])
            ->whereHas('getEmployee', function ($q) use ($val) {
                $q->where(['ECity' => $val->id]);
            })
            ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
            ->count();
        $cnxBankingNull = BankingEnrolStatus::where(['PledgeStatus' => null])
            ->whereHas('getEmployee', function ($q) use ($val) {
                $q->where(['ECity' => $val->id]);
            })
            ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
            ->count();

        $cnxBankingEnrolTotal = $cnxBankingActive + $cnxBankingCancel + $cnxBankingNull;

        $cnxCol = '0';
        if ($cnxBankingEnrolTotal != 0) {
            $cnxCol = ($cnxBankingCancel / $cnxBankingEnrolTotal) * 100;
            $cnxCol = (int)$cnxCol;
        }
        $record['Canx'] = $cnxCol . ' %';
        return $record;
    }

    public function welcomeCallReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.welcomeCallReport', compact('getAllCity', 'getCharityCode'));
    }

    public function welcomeCallReport() {
        $input = request()->all();


        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();
        /**
         * Start
         */
        $date1 = date_create($dateFrom);
        $date2 = date_create($dateTo);
        if ($date1 > $date2) {
            return $this->sendResponse(false, '', 'Invalid Date Difference.');
        }

        $getAllCity = EmployeeECity::get();
        if ($city) {
            $getAllCity = EmployeeECity::where(['id' => $city])->get();
        }
        $dataArray = [];
        $totalVerifiedFooter = 0;
        $totalUnverifiedFooter = 0;
        $totalRejectedFooter = 0;
        $grandTotalFooter = 0;
        $totalVerificationPerFooter = 0;
        $totalUnverifiedPerFooter = 0;
        $totalRejectionPerFooter = 0;
        if ($city) {
            $getAllEmployeeByCity = EmployeeMaster::where(['ECity' => $city])->get();

            foreach ($getAllEmployeeByCity as $val) {
                $welcomeCallData = SignupWlcmCall::leftJoin('Signup', 'Signup_WlcmCall.CRM_ID', 'Signup.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->where(['Employee_Master.ECity' => $city])
                    ->where(['Employee_Master.EID' => $val->EID]);
                if ($dateFrom && $dateTo) {
                    $welcomeCallData = $welcomeCallData->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
                }
                if ($charityCode) {
                    $welcomeCallData = $welcomeCallData->where('Employee_Master.CharityCode', $charityCode);
                }
                if (!$welcomeCallData->count()) {
                    continue;
                }
                /**
                 * Verify
                 */
                $totalVerified = SignupWlcmCall::leftJoin('Signup', 'Signup_WlcmCall.CRM_ID', 'Signup.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->where(['Employee_Master.ECity' => $city])
                    ->where(['Employee_Master.EID' => $val->EID]);
                if ($dateFrom && $dateTo) {
                    $totalVerified = $totalVerified->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
                }
                if ($charityCode) {
                    $totalVerified = $totalVerified->where('Employee_Master.CharityCode', $charityCode);
                }
                $totalVerified = $totalVerified->where(['Signup_WlcmCall.Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_verified])->count();
                /**
                 * Unverify
                 */
                $totalUnverified = SignupWlcmCall::leftJoin('Signup', 'Signup_WlcmCall.CRM_ID', 'Signup.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->where(['Employee_Master.ECity' => $city])
                    ->where(['Employee_Master.EID' => $val->EID]);
                if ($dateFrom && $dateTo) {
                    $totalUnverified = $totalUnverified->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
                }
                if ($charityCode) {
                    $totalUnverified = $totalUnverified->where('Employee_Master.CharityCode', $charityCode);
                }
                $totalUnverified = $totalUnverified->whereIn('Signup_WlcmCall.Call_FinalStatus', [SignupWlcmCall::Call_FinalStatus_not_verified, SignupWlcmCall::Call_FinalStatus_process_unverified])->count();

                /**
                 * Reject
                 */
                $totalRejected = SignupWlcmCall::leftJoin('Signup', 'Signup_WlcmCall.CRM_ID', 'Signup.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->where(['Employee_Master.ECity' => $city])
                    ->where(['Employee_Master.EID' => $val->EID]);
                if ($dateFrom && $dateTo) {
                    $totalRejected = $welcomeCallData->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
                }
                if ($charityCode) {
                    $totalRejected = $totalRejected->where('Employee_Master.CharityCode', $charityCode);
                }
                $totalRejected = $totalRejected->where(['Signup_WlcmCall.Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_rejected])->count();


                $grandTotal = $totalVerified + $totalUnverified + $totalRejected;
                $totalVerificationPer = ($grandTotal <= 0) ? 0 : ($totalVerified / $grandTotal) * 100;
                $totalUnverifiedPer = ($grandTotal <= 0) ? 0 : ($totalUnverified / $grandTotal) * 100;
                $totalRejectionPer = ($grandTotal <= 0) ? 0 : ($totalRejected / $grandTotal) * 100;

                //Add group name and info
                $getCityGroup = ChatCityGroup::select('GrpName')
                    ->where(['EID' => $val->EID])
                    ->groupBy('GrpId')->get()->toArray();
                $getTeamGroup = ChatTeamGroup::select('GrpName')
                    ->where(['EID' => $val->EID])
                    ->groupBy('GrpId')->get()->toArray();
                $cityGroupNames = implode(', ', array_column($getCityGroup, 'GrpName'));
                $teamGroupNames = implode(', ', array_column($getTeamGroup, 'GrpName'));

                $record = [];
                $record['EName'] = $val->EName ?? '';
                $record['EDesg'] = $val->getDesg->EDesg ?? '';
                $record['City Group'] = $cityGroupNames ?? '';
                $record['Team Group'] = $teamGroupNames ?? '';

                $record['Location'] = $val->getCity->Ecity ?? '';
                $record['Verified'] = $totalVerified;
                $record['UnVerified'] = $totalUnverified;
                $record['Reject'] = $totalRejected;
                $record['Grand Total'] = $grandTotal;
                $record['Verification %'] = number_format((float)$totalVerificationPer, 2, '.', '') . '%';
                $record['Unverified %'] = number_format((float)$totalUnverifiedPer, 2, '.', '') . '%';
                $record['Rejection %'] = number_format((float)$totalRejectionPer, 2, '.', '') . '%';
                $dataArray[] = $record;
                /**
                 * Footer Total Count
                 */
                $totalVerifiedFooter += $totalVerified;
                $totalUnverifiedFooter += $totalUnverified;
                $totalRejectedFooter += $totalRejected;
                $grandTotalFooter += $grandTotal;
                $totalVerificationPerFooter += $totalVerificationPer;
                $totalUnverifiedPerFooter += $totalUnverifiedPer;
                $totalRejectionPerFooter += $totalRejectionPer;
            }
        } else {
            foreach ($getAllCity as $val) {
                $welcomeCallData = SignupWlcmCall::leftJoin('Signup', 'Signup_WlcmCall.CRM_ID', 'Signup.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->where(['Employee_Master.ECity' => $val->id]);
                if ($dateFrom && $dateTo) {
                    $welcomeCallData = $welcomeCallData->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
                }
                if ($charityCode) {
                    $welcomeCallData = $welcomeCallData->where('Employee_Master.CharityCode', $charityCode);
                }
                if (!$welcomeCallData->count()) {
                    continue;
                }
                /**
                 * Verify
                 */
                $totalVerified = SignupWlcmCall::leftJoin('Signup', 'Signup_WlcmCall.CRM_ID', 'Signup.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->where(['Employee_Master.ECity' => $val->id]);
                if ($dateFrom && $dateTo) {
                    $totalVerified = $totalVerified->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
                }
                if ($charityCode) {
                    $totalVerified = $totalVerified->where('Employee_Master.CharityCode', $charityCode);
                }
                $totalVerified = $totalVerified->where(['Signup_WlcmCall.Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_verified])->count();
                /**
                 * Unverify
                 */
                $totalUnverified = SignupWlcmCall::leftJoin('Signup', 'Signup_WlcmCall.CRM_ID', 'Signup.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->where(['Employee_Master.ECity' => $val->id]);
                if ($dateFrom && $dateTo) {
                    $totalUnverified = $totalUnverified->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
                }
                if ($charityCode) {
                    $totalUnverified = $totalUnverified->where('Employee_Master.CharityCode', $charityCode);
                }
                $totalUnverified = $totalUnverified->whereIn('Signup_WlcmCall.Call_FinalStatus', [SignupWlcmCall::Call_FinalStatus_not_verified, SignupWlcmCall::Call_FinalStatus_process_unverified])->count();

                /**
                 * Reject
                 */
                $totalRejected = SignupWlcmCall::leftJoin('Signup', 'Signup_WlcmCall.CRM_ID', 'Signup.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->where(['Employee_Master.ECity' => $val->id]);
                if ($dateFrom && $dateTo) {
                    $totalRejected = $welcomeCallData->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
                }
                if ($charityCode) {
                    $totalRejected = $totalRejected->where('Employee_Master.CharityCode', $charityCode);
                }
                $totalRejected = $totalRejected->where(['Signup_WlcmCall.Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_rejected])->count();


                $grandTotal = $totalVerified + $totalUnverified + $totalRejected;
                $totalVerificationPer = ($grandTotal <= 0) ? 0 : ($totalVerified / $grandTotal) * 100;
                $totalUnverifiedPer = ($grandTotal <= 0) ? 0 : ($totalUnverified / $grandTotal) * 100;
                $totalRejectionPer = ($grandTotal <= 0) ? 0 : ($totalRejected / $grandTotal) * 100;

                $record = [];
                $record['Location'] = $val->Ecity ?? '';
                $record['Verified'] = $totalVerified;
                $record['UnVerified'] = $totalUnverified;
                $record['Reject'] = $totalRejected;
                $record['Grand Total'] = $grandTotal;
                $record['Verification %'] = number_format((float)$totalVerificationPer, 2, '.', '') . '%';
                $record['Unverified %'] = number_format((float)$totalUnverifiedPer, 2, '.', '') . '%';
                $record['Rejection %'] = number_format((float)$totalRejectionPer, 2, '.', '') . '%';
                $dataArray[] = $record;
                /**
                 * Footer Total Count
                 */
                $totalVerifiedFooter += $totalVerified;
                $totalUnverifiedFooter += $totalUnverified;
                $totalRejectedFooter += $totalRejected;
                $grandTotalFooter += $grandTotal;
                $totalVerificationPerFooter += $totalVerificationPer;
                $totalUnverifiedPerFooter += $totalUnverifiedPer;
                $totalRejectionPerFooter += $totalRejectionPer;
            }
        }

        if (count($dataArray)) {
            $countVerificationPerTotal = ($totalVerificationPerFooter / count($dataArray)) ?? 0;
            $countVerificationPer = number_format((float)$countVerificationPerTotal, 2, '.', '');
            $countUnverifiedPerTotal = ($totalUnverifiedPerFooter / count($dataArray)) ?? 0;
            $countUnverifiedPer = number_format((float)$countUnverifiedPerTotal, 2, '.', '');
            $countRejectionPerTotal = ($totalRejectionPerFooter / count($dataArray)) ?? 0;
            $countRejectionPer = number_format((float)$countRejectionPerTotal, 2, '.', '');

            if ($city) {
                $footer['EName'] = '-';
                $footer['EDesg'] = '-';
                $footer['City Group'] = '-';
                $footer['Team Group'] = '-';
            }

            $footer['Location'] = ['color' => 'D993c47d', 'val' => 'Grand Total']; //don't change this value because it is used in custom color function
            $footer['Verified'] = ['color' => 'D993c47d', 'val' => $totalVerifiedFooter];
            $footer['UnVerified'] = ['color' => 'D993c47d', 'val' => $totalUnverifiedFooter];
            $footer['Reject'] = ['color' => 'D993c47d', 'val' => $totalRejectedFooter];
            $footer['Grand Total'] = ['color' => 'D993c47d', 'val' => $grandTotalFooter];
            $footer['Verification %'] = ['color' => 'D9FFFF00', 'val' => $countVerificationPer . '%'];
            $footer['Unverified %'] = ['color' => 'D9FFFF00', 'val' => $countUnverifiedPer . '%'];
            $footer['Rejection %'] = ['color' => 'D9FFFF00', 'val' => $countRejectionPer . '%'];
            $dataArray[] = $footer;

            $getFileUrl = $this->writeXlsxFileWelcomeCall($dataArray, 'welcomeCall');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function oneOffReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.oneOffReport', compact('getAllCity', 'getCharityCode'));
    }

    public function oneOffReport() {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();

        $date1 = date_create($dateFrom);
        $date2 = date_create($dateTo);
        if ($date1 > $date2) {
            return $this->sendResponse(false, '', 'Invalid Date Difference.');
        }
        /**
         *
         * Filters
         *
         */
        $dataArray = [];
        $getEmployeeList = EmployeeMaster::whereIn('EDesg', [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_STL])
            ->whereHas('getUserInfo')
            ->where('ECity', '!=', '')
            ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
            ->where('ECity', '!=', null);
        if ($city) {
            $getEmployeeList = $getEmployeeList->where(['ECity' => $city]);
        }
        if ($charityCode) {
            $getEmployeeList = $getEmployeeList->where(['CharityCode' => $charityCode]);
        }
        $getEmployeeList = $getEmployeeList->get();


        foreach ($getEmployeeList as $key => $val) {
            $getSignupRecordCount = Signup::where(['EID' => $val->EID]);
            if ($dateFrom && $dateTo) {
                $getSignupRecordCount = $getSignupRecordCount->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            $getSignupRecordCount = $getSignupRecordCount->count();

            $getOtSignupCount = SignupAccountChk::leftJoin('Signup', 'Signup_AccountChk.CRM_ID', 'Signup.CRM_ID')
                ->where(['Signup.EID' => $val->EID]);
            if ($dateFrom && $dateTo) {
                $getOtSignupCount = $getOtSignupCount->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
            }
            $getOtSignupCount = $getOtSignupCount->where(['Frequency' => SignupAccountChk::Frequency_OneTime])
                ->where(['BOStatUpdate' => SignupAccountChk::STATUS_ACCEPTED])
                ->get();

            $totalOTAmount = 0;
            foreach ($getOtSignupCount as $otVal) {
                $totalOTAmount += $otVal->Amount;
            }
            $getOtSignupCount = $getOtSignupCount->count();

            //Add group name and info
            $getCityGroup = ChatCityGroup::select('GrpName')
                ->where(['EID' => $val->EID])
                ->groupBy('GrpId')->get()->toArray();
            $getTeamGroup = ChatTeamGroup::select('GrpName')
                ->where(['EID' => $val->EID])
                ->groupBy('GrpId')->get()->toArray();
            $cityGroupNames = implode(', ', array_column($getCityGroup, 'GrpName'));
            $teamGroupNames = implode(', ', array_column($getTeamGroup, 'GrpName'));


            $record['EID'] = $val->EID;
            $record['FRName'] = $val->EName;
            $record['City'] = $val->getCity->Ecity ?? '';
            $record['Designation'] = $val->getDesg->EDesg ?? '';
            $record['City Group'] = $cityGroupNames ?? '';
            $record['Team Group'] = $teamGroupNames ?? '';
            $record['Total Signup'] = $getSignupRecordCount;
            $record['OT Signup'] = $getOtSignupCount;

            $otPer = 0;
            if ($getSignupRecordCount > 0) {
                $otPer = ($getOtSignupCount / $getSignupRecordCount) * 100;
            }
            $ads = 0;
            if ($getOtSignupCount > 0) {
                $ads = ($totalOTAmount / $getOtSignupCount);
            }

            $record['OT %'] = number_format((float)$otPer, '2', '.', '') . ' %';
            $record['Total OT Amount'] = number_format((float)$totalOTAmount, '2', '.', '');
            $record['ADS'] = number_format((float)$ads, '2', '.', '');

            $dataArray[] = $record;
        }

        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'OneOffReport');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function probationalReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.probationalReport', compact('getAllCity', 'getCharityCode'));
    }

    public function probationalReport() {
        $input = request()->all();

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();
        /**
         * Start
         */
        $dataArray = [];
        $getEmployeeList = EmployeeMaster::whereHas('getUserInfo')
            ->where(['EDesg' => EmployeeEDesg::DESG_FR])
            ->where('ECity', '!=', '')
            ->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
            ->where('ECity', '!=', null)
            ->whereIn('EStatus', [EmployeeMaster::EStatusProbation, EmployeeMaster::EStatusExtendedProbation]);
        if ($city) {
            $getEmployeeList = $getEmployeeList->where(['ECity' => $city]);
        }
        if ($charityCode) {
            $getEmployeeList = $getEmployeeList->where(['CharityCode' => $charityCode]);
        }
        $getEmployeeList = $getEmployeeList->get();

        $employeeProbation = EmployeeMaster::getProbation();


        foreach ($getEmployeeList as $val) {
            $record['EID'] = $val->EID;
            $record['Fundraiser'] = $val->EName;
            $record['Employee Designation'] = $val->getDesg->EDesg;
            $record['Employee City'] = $val->getCity->Ecity;
            $record['DOJ'] = $val->EDOJ;
            $record['Estatus'] = $employeeProbation[$val->EStatus] ?? '';

            $getDoj = $val->EDOJ;
            $date = new \DateTime($getDoj);
            $total30dayFrom = $getDoj;
            $total30dayTO = $date->modify('+30 days')->format('Y-m-d');
            $total60dayFrom = $date->modify('+1 days')->format('Y-m-d');
            $total60dayTO = $date->modify('+30 days')->format('Y-m-d');
            $total90dayFrom = $date->modify('+1 days')->format('Y-m-d');
            $total90dayTO = $date->modify('+30 days')->format('Y-m-d');

            $getTotal30DaySignup = Signup::where(['EID' => $val->EID])
                ->whereBetween('PDate', [$total30dayFrom, $total30dayTO])
                ->count();
            $getTotal60DaySignup = Signup::where(['EID' => $val->EID])
                ->whereBetween('PDate', [$total60dayFrom, $total60dayTO])
                ->count();
            $getTotal90DaySignup = Signup::where(['EID' => $val->EID])
                ->whereBetween('PDate', [$total90dayFrom, $total90dayTO])
                ->count();

            $record['DOJ + 30 Days'] = $getTotal30DaySignup;
            $record['DOJ 30 - 60 Days'] = $getTotal60DaySignup;
            $record['DOJ 60 - 90 Days'] = $getTotal90DaySignup;

            $getEmployeeQualityScore = EmployeeTargetQaulity::where(['EID' => $val->EID])
                ->whereMonth('date', date('m'))
                ->whereYear('date', date('Y'))
                ->first();

            $record['Quality Score'] = $getEmployeeQualityScore->quality ?? '';
            $dataArray[] = $record;
        }

        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'probational');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function processHealthReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.processHealthReport', compact('getAllCity', 'getCharityCode'));
    }

    public function processHealthReport() {
        $input = request()->all();

        $city = $input['city'] ?? null;
        $charityCode = '';
        if (isset($input['CharityCode']) && $input['CharityCode']) {
            $checkCharityCode = CharityCode::find($input['CharityCode']);
            $charityCode = $checkCharityCode ? $checkCharityCode->id : '';
        }
        $locType = '';
        if (isset($input['LocType']) && $input['LocType']) {
            $locTypes = [Signup::LOCTYPE_PERMISSION, Signup::LOCTYPE_STREET];
            $locType = (in_array($input['LocType'], $locTypes)) ? $input['LocType'] : '';
        }
        /**
         * Start
         */
        $yearMonthFrom = $input['YearMonthFrom'] ?? null;
        $yearMonthTo = $input['YearMonthTo'] ?? null;
        if (!$yearMonthFrom || !$yearMonthTo) {
            return $this->sendResponse(false, '', 'Please Select Month Range.');
        }
        if (!$city) {
            return $this->sendResponse(false, '', 'Please Select City.');
        }

        $yearFrom = date('Y');
        $monthFrom = date('m');
        $yearTo = date('Y');
        $monthTo = date('m');
        if (isset($input['YearMonthFrom']) && $input['YearMonthFrom']) {
            $yearFrom = Common::fixDateFormat($input['YearMonthFrom'], 'm-Y', 'Y');
            $monthFrom = Common::fixDateFormat($input['YearMonthFrom'], 'm-Y', 'm');
        }
        if (isset($input['YearMonthTo']) && $input['YearMonthTo']) {
            $yearTo = Common::fixDateFormat($input['YearMonthTo'], 'm-Y', 'Y');
            $monthTo = Common::fixDateFormat($input['YearMonthTo'], 'm-Y', 'm');
        }
        $checkDateFrom = $yearFrom . '-' . $monthFrom . '-' . date('d');
        $checkDateTo = $yearTo . '-' . $monthTo . '-' . date('d');
        $date1 = date_create($checkDateFrom);
        $date2 = date_create($checkDateTo);
        if ($date1 > $date2) {
            return $this->sendResponse(false, '', 'Invalid Date Difference.');
        }
        $monthRange = [];
        while ($checkDateFrom <= $checkDateTo) {
            $year = $date1->format('Y');
            $month = $date1->format('m');
            $monthRange[] = [
                'year' => $year,
                'month' => $month,
            ];
            $date1->add(new \DateInterval("P1M"));
            $checkDateFrom = $date1->format('Y-m-d');
            $date1 = date_create($checkDateFrom);
//            if($date1 <= $date2){
//                break;
//            }
        }

        /*         * ********Start Calculating data************* */

        $dataArray = [];
        $getAllCity = EmployeeECity::whereIn('id', $city)->get();
        $row = 1;
        foreach ($getAllCity as $getCity) {
            $cityName = $getCity ? $getCity->Ecity : '';
            $city = $getCity->id;
            $row++; //because this value will apply to row 2
            $report = [];
            $report['Location'] = null;
            $report['Nach Signup'] = null;
            $report['Nach Submission'] = null;
            $report['Nach Enrol Success'] = null;
            $report['Nach Enrol Fail'] = null;
            $report['Success %'] = null;
            $report['null-1'] = null;
            $report['Nach 1st Debit'] = null;
            $report['Debit %'] = null;
            $report['null-2'] = null;
            $report['Throughput'] = null;
            $report['null-3'] = null;
            $report['Online Signup'] = null;
            $report['Online First Debit'] = null;
            $report['null-4'] = null;
            $report['Enach Signup'] = null;
            $report['Enach 1st Debit'] = null;
            $report['null-5'] = null;
            $report['Total Signup'] = null;
            $report['Billed'] = null;
            $report['Billed %'] = null;
            $report['null-6'] = null;
            $report['Cancelled'] = null;
            $dataArray[] = $report;
            $row++;
            $report['Location'] = ['mergeCell' => 'A' . $row . ':F' . $row, 'color' => '#538DD4', 'val' => $cityName];
            $dataArray[] = $report;
            $row++;
            $report['Location'] = ['color' => '#4F81BD', 'val' => 'Location'];
            $report['Nach Signup'] = ['color' => '#4F81BD', 'val' => 'Nach Signup'];
            $report['Nach Submission'] = ['color' => '#4F81BD', 'val' => 'Nach Submission'];
            $report['Nach Enrol Success'] = ['color' => '#4F81BD', 'val' => 'Nach Enrol Success'];
            $report['Nach Enrol Fail'] = ['color' => '#4F81BD', 'val' => 'Nach Enrol Fail'];
            $report['Success %'] = ['color' => '#4F81BD', 'val' => 'Success %'];
            $report['Nach 1st Debit'] = ['color' => '#4F81BD', 'val' => 'Nach 1st Debit'];
            $report['Debit %'] = ['color' => '#4F81BD', 'val' => 'Debit %'];
            $report['Throughput'] = ['color' => '#4F81BD', 'val' => 'Throughput'];
            $report['Online Signup'] = ['color' => '#4F81BD', 'val' => 'Online Signup'];
            $report['Online First Debit'] = ['color' => '#4F81BD', 'val' => 'Online First Debit'];
            $report['Enach Signup'] = ['color' => '#4F81BD', 'val' => 'Enach Signup'];
            $report['Enach 1st Debit'] = ['color' => '#4F81BD', 'val' => 'Enach 1st Debit'];
            $report['Total Signup'] = ['color' => '#4F81BD', 'val' => 'Total Signup'];
            $report['Billed'] = ['color' => '#4F81BD', 'val' => 'Billed'];
            $report['Billed %'] = ['color' => '#4F81BD', 'val' => 'Billed %'];
            $report['Cancelled'] = ['color' => '#4F81BD', 'val' => 'Cancelled'];
            $dataArray[] = $report;
            foreach ($monthRange as $yearMonth) {
                $row++;
                $report = [];
                $year = $yearMonth['year'];
                $month = $yearMonth['month'];
                $report['Location'] = $yearMonth['month'] . '/' . $yearMonth['year'];

                $nachSignup = Signup::where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                    ->whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->count();

                $nachSubmission = Signup::where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                    ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                    ->whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where('exportDate', '!=', null)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->count();

                $nachEnrolSuccess = Signup::where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                    ->whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getBankingEnrolStatus', function ($q) {
                        return $q->where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusSuccess]);
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->count();

                $nachEnrolFail = Signup::where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                    ->whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->whereHas('getBankingEnrolStatus', function ($q) {
                        return $q->where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusFail]);
                    })
                    ->count();

                $successPer = '0.00 %';
                $successDividedBy = $nachEnrolSuccess + $nachEnrolFail;
                if ($successDividedBy != 0) {
                    $successPer = ($nachEnrolSuccess / $successDividedBy) * 100;
                    $successPer = number_format((float)$successPer, '2', '.', '') . ' %';
                }

                $nachFirstDebit = Signup::where(['ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                    ->whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->whereHas('getBankingDebitStatus', function ($q) {
                        return $q->where(['Debit_Attempt' => 1])
                            ->where(['DebitStatus' => BankingDebitStatus::DebitStatusSuccess]);
                    })
                    ->count();

                $debitPer = '0.00 %';
                if ($nachEnrolSuccess != 0) {
                    $debitPer = ($nachFirstDebit / $nachEnrolSuccess) * 100;
                    $debitPer = number_format((float)$debitPer, '2', '.', '') . ' %';
                }
                $throughPut = '0.00 %';
                $throughPutDevidedBy = $nachEnrolSuccess + $nachEnrolFail;
                if ($throughPutDevidedBy != 0) {
                    $throughPut = ($nachFirstDebit / $throughPutDevidedBy) * 100;
                    $throughPut = number_format((float)$throughPut, '2', '.', '') . ' %';
                }

                $onlineSignup = Signup::where(['ModeOfDonation' => Signup::MODEOFDONATION_ONLINE])
                    ->whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->count();

                $onlineFirstDebit = Signup::where(['ModeOfDonation' => Signup::MODEOFDONATION_ONLINE])
                    ->whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->whereHas('getBankingEnrolStatus', function ($q) {
                        return $q->where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusSuccess]);
                    })
                    ->count();

                $enachSignup = Signup::where(['ModeOfDonation' => Signup::MODEOFDONATION_ENACH])
                    ->whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->count();

                $enachFirstDebit = Signup::where(['ModeOfDonation' => Signup::MODEOFDONATION_ENACH])
                    ->whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->whereHas('getBankingDebitStatus', function ($q) {
                        return $q->where(['Debit_Attempt' => 1])
                            ->where(['DebitStatus' => BankingDebitStatus::DebitStatusSuccess]);
                    })
                    ->count();

                $totalSignup = $nachSubmission + $onlineSignup + $enachSignup;

                $billed = $nachFirstDebit + $onlineFirstDebit + $enachFirstDebit;

                $billedPer = '0.00 %';
                if ($totalSignup != 0) {
                    $billedPer = ($billed / $totalSignup) * 100;
                    $billedPer = number_format((float)$billedPer, '2', '.', '') . ' %';
                }

                $cancelled = Signup::whereYear('PDate', $year)
                    ->whereMonth('PDate', $month)
                    ->where(function ($q) use ($charityCode, $locType) {
                        if ($charityCode) {
                            $q->where(['CharityCode' => $charityCode]);
                        }
                        if ($locType) {
                            $q->where(['LocType' => $charityCode]);
                        }
                        return $q;
                    })
                    ->whereHas('getEmployee', function ($q) use ($city) {
                        if ($city) {
                            $q = $q->where(['ECity' => $city]);
                        }
                        return $q;
                    })
                    ->whereHas('getBankingEnrolStatus', function ($q) {
                        return $q->where(['PledgeStatus' => BankingEnrolStatus::PledgeStatusCancel]);
                    })
                    ->count();


                $report['Nach Signup'] = $nachSignup;
                $report['Nach Submission'] = $nachSubmission;
                $report['Nach Enrol Success'] = $nachEnrolSuccess;
                $report['Nach Enrol Fail'] = $nachEnrolFail;
                $report['Success %'] = $successPer;
                $report['null-1'] = null;
                $report['Nach 1st Debit'] = $nachFirstDebit;
                $report['Debit %'] = $debitPer;
                $report['null-2'] = null;
                $report['Throughput'] = $throughPut;
                $report['null-3'] = null;
                $report['Online Signup'] = $onlineSignup;
                $report['Online First Debit'] = $onlineFirstDebit;
                $report['null-4'] = null;
                $report['Enach Signup'] = $enachSignup;
                $report['Enach 1st Debit'] = $enachFirstDebit;
                $report['null-5'] = null;
                $report['Total Signup'] = $totalSignup ?? 0;
                $report['Billed'] = $billed ?? 0;
                $report['Billed %'] = $billedPer ?? '0.00 %';
                $report['null-6'] = null;
                $report['Cancelled'] = $cancelled;
                $dataArray[] = $report;
            }
        }
        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFileProcessHealth($dataArray, 'processHealthReport');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function locationProductivityReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.locationProductivityReport', compact('getAllCity', 'getCharityCode'));
    }

    public function locationProductivityReport() {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();

        $getAllSignUp = ProspectMaster::select('*');

        if ($charityCode) {
            $getAllSignUp = $getAllSignUp->where(['CharityCode' => $charityCode]);
        }

        if ($locType) {
            $getAllSignUp = $getAllSignUp->where(['LocType' => $locType]);
        }
        if ($dateFrom && $dateTo) {
            $getAllSignUp = $getAllSignUp->whereBetween('PDate', [$dateFrom, $dateTo]);
        }

        $getAllSignUp = $getAllSignUp->get();

        $dataArray = [];
        foreach ($getAllSignUp as $key => $val) {
            $record = [];
            $record['CRM_ID'] = $val->CRM_ID;
            $record['Pin_Code'] = $val->pinCode;
            $record['Address'] = $val->GeoLocationAcc;
            $record['Prospect'] = $val->getSignup ? '' : '1';
            $record['NACH Signup'] = ($val->getSignup && $val->getSignup->ModeOfDonation == Signup::MODEOFDONATION_NACH) ? '1' : '';
            $record['ENACH Signup'] = ($val->getSignup && $val->getSignup->ModeOfDonation == Signup::MODEOFDONATION_ENACH) ? '1' : '';
            $record['Online Signup'] = ($val->getSignup && $val->getSignup->ModeOfDonation == Signup::MODEOFDONATION_ONLINE) ? '1' : '';
            $dataArray[] = $record;
        }

        /**
         * Start
         */
        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'locationProductivity');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function locationPerformanceReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.locationPerformanceReport', compact('getAllCity', 'getCharityCode'));
    }

    public function locationPerformanceReport() {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();

        $getAllSignUp = ProspectMaster::where('pinCode', '!=', '')
            ->where('pinCode', '!=', NULL);

        if ($charityCode) {
            $getAllSignUp = $getAllSignUp->where(['CharityCode' => $charityCode]);
        }

        if ($locType) {
            $getAllSignUp = $getAllSignUp->where(['LocType' => $locType]);
        }
        if ($dateFrom && $dateTo) {
            $getAllSignUp = $getAllSignUp->whereBetween('PDate', [$dateFrom, $dateTo]);
        }

        $getAllSignUp = $getAllSignUp->get();

        $recordData = [];
        foreach ($getAllSignUp as $key => $val) {
            $prospectCount = 1;
            $onlineSignup = ($val->getSignup && $val->getSignup->ModeOfDonation == Signup::MODEOFDONATION_ONLINE) ? 1 : 0;
            $nachSignup = ($val->getSignup && $val->getSignup->ModeOfDonation == Signup::MODEOFDONATION_NACH) ? 1 : 0;
            $enachSignup = ($val->getSignup && $val->getSignup->ModeOfDonation == Signup::MODEOFDONATION_ENACH) ? 1 : 0;
            $formProcessed = ($val->getSignup && $val->getSignup->getSignupDataEntry && ($val->getSignup->getSignupDataEntry->exportDate != null)) ? 1 : 0;
            $crmId = $val->CRM_ID;

            if (isset($recordData[$val->pinCode])) {
                $recordData[$val->pinCode]['Prospect'] += $prospectCount;
                $recordData[$val->pinCode]['Online_Signup'] += $onlineSignup;
                $recordData[$val->pinCode]['NACH_Signup'] += $nachSignup;
                $recordData[$val->pinCode]['ENACH_Signup'] += $enachSignup;
                $recordData[$val->pinCode]['Form_Processed'] += $formProcessed;
                $recordData[$val->pinCode]['allCrmId'][] = $crmId;
            } else {
                $recordData[$val->pinCode] = [
                    'Prospect' => $prospectCount,
                    'Online_Signup' => $onlineSignup,
                    'NACH_Signup' => $nachSignup,
                    'ENACH_Signup' => $enachSignup,
                    'Form_Processed' => $formProcessed,
                    'allCrmId' => [$crmId]
                ];
            }
        }

        $dataArray = [];
        foreach ($recordData as $key => $val) {
            $record = [];
            $record['Pin_Code'] = $key;
            $record['Prospect'] = $val['Prospect'];
            $record['Online Signup'] = $val['Online_Signup'];
            $record['NACH Signup'] = $val['NACH_Signup'];
            $record['ENACH Signup'] = $val['ENACH_Signup'];
            $record['Form Processed'] = $val['Form_Processed'];
            /*             * * Canculate enrol% * */
            $date = new \DateTime();
            $date90DayBefore = $date->modify('-30 days')->format('Y-m-d');
            $allCrmId = $val['allCrmId'];

//            $succesEnrol = BankingEnrolStatus::where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusSuccess])
//                ->whereIn('CRM_ID', $allCrmId)
//                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
//                ->count();
//            $failtEnrol = BankingEnrolStatus::where(['EnrolStatus' => BankingEnrolStatus::EnrolStatusFail])
//                ->whereIn('CRM_ID', $allCrmId)
//                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
//                ->count();
//            $enrolDivided = $succesEnrol + $failtEnrol;
//            $enrolPerCol = '0 %';
//            if ($enrolDivided != 0) {
//                $enrolPerCol = ($succesEnrol / $enrolDivided) * 100;
//                $enrolPerCol = (int)$enrolPerCol;
//            }
//            $record['Enrol %'] = $enrolPerCol . ' %';
            $getEnrolPerSuccess = BankingEnrolStatus::where(['Prospect_Master.pinCode' => $key])
                ->leftJoin('Prospect_Master', 'Banking_Enrol_Status.CRM_ID', 'Prospect_Master.CRM_ID')
                ->where(['Banking_Enrol_Status.EnrolStatus' => BankingEnrolStatus::EnrolStatusSuccess])
                ->where(['Banking_Enrol_Status.ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                ->whereBetween('Banking_Enrol_Status.updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getEnrolPerFail = BankingEnrolStatus::where(['Prospect_Master.pinCode' => $key])
                ->leftJoin('Prospect_Master', 'Banking_Enrol_Status.CRM_ID', 'Prospect_Master.CRM_ID')
                ->where(['Banking_Enrol_Status.EnrolStatus' => BankingEnrolStatus::EnrolStatusFail])
                ->where(['Banking_Enrol_Status.ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                ->whereBetween('Banking_Enrol_Status.updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();

            $enrolDividedBy = $getEnrolPerSuccess + $getEnrolPerFail;
            $getEnrolPer = 0;
            if ($enrolDividedBy != 0) {
                $getEnrolPer = ($getEnrolPerSuccess / $enrolDividedBy) * 100;
            }
            $record['Enrol %'] = number_format((float)$getEnrolPer, '2', '.', '') . ' %';


            /*             * * Canculate debit% * */
//            $succesDebit = BankingDebitStatus::where(['DebitStatus' => BankingDebitStatus::DebitStatusSuccess])
//                ->whereIn('CRM_ID', $allCrmId)
//                ->where(['Debit_Attempt' => 1])
//                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
//                ->count();
//            $failDebit = BankingDebitStatus::where(['DebitStatus' => BankingDebitStatus::DebitStatusFail])
//                ->whereIn('CRM_ID', $allCrmId)
//                ->where(['Debit_Attempt' => 1])
//                ->whereBetween('updated_at', [$date90DayBefore, date('Y-m-d')])
//                ->count();
//            $debitDivided = $succesDebit + $failDebit;
//            $debitPerCol = '0 %';
//            if ($debitDivided != 0) {
//                $debitPerCol = ($succesDebit / $debitDivided) * 100;
//                $debitPerCol = (int)$debitPerCol . ' %';
//            }
//            $record['Debit %'] = $debitPerCol;

            /**
             * get debit %
             */
            $getDebitPerSuccess = BankingDebitStatus::where(['Prospect_Master.pinCode' => $key])
                ->leftJoin('Prospect_Master', 'Banking_Debit_Status.CRM_ID', 'Prospect_Master.CRM_ID')
                ->where(['Banking_Debit_Status.Debit_Attempt' => 1])
                ->where(['Banking_Debit_Status.DebitStatus' => BankingDebitStatus::DebitStatusSuccess])
                ->where(['Banking_Debit_Status.ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                ->whereBetween('Banking_Debit_Status.updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getDebitPerFail = BankingDebitStatus::where(['Prospect_Master.pinCode' => $key])
                ->leftJoin('Prospect_Master', 'Banking_Debit_Status.CRM_ID', 'Prospect_Master.CRM_ID')
                ->where(['Banking_Debit_Status.Debit_Attempt' => 1])
                ->where(['Banking_Debit_Status.DebitStatus' => BankingDebitStatus::DebitStatusFail])
                ->where(['Banking_Debit_Status.ModeOfDonation' => Signup::MODEOFDONATION_NACH])
                ->whereBetween('Banking_Debit_Status.updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();

            $debitDividedBy = $getDebitPerSuccess + $getDebitPerFail;
            $getDebitPer = 0;
            if ($debitDividedBy != 0) {
                $getDebitPer = ($getDebitPerSuccess / $debitDividedBy) * 100;
            }
            $record['Debit Nach'] = number_format((float)$getDebitPer, '2', '.', '') . ' %';

            $getDebitPerSuccess = BankingDebitStatus::where(['Prospect_Master.pinCode' => $key])
                ->leftJoin('Prospect_Master', 'Banking_Debit_Status.CRM_ID', 'Prospect_Master.CRM_ID')
                ->where(['Banking_Debit_Status.Debit_Attempt' => 1])
                ->where(['Banking_Debit_Status.DebitStatus' => BankingDebitStatus::DebitStatusSuccess])
                ->where(['Banking_Debit_Status.ModeOfDonation' => Signup::MODEOFDONATION_ENACH])
                ->whereBetween('Banking_Debit_Status.updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getDebitPerFail = BankingDebitStatus::where(['Prospect_Master.pinCode' => $key])
                ->leftJoin('Prospect_Master', 'Banking_Debit_Status.CRM_ID', 'Prospect_Master.CRM_ID')
                ->where(['Banking_Debit_Status.Debit_Attempt' => 1])
                ->where(['Banking_Debit_Status.DebitStatus' => BankingDebitStatus::DebitStatusFail])
                ->where(['Banking_Debit_Status.ModeOfDonation' => Signup::MODEOFDONATION_ENACH])
                ->whereBetween('Banking_Debit_Status.updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();

            $debitDividedBy = $getDebitPerSuccess + $getDebitPerFail;
            $getDebitPer = 0;
            if ($debitDividedBy != 0) {
                $getDebitPer = ($getDebitPerSuccess / $debitDividedBy) * 100;
            }
            $record['Debit ENach'] = number_format((float)$getDebitPer, '2', '.', '') . ' %';

            /*             * ** Canx %* */

            $getEnrolCancel = BankingEnrolStatus::where(['Prospect_Master.pinCode' => $key])
                ->leftJoin('Prospect_Master', 'Banking_Enrol_Status.CRM_ID', 'Prospect_Master.CRM_ID')
                ->where(['Banking_Enrol_Status.PledgeStatus' => BankingEnrolStatus::PledgeStatusCancel])
                ->whereBetween('Banking_Enrol_Status.updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getEnrolActive = BankingEnrolStatus::where(['Prospect_Master.pinCode' => $key])
                ->leftJoin('Prospect_Master', 'Banking_Enrol_Status.CRM_ID', 'Prospect_Master.CRM_ID')
                ->where(['Banking_Enrol_Status.PledgeStatus' => BankingEnrolStatus::PledgeStatusActive])
                ->whereBetween('Banking_Enrol_Status.updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();
            $getEnrolNull = BankingEnrolStatus::where(['Prospect_Master.pinCode' => $key])
                ->leftJoin('Prospect_Master', 'Banking_Enrol_Status.CRM_ID', 'Prospect_Master.CRM_ID')
                ->where(['Banking_Enrol_Status.PledgeStatus' => null])
                ->whereBetween('Banking_Enrol_Status.updated_at', [$date90DayBefore, date('Y-m-d')])
                ->count();

            $pledgeDividedBy = $getEnrolCancel + $getEnrolActive + $getEnrolNull;
            $getCancelPer = 0;
            if ($pledgeDividedBy != 0) {
                $getCancelPer = ($getEnrolCancel / $pledgeDividedBy) * 100;
            }

            $record['Canx'] = number_format((float)$getCancelPer, '2', '.', '') . ' %';

            $dataArray[] = $record;
        }
        /**
         * Start
         */
        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'locationPerformance');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function formQualityReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.formQualityReport', compact('getAllCity', 'getCharityCode'));
    }

    public function formQualityReport() {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();
        /**
         * Start
         */
        $getAllScore = SignupFormChk::select(
            'Prospect_Master.*',
            'Signup_FormChk.*',
            'Employee_Master.*',
            DB::raw('count(Prospect_Master.CRM_ID) as totalSignup')
        )
            ->leftJoin('Prospect_Master', 'Signup_FormChk.CRM_ID', 'Prospect_Master.CRM_ID')
            ->leftJoin('Employee_Master', 'Prospect_Master.EID', 'Employee_Master.EID')
            ->where('Employee_Master.EStatus', '!=', EmployeeMaster::EStatusLeft)
            ->whereIn('Signup_FormChk.FFPStatus', [SignupFormChk::FFPStatus_Accept, SignupFormChk::FFPStatus_Modify]);
        if ($city) {
            $getAllScore->where('Employee_Master.ECity', $city);
        }
        $getAllScore = $getAllScore->groupBy('Employee_Master.EID')
            ->orderBy('Employee_Master.EName')
            ->get();


        $dataArray = [];
        foreach ($getAllScore as $key => $val) {
            //Add group name and info
            $getCityGroup = ChatCityGroup::select('GrpName')
                ->where(['EID' => $val->getSignup->getEmployee->EID])
                ->groupBy('GrpId')->get()->toArray();
            $getTeamGroup = ChatTeamGroup::select('GrpName')
                ->where(['EID' => $val->getSignup->getEmployee->EID])
                ->groupBy('GrpId')->get()->toArray();
            $cityGroupNames = implode(', ', array_column($getCityGroup, 'GrpName'));
            $teamGroupNames = implode(', ', array_column($getTeamGroup, 'GrpName'));


            $record = [];
            $record['Fr Name'] = $val->getSignup->getEmployee->EName ?? '';
            $record['EID'] = $val->getSignup->getEmployee->EID ?? '';
            $record['ECity'] = $val->getSignup->getEmployee->getCity->Ecity ?? '';
            $record['EDesg'] = $val->getSignup->getEmployee->getDesg->EDesg ?? '';
            $record['City Group'] = $cityGroupNames ?? '';
            $record['Team Group'] = $teamGroupNames ?? '';
            $record['Total Nach Signup'] = $val->totalSignup;
            /**
             * Get Total %
             */
            $getAllCrm = SignupFormChk::where(['Employee_Master.EID' => $val->EID])
                ->leftJoin('Prospect_Master', 'Signup_FormChk.CRM_ID', 'Prospect_Master.CRM_ID')
                ->leftJoin('Employee_Master', 'Prospect_Master.EID', 'Employee_Master.EID')
                ->whereIn('Signup_FormChk.FFPStatus', [SignupFormChk::FFPStatus_Accept, SignupFormChk::FFPStatus_Modify]);
            if ($dateFrom && $dateTo) {
                $getAllCrm->whereBetween('Signup_FormChk.updated_at', [$dateFrom, $dateTo]);
            }
            if ($city) {
                $getAllCrm->where('Employee_Master.ECity', $city);
            }
            if ($charityCode) {
                $getAllCrm->where('Employee_Master.CharityCode', $charityCode);
            }
            $getAllCrm = $getAllCrm->get();

            $totalPercentage = 0;
            $totalScore = 0;
            foreach ($getAllCrm as $crmKey => $crmVal) {
                $isOverwrite = ($crmVal->IsOverwrite == 0) ? -2 : 2;
                $bCopySub = ($crmVal->BCopySub == 1) ? 1 : 0;
                $isActionTypeTick = ($crmVal->IsActionTypeTick == 1) ? 0.5 : 0;
                $isAccntType = ($crmVal->IsAccntTypeTick == 1) ? 0.5 : 0;
                $isAccntHldrNameMention = ($crmVal->IsAccntHldrNameMention == 1) ? 0.5 : 0;
                $isBankNameMention = ($crmVal->IsBankNameMention == 1) ? 0.5 : 0;
                $isDebitTypeTick = ($crmVal->IsDebitTypeTick == 1) ? 0.5 : 0;
                $isPhoneEmailMention = ($crmVal->IsPhoneEmailMentionNACH == 1) ? 0.5 : 0;
                $isAccountWordFigCheck = ($crmVal->IsAmountWordFigCheck == 1) ? 0.5 : 0;
                $isStartDateMention = ($crmVal->IsStartDateMention == 1) ? 0.5 : 0;
                $isPostDated = ($crmVal->IsPostDated == 1) ? 0.5 : 0;
                $noOfSignACopy = 0;
                $noOfSignBCopy = 0;
                if ($crmVal->NoOfSignACopy == 3) {
                    $noOfSignACopy = 1;
                }
                if ($crmVal->NoOfSignACopy == 2) {
                    $noOfSignACopy = 0.6;
                }
                if ($crmVal->NoOfSignACopy == 1) {
                    $noOfSignACopy = 0.3;
                }
                if ($crmVal->NoOfSignBCopy == 3) {
                    $noOfSignBCopy = 1;
                }
                if ($crmVal->NoOfSignBCopy == 2) {
                    $noOfSignBCopy = 0.6;
                }
                if ($crmVal->NoOfSignBCopy == 1) {
                    $noOfSignBCopy = 0.3;
                }
                $isAddress = ($crmVal->IsAddresComplete == 1) ? 0.5 : 0;
                $isPinCode = ($crmVal->IsPinCodeCap == 1) ? 0.5 : 0;
                $isPhoneMention = ($crmVal->IsPhoneMention == 1) ? 0.5 : 0;
                $isEmailMention = ($crmVal->IsEmailMention == 1) ? 0.5 : 0;
                $isDobMention = ($crmVal->IsDOBMention == 1) ? 0.5 : 0;
                $subFormAckSign = ($crmVal->SupFormAckSign == 1) ? 0.5 : 0;
                $isFidMenction = ($crmVal->IsFidMention == 1) ? 0.5 : 0;

                $totalScore += $isOverwrite + $bCopySub + $isActionTypeTick +
                    $isAccntType + $isAccntHldrNameMention + $isBankNameMention +
                    $isDebitTypeTick + $isPhoneEmailMention + $isAccountWordFigCheck +
                    $isStartDateMention + $isPostDated + $noOfSignACopy +
                    $noOfSignBCopy + $isAddress + $isPinCode +
                    $isPhoneMention + $isEmailMention + $isDobMention +
                    $subFormAckSign + $isFidMenction;
            }
            $percentage = 0;
            if (count($getAllCrm) > 0) {
                $getPercentage = ($totalScore / (20 * count($getAllCrm))) * 100;
                $percentage = number_format((float)$getPercentage, '2', '.', '');
                if ($getPercentage < 0) {
                    $percentage = 0;
                }
            }
            $record['Avg Form Quality Score(%)'] = $percentage . ' %';
            $dataArray[] = $record;
        }

        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'formQuality');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function donorAwarenessReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.donorAwarenessReport', compact('getAllCity', 'getCharityCode'));
    }

    public function donorAwarenessReport() {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();
        /**
         * Start
         */
        $getAllScore = EmployeeMaster::whereIn('EDesg', [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_STL]);
        if ($city) {
            $getAllScore->where('Employee_Master.ECity', $city);
        }
        if ($charityCode) {
            $getAllScore->where('Employee_Master.CharityCode', $charityCode);
        }
        $getAllEmployee = $getAllScore->where('EStatus', '!=', EmployeeMaster::EStatusLeft)
            ->get();
        $dataArray = [];
        foreach ($getAllEmployee as $val) {
            //Add group name and info
            $getCityGroup = ChatCityGroup::select('GrpName')
                ->where(['EID' => $val->EID])
                ->groupBy('GrpId')->get()->toArray();
            $getTeamGroup = ChatTeamGroup::select('GrpName')
                ->where(['EID' => $val->EID])
                ->groupBy('GrpId')->get()->toArray();
            $cityGroupNames = implode(', ', array_column($getCityGroup, 'GrpName'));
            $teamGroupNames = implode(', ', array_column($getTeamGroup, 'GrpName'));


            $record = [];
            $record['EID'] = $val->EID;
            $record['Fr Name'] = $val->EName;
            $record['EDesg'] = $val->getDesg->EDesg ?? '';
            $record['City Group'] = $cityGroupNames ?? '';
            $record['Team Group'] = $teamGroupNames ?? '';
            $record['ECity'] = $val->getCity->Ecity ?? '';

            $totalSignup = Signup::orderBy('CRM_ID')
                ->whereHas('getEmployee', fn($q) => $q->where(['EID' => $val->EID]));
            if ($dateFrom && $dateTo) {
                $totalSignup->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            $totalSignupCount = $totalSignup->count();
            if ($dateFrom && $dateTo) {
                $totalSignup->whereBetween('PDate', [$dateFrom, $dateTo]);
            }
            $totalSignupCount = $totalSignup->count();


            /**
             *
             * Total Welcome call
             *
             */
            $totalWelcomeCall = SignupWlcmCall::orderBy('CRM_ID')
                ->where(['Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_verified])
                ->whereHas('getSignup', function ($qs) use ($val, $dateFrom, $dateTo) {

                    if ($dateFrom && $dateTo) {
                        $qs = $qs->whereBetween('PDate', [$dateFrom, $dateTo]);
                    }

                    return $qs->whereHas('getEmployee', fn($q) => $q->where(['EID' => $val->EID]));
                });
//                    ->whereHas('getEmployee', fn($q) => $q->where(['EID' => $val->EID]));
            $totalWelcomeCall = $totalWelcomeCall->count();


            $record['Total Signup'] = $totalSignupCount;
            $getAllSignup = $totalSignup->get();
            $totalAwareCases = 0;
            $totalTeamSupport = 0;
            $totalEnqueryCanx = 0;
            $totalAwareCasesTotal = 0;
            $totalTeamSupportTotal = 0;
            $totalEnqueryCanxTotal = 0;
            foreach ($getAllSignup as $val) {
                if ($val->getWelcomeCall) {
                    $welcomeCallDetail = $val->getWelcomeCall;
                    if ($welcomeCallDetail->IsSupAwareCause !== null) {
                        $totalAwareCases += ($welcomeCallDetail->IsSupAwareCause == 1) ? 1 : -1;
                        $totalAwareCasesTotal += 1;
                    }
                    if ($welcomeCallDetail->IsSupAwareMonthly !== null) {
                        $totalTeamSupport += ($welcomeCallDetail->IsSupAwareMonthly == 1) ? 1 : -1;
                        $totalTeamSupportTotal += 1;
                    }
                    if ($welcomeCallDetail->IsEnqCanx !== null) {
                        $totalEnqueryCanx += ($welcomeCallDetail->IsEnqCanx == 0) ? 1 : -1;
                        $totalEnqueryCanxTotal += 1;
                    }
                }
            }


            $record['Welcome Call Verified'] = $totalWelcomeCall;
            $record['Supporter Aware Of Cause'] = $totalAwareCases;
            $record['Supporter Aware Of Long Term Support'] = $totalTeamSupport;
            $record['Did Supporter enquire about Canx'] = $totalEnqueryCanx;

            $donorPer = 0;
            if ($totalWelcomeCall != 0) {
                $totalSupportsCount = $totalAwareCases + $totalTeamSupport + $totalEnqueryCanx;
                if ($totalSupportsCount <= 0) {
                    $donorPer = 0;
                } else {
                    $totalDivBy = $totalAwareCasesTotal + $totalTeamSupportTotal + $totalEnqueryCanxTotal;
                    $donorPer = ((($totalAwareCases + $totalTeamSupport + $totalEnqueryCanx)) / ($totalDivBy)) * 100;
                }
                $donorPer = (int)$donorPer;
            }


            $record['Avg. Donor Feedback Score(%)'] = $donorPer . ' %';
            $dataArray[] = $record;
        }

        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'donorAwareness');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function billingReportView() {
        $getCharityCode = CharityCode::all();
        $query = ClientDataExport::select('id', 'name')->get();

        /** This if after import complete * */
        $getCompleteReport = '';
        if (Session::get('importComplete')) {
            $getCompleteReport = Session::get('importComplete');
            Session::forget('importComplete');
        }
        $billingImportColumn = ['CRM_ID', 'Charity_ID', 'invoice_no', 'invoice_date', 'claw_back'];

        return view('report.billingReport', compact('query', 'billingImportColumn', 'getCharityCode', 'getCompleteReport'));
    }

    //billingreport available in backoffice controller
    //billingreport import available in import controller

    public function donorFeedbackReportView() {
        $getAllCity = EmployeeECity::get();
        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }
        $getCharityCode = CharityCode::all();
        return view('report.donorFeedbackReport', compact('getAllCity', 'getCharityCode'));
    }

    public function donorFeedbackReport() {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();
        /**
         * Start
         */
        $getAllDonor = Signup::select(
            DB::raw('Signup.CRM_ID as CRM_ID'),
            DB::raw('Signup.EID as EID'),
            DB::raw('Employee_Master.ECity as ECity'),
            DB::raw('Employee_Master.EID as EMEID'),
            DB::raw('Employee_ECity.Ecity as Ecity'),
            DB::raw('Prospect_Master.FullName as FullName'),
            DB::raw('Prospect_Master.CRM_ID as PCRM_ID')
        )
            ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
            ->leftJoin('Employee_ECity', 'Employee_Master.ECity', 'Employee_ECity.id')
            ->leftJoin('Prospect_Master', 'Signup.CRM_ID', 'Prospect_Master.CRM_ID')
            ->where('Signup.EID', '!=', null)
            ->where('Signup.EID', '!=', '');
        if ($dateFrom && $dateTo) {
            $getAllDonor->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
        }
        if ($city) {
            $getAllDonor->where('Employee_Master.ECity', $city);
        }
        if ($charityCode) {
            $getAllDonor->where('Employee_Master.CharityCode', $charityCode);
        }
        $getAllDonor = $getAllDonor->orderBy('Signup.CRM_ID')
            ->groupBy('Signup.CRM_ID')
            ->get();

        $dataArray = [];
        foreach ($getAllDonor as $key => $val) {
            $record = [];
            $record['CRM Id'] = $val->CRM_ID;
            $record['Donor Name'] = $val->FullName;
            $record['City'] = $val->Ecity;

            $funName = '';
            $tlName = '';
            $getEmployee = EmployeeMaster::where(['EID' => $val->EID])
                ->whereIn('EDesg', [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_STL])
                ->first();
            if ($getEmployee) {
                if ($getEmployee->EDesg == EmployeeEDesg::DESG_FR) {
                    $funName = $getEmployee->EName ?? '';
                    $tlName = $getEmployee->getETL->EName ?? '';
                }
                if ($getEmployee->EDesg == EmployeeEDesg::DESG_TL) {
                    $funName = $getEmployee->EName ?? '';
                    $tlName = '';
                }
            }

            $record['Fundraiser'] = $funName;
            $record['TL'] = $tlName;
            $getWelcomeCallDetail = WlcmCallDetail::where(['CRM_ID' => $val->CRM_ID])->orderBy('Call_TimeStamp', 'desc')->first();
            $outcome = SignupWlcmCall::getCallOutcome();
            $dFeedback = '';
            if ($getWelcomeCallDetail && $getWelcomeCallDetail->CallOutcome) {
                $dFeedback = $outcome[$getWelcomeCallDetail->CallOutcome] ?? '';
                if ($getWelcomeCallDetail->CallOutcome == SignupWlcmCall::Outcome_Other) {
                    $dFeedback = $getWelcomeCallDetail->Call_FinalStatusRemark;
                }
            }
            $record['Donor Feedback'] = $dFeedback ?? '';
            $dataArray[] = $record;
        }

        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'donorFeedback');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }


    public function nonBCopyDuplicatesView() {
        $getCharityCode = CharityCode::get();
        return view('report.nonBCopyDuplicates', compact('getCharityCode'));
    }

    public function nonBCopyDuplicates() {
        $input = request()->all();

        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();

        $getAllProspect = ProspectMaster::select(
            '*',
            DB::raw('GROUP_CONCAT(Prospect_Master.CRM_ID SEPARATOR ",") as CRM_LIST'),
            DB::raw('count(Signup_AccountChk.AccountNo) as AccountNoCount'),
            DB::raw('count(Signup_AccountChk.OnlineTransactionID) as OnlineTransactionIDCount')

//            DB::raw('CONCAT(Signup_DataEntry.Mobile_1,Signup_DataEntry.Mobile_2) as MobileNumber'),
//            DB::raw('count(CONCAT(Signup_DataEntry.Mobile_1,Signup_DataEntry.Mobile_2)) as MobileNumberCount')
        )
            ->leftJoin('Signup', 'Prospect_Master.CRM_ID', 'Signup.CRM_ID')
            ->leftJoin('Signup_DataEntry', 'Prospect_Master.CRM_ID', 'Signup_DataEntry.CRM_ID')
            ->leftJoin('Signup_AccountChk', 'Prospect_Master.CRM_ID', 'Signup_AccountChk.CRM_ID')
            ->whereBetween('Prospect_Master.PDate', [$dateFrom, $dateTo])
            ->groupBy('Signup_AccountChk.AccountNo', 'Signup_AccountChk.OnlineTransactionID');

        if ($charityCode) {
            $getAllProspect = $getAllProspect->where(['Prospect_Master.CharityCode' => $charityCode]);
        }

        $getAllProspect = $getAllProspect->having('AccountNoCount', '>', 1)
            ->orHaving('OnlineTransactionIDCount', '>', 1)
//            ->orHaving('MobileNumberCount', '>', 1)
            ->get();

        $dataArray = [];
        foreach ($getAllProspect as $value) {
            $getAllCRMIds = explode(',', $value->CRM_LIST);
            $getProspectDetails = ProspectMaster::whereIn('CRM_ID', $getAllCRMIds)->get();
            foreach ($getProspectDetails as $prosList) {
                $record = [];
                $record['CRM_ID'] = $prosList->CRM_ID;

                $record['Donor Name'] = $prosList->FullName;
                $record['Created Date'] = $prosList->PDate;
                $record['FR FID'] = $prosList->FID;
                $record['FR EID'] = $prosList->EID;
                $record['FR Name'] = $prosList->getEmployee->EName ?? null;
                $record['Signup Date'] = $prosList->getSignup->PDate ?? null;
                $record['AV Status'] = isset(SignupAccountChk::getBOStatUpdate()[$prosList->getSignup->getSignupAccCheck->BOStatUpdate]) ? SignupAccountChk::getBOStatUpdate()[$prosList->getSignup->getSignupAccCheck->BOStatUpdate] : null;
                $bakingEnrolPledgeStatus = BankingEnrolStatus::getPledgeStatus();
                $bakingEnrolStatus = BankingEnrolStatus::getEnrolStatus();
                $record['Pledge Status'] = isset($prosList->getSignup->getBankingEnrolStatus->PledgeStatus) ? $bakingEnrolPledgeStatus[$prosList->getSignup->getBankingEnrolStatus->PledgeStatus] : null;
                $record['Enroll Status'] = isset($prosList->getSignup->getBankingEnrolStatus->EnrolStatus) ? $bakingEnrolStatus[$prosList->getSignup->getBankingEnrolStatus->EnrolStatus] : null;
                $record['Enroll Status Reason'] = isset($prosList->getSignup->getBankingEnrolStatus->ReasonDesc) ? $prosList->getSignup->getBankingEnrolStatus->ReasonDesc : null;
                $bakingDebitStatus = BankingDebitStatus::getDebitStatus();
                $record['Debit Status'] = isset($prosList->getSignup->getBankingDebitStatus->DebitStatus) ? $bakingDebitStatus[$prosList->getSignup->getBankingDebitStatus->DebitStatus] : null;
                $signupAccChkFrequency = SignupAccountChk::getAllFrequency();
                $record['Frequency'] = isset($signupAccChkFrequency[$prosList->getSignup->getSignupAccCheck->Frequency]) ? $signupAccChkFrequency[$prosList->getSignup->getSignupAccCheck->Frequency] : null;
                $getModeOfDonation = Signup::modeOfDonation();
                $record['Mode of Donation'] = isset($getModeOfDonation[$prosList->getSignup->ModeOfDonation]) ? $getModeOfDonation[$prosList->getSignup->ModeOfDonation] : null;
                $record['Amount'] = isset($prosList->getSignup->getSignupAccCheck->Amount) ? $prosList->getSignup->getSignupAccCheck->Amount : null;

                $AccountNo = $prosList->getSignup->getSignupAccCheck->AccountNo ?? '';
                $record['AccountNo'] = (string)$AccountNo;
                $record['OnlineTransactionID'] = $prosList->getSignup->getSignupAccCheck->OnlineTransactionID ?? '';
                $record['Mobile_1'] = $prosList->getSignup->getSignupDataEntry->Mobile_1 ?? '';
                $record['Mobile_2'] = $prosList->getSignup->getSignupDataEntry->Mobile_2 ?? '';
                $record['Concat_CRM_ID'] = $value->CRM_LIST;
                $dataArray[] = $record;
            }
        }
        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'Duplicate');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }

    public function incentiveDataReportView() {
        $getCharityCode = CharityCode::get();
        $getAllCity = EmployeeECity::get();
        return view('report.incentiveData', compact('getCharityCode', 'getAllCity'));
    }

    public function incentiveDataReport() {
        $input = request()->all();
        if (!$input['dateFrom']) {
            return $this->sendResponse(false, '', 'Please Select Start Date.');
        }
        if (!$input['dateTo']) {
            return $this->sendResponse(false, '', 'Please Select End Date.');
        }
        $withBilling = false;
        $billing = isset($input['withBilling']) ?? null;
        if ($billing && ($billing == 'on')) {
            $withBilling = true;
        }

        list($year, $month, $day, $dateFrom, $dateTo, $city, $charityCode, $locType) = $this->getBackOfficeFilter();

        $getSignupData = Signup::whereBetween('PDate', [$dateFrom, $dateTo]);
        if ($charityCode) {
            $getSignupData = $getSignupData->where(['CharityCode' => $charityCode]);
        }
        if ($city) {
            $getSignupData = $getSignupData->whereHas('getEmployee', function ($q) use ($city) {
                if ($city) {
                    $q = $q->where(['ECity' => $city]);
                }
                return $q;
            });
        }
        $getSignupData = $getSignupData->get();

        $dataArray = [];
        $srNo = 1;
        foreach ($getSignupData as $signup) {
            $record = [];
            $employee = $signup->getEmployee ?? null;
            $signupAck = $signup->getSignupAccCheck ?? null;
            $signupWcall = $signup->getWelcomeCall ?? null;
            $signupFQ = $signup->getSignupFormChk ?? null;
            $signupDatEnt = $signup->getSignupDataEntry ?? null;
            $designation = $employee->getDesg ?? null;
            $city = $employee->getCity ?? null;
            $getETL = null;
            if ($employee->ETLID) {
                $getETL = EmployeeMaster::where(['EID' => $employee->ETLID])->first();
            }
            $modeOfDonation = Signup::modeOfDonation();
            $frequency = SignupAccountChk::getAllFrequency();
            $bOStatUpdate = SignupAccountChk::getBOStatUpdate();
            $signupWcallSt = SignupWlcmCall::getCallFinalStatus();
            $signupFqStatus = SignupFormChk::getFFPStatus();
            $signupDataEntrStat = SignupDataEntry::getDataEntryStatus();
            $enrolStatus = $signup->getBankingEnrolStatus ?? null;
            $bankingEnrolStat = BankingEnrolStatus::getEnrolStatus();
            $debitStatus = $signup->getBankingDebitStatus ?? null;
            $bankingDebitStat = BankingDebitStatus::getDebitStatus();
            $pledgeStatus = BankingEnrolStatus::getPledgeStatus();

            $payout = true;
            if (
                (isset($signupAck->BOStatUpdate) && ($signupAck->BOStatUpdate == SignupAccountChk::STATUS_REJECTED)) ||
                (isset($signupWcall->Call_FinalStatus) && in_array($signupWcall->Call_FinalStatus, [SignupWlcmCall::Call_FinalStatus_rejected, SignupWlcmCall::Call_FinalStatus_process_unverified])) ||
                (isset($signupFQ->FFPStatus) && ($signupFQ->FFPStatus == SignupFormChk::FFPStatus_Reject)) ||
                (isset($signupDatEnt->dataEntryStatus) && ($signupDatEnt->dataEntryStatus == SignupDataEntry::dataEntryStatus_Reject)) ||
                (isset($enrolStatus->EnrolStatus) && ($enrolStatus->EnrolStatus == BankingEnrolStatus::EnrolStatusFail)) ||
                (isset($enrolStatus->PledgeStatus) && in_array($enrolStatus->PledgeStatus, [BankingEnrolStatus::PledgeStatusBurnt, BankingEnrolStatus::PledgeStatusCancel, BankingEnrolStatus::PledgeStatusRejected])) ||
//                (isset($debitStatus[0]->DebitStatus) && ($debitStatus[0]->DebitStatus == BankingDebitStatus::DebitStatusFail)) ||
//                (isset($debitStatus[1]->DebitStatus) && ($debitStatus[1]->DebitStatus == BankingDebitStatus::DebitStatusFail)) ||
//                (isset($debitStatus[2]->DebitStatus) && ($debitStatus[2]->DebitStatus == BankingDebitStatus::DebitStatusFail)) ||
                /*2 or grter then 2 will be false*/
                ((isset($debitStatus[0]->DebitStatus) && ($debitStatus[0]->DebitStatus == BankingDebitStatus::DebitStatusFail)) &&
                    (isset($debitStatus[1]->DebitStatus) && ($debitStatus[1]->DebitStatus == BankingDebitStatus::DebitStatusFail))) ||
                ((isset($debitStatus[1]->DebitStatus) && ($debitStatus[1]->DebitStatus == BankingDebitStatus::DebitStatusFail)) &&
                    (isset($debitStatus[2]->DebitStatus) && ($debitStatus[2]->DebitStatus == BankingDebitStatus::DebitStatusFail))) ||
                ((isset($debitStatus[0]->DebitStatus) && ($debitStatus[0]->DebitStatus == BankingDebitStatus::DebitStatusFail)) &&
                    (isset($debitStatus[2]->DebitStatus) && ($debitStatus[2]->DebitStatus == BankingDebitStatus::DebitStatusFail)))
            ) {
                $payout = false;
            }

            $record['Sr. No.'] = $srNo++;
            $record['CRM_ID'] = $signup->CRM_ID;
            $record['Charity Id'] = $signupDatEnt->Charity_ID ?? null;
            $record['Transaction Id'] = $signupAck->OnlineTransactionID ?? null;
            $record['Export'] = $signupDatEnt->BatchNo ?? null;
            $record['Loc Type'] = $signup->LocType;
            $record['EID'] = $employee->EID ?? null;
            $record['FID'] = $employee->FID ?? null;
            $record['EName'] = $employee->EName ?? null;
            $record['Designation'] = $designation->EDesg ?? null;
            $record['City'] = $city->Ecity ?? null;
            $record['ETLID'] = $signup->ETLID ?? null;
            $record['ETL'] = $signup->ETL ?? null;
            $record['Full Name'] = $signup->getProspect->FullName;
            $record['Mobile_1'] = $signup->getProspect->Mobile_1;
            $record['Email Address'] = $signup->getProspect->eMail_Address;
            $record['PDate'] = $signup->PDate;
            $record['PTime'] = $signup->PTime;
            $record['Mode of donation'] = isset($signup->ModeOfDonation) ? $modeOfDonation[$signup->ModeOfDonation] : null;
            $record['Account Check'] = $signup->accountCheck ?? null;
            $record['Frequency'] = isset($signupAck->Frequency) ? $frequency[$signupAck->Frequency] : null;
            $record['Amount'] = $signupAck->Amount ?? null;
            $record['AV Status'] = isset($signupAck->BOStatUpdate) ? $bOStatUpdate[$signupAck->BOStatUpdate] : null;
            $record['BOStatRemark'] = $signupAck->BOStatRemark ?? null;
            $record['Welcome call status'] = isset($signupWcall->Call_FinalStatus) ? $signupWcallSt[$signupWcall->Call_FinalStatus] : null;
            $record['Call Final Status Remark'] = $signupWcall->Call_FinalStatusRemark ?? null;
            $record['FQ Status'] = isset($signupFQ->FFPStatus) ? $signupFqStatus[$signupFQ->FFPStatus] : null;
            $record['Remark'] = $signupFQ->Remarks ?? null;
            $record['Data Entry Status'] = isset($signupDatEnt->dataEntryStatus) ? $signupDataEntrStat[$signupDatEnt->dataEntryStatus] : null;
            $record['Data Entry Remark'] = $signupDatEnt->DataEntryRemarks ?? null;
            $record['Enrol Stat'] = isset($enrolStatus->EnrolStatus) ? $bankingEnrolStat[$enrolStatus->EnrolStatus] : null;
            $record['Enrol Stat Reason'] = $enrolStatus->ReasonDesc ?? null;

            /*Debit entry can be multiple max to 3*/
            $record['Debit Status 1'] = isset($debitStatus[0]->DebitStatus) ? $bankingDebitStat[$debitStatus[0]->DebitStatus] : null;
            $record['Debit Reason Desc 1'] = $debitStatus[0]->DebitReasonDesc ?? null;
            $record['Debit Status 2'] = isset($debitStatus[1]->DebitStatus) ? $bankingDebitStat[$debitStatus[1]->DebitStatus] : null;
            $record['Debit Reason Desc 2'] = $debitStatus[1]->DebitReasonDesc ?? null;
            $record['Debit Status 3'] = isset($debitStatus[2]->DebitStatus) ? $bankingDebitStat[$debitStatus[2]->DebitStatus] : null;
            $record['Debit Reason Desc 3'] = $debitStatus[2]->DebitReasonDesc ?? null;

            $record['Pledge Status'] = isset($enrolStatus->PledgeStatus) ? $pledgeStatus[$enrolStatus->PledgeStatus] : null;
            $record['Payout'] = $payout ? 'Yes' : 'No';
            if ($withBilling) {
                $record['Invoice No'] = $signup->invoice_no ?? null;
                $record['Invoice Date'] = $signup->invoice_date ?? null;
                $record['Claw Back'] = $signup->claw_back ?? null;
            }
            $dataArray[] = $record;
        }

        if (count($dataArray)) {
            $getFileUrl = $this->writeXlsxFile($dataArray, null, 'Incentive_Data');
            $responseData = [
                'fileUrl' => $getFileUrl
            ];
            return $this->sendResponse(true, '', 'Export Successfully', $responseData, 'exportDataTable');
        }
        return $this->sendResponse(false, '', 'No Record Found With Given Filter.');
    }


    /*     * Write CSV FILE************************************************************ */

    public function writeCSVFile($resultData = [1, 2]) {
        $fileName = uniqid() . '.csv';
        $file = fopen(public_path() . '/temp/' . $fileName, 'w');
        foreach ($resultData as $row) {
            fputcsv($file, $row);
        }
        fclose($file);
        $fileUrl = url('/') . '/temp/' . $fileName;
        $data = [
            'fileUrl' => $fileUrl
        ];
        return $data;
    }

    /**
     *
     * @param type $arrayData
     * @param type $customExcelChange
     * @param type $getFileName
     * @return string
     */
    public function writeXlsxFile($arrayData, $customExcelChange = null, $getFileName = null) {
        $getFileName = $getFileName ? $getFileName . '_' : '';
        $fileName = $getFileName . uniqid() . '.xlsx';
        $filePath = public_path() . '/temp/' . $fileName;
        $fileUrl = url('/') . '/temp/' . $fileName;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $row = 1;
        foreach ($arrayData as $key => $val) {
            $cal = 'A';
            if ($key == 0) {
                $newArray = array_keys($val);
                foreach ($newArray as $headers) {
                    $sheet->setCellValue($cal . $row, $headers);
                    $cal++;
                }
                $row++;
                $cal = 'A';
            }
            foreach ($val as $header => $data) {
                $sheet->setCellValue($cal . $row, $data);
                if ($customExcelChange) {
                    $this->$customExcelChange($sheet, $header, $cal . $row, $data);
                }
                $cal++;
            }
            $row++;
        }
//        $sheet->setCellValue('A1', 'Hello World !');
//        $sheet->setCellValue('B1', 'Hello World !');

        /*This code is to set the width of column start*/
        $cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);
        /** @var PHPExcel_Cell $cell */
        foreach ($cellIterator as $cell) {
            $sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
        }
        /*This code is to set the width of column end */

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $fileUrl;
    }

    public function productivityCustomChanges($sheet, $header, $colRow, $data) {

        $dateRange = range(1, 31);

        if (!in_array($header, $dateRange)) {
            return;
        }
        //red D9ff0000
        //yellow D9fff200
        //green D900ff00

        $color = 'D9ff0000';
        if ($data > 0) {
            $color = 'D900ff00';
        }
        if ($data == 0) {
            $color = 'D9fff200';
        }
        if ($data === null) {
            $color = 'D9ff0000';
        }

        $styleArray = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'rotation' => 90,
                'startColor' => [
                    'argb' => $color,
                ],
                'endColor' => [
                    'argb' => $color,
                ],
            ],
        ];
        $sheet->getStyle($colRow)->applyFromArray($styleArray);
    }

    public function writeXlsxFileWelcomeCall($arrayData, $getFileName = null) {
        $getFileName = $getFileName ? $getFileName . '_' : '';
        $fileName = $getFileName . uniqid() . '.xlsx';
        $filePath = public_path() . '/temp/' . $fileName;
        $fileUrl = url('/') . '/temp/' . $fileName;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;
        foreach ($arrayData as $key => $val) {
            $cal = 'A';
            if ($key == 0) {
                $newArray = array_keys($val);
                foreach ($newArray as $headers) {
                    $sheet->setCellValue($cal . $row, $headers);
                    $styleArray = [
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'rotation' => 90,
                            'startColor' => [
                                'argb' => 'd9cfe2f3',
                            ],
                            'endColor' => [
                                'argb' => 'd9cfe2f3',
                            ],
                        ],
                    ];
                    $sheet->getStyle($cal . $row)->applyFromArray($styleArray);
                    $cal++;
                }
                $row++;
                $cal = 'A';
            }
            foreach ($val as $header => $data) {
                if (is_array($data)) {
                    $sheet->setCellValue($cal . $row, $data['val']);
                    if (isset($data['color']) && $data['color']) {
                        $styleArray = [
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'rotation' => 90,
                                'startColor' => [
                                    'argb' => $data['color'],
                                ],
                                'endColor' => [
                                    'argb' => $data['color'],
                                ],
                            ],
                        ];
                        $sheet->getStyle($cal . $row)->applyFromArray($styleArray);
                    }
                } else {
                    $sheet->setCellValue($cal . $row, $data);
                }
                $cal++;
            }
            $row++;
        }

        /*This code is to set the width of column start*/
        $cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);
        /** @var PHPExcel_Cell $cell */
        foreach ($cellIterator as $cell) {
            $sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
        }
        /*This code is to set the width of column end */

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $fileUrl;
    }

    public function writeXlsxFileProcessHealth($arrayData, $getFileName = null) {
        $getFileName = $getFileName ? $getFileName . '_' : '';
        $fileName = $getFileName . uniqid() . '.xlsx';
        $filePath = public_path() . '/temp/' . $fileName;
        $fileUrl = url('/') . '/temp/' . $fileName;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;
        foreach ($arrayData as $key => $val) {
            $cal = 'A';
            if ($key == 0) {
                $newArray = array_keys($val);
                foreach ($newArray as $headers) {
                    $sheet->setCellValue($cal . $row, $headers);
                    $styleArray = [
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'rotation' => 90,
                            'startColor' => [
                                'argb' => 'd9cfe2f3',
                            ],
                            'endColor' => [
                                'argb' => 'd9cfe2f3',
                            ],
                        ],
                    ];
                    $sheet->getStyle($cal . $row)->applyFromArray($styleArray);
                    $cal++;
                }
                $row++;
                $cal = 'A';
            }
            foreach ($val as $header => $data) {
                if (is_array($data)) {
                    $sheet->setCellValue($cal . $row, $data['val']);
                    if (isset($data['color']) && $data['color']) {
                        $styleArray = [
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'rotation' => 90,
                                'startColor' => [
                                    'argb' => $data['color'],
                                ],
                                'endColor' => [
                                    'argb' => $data['color'],
                                ],
                            ],
                        ];
                        $sheet->getStyle($cal . $row)->applyFromArray($styleArray);
                    }
                    if (isset($data['mergeCell']) && $data['mergeCell']) {
                        $sheet->mergeCells($data['mergeCell']);
                    }
                } else {
                    $sheet->setCellValue($cal . $row, $data);
                }
                $cal++;
            }
            $row++;
        }
        /*This code is to set the width of column start*/
        $cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);
        /** @var PHPExcel_Cell $cell */
        foreach ($cellIterator as $cell) {
            $sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
        }
        /*This code is to set the width of column end */
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $fileUrl;
    }

}
