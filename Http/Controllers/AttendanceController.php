<?php

namespace App\Http\Controllers;

use App\Helper\Common;
use App\Model\Attendance;
use App\Model\EmployeeECity;
use App\Model\EmployeeEDesg;
use App\Model\EmployeeMaster;
use App\Http\Requests\ModifyEmployeeAttendance;

class AttendanceController extends Controller {

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
    public function viewSearchAttendance($eid = null) {
        $attendance = null;
        $getAllCity = EmployeeECity::all();
        $getEmployeeDesg = EmployeeEDesg::whereIn('id', [EmployeeEDesg::DESG_FR, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM])->get();

        $allowEDesg = [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH];
        if (in_array($this->user->EDesg, $allowEDesg)) {
            $getAllCity = EmployeeECity::where(['id' => $this->user->ECity])->get();
        }

        $getAllEcity = [];
        foreach ($getAllCity as $ecities) {
            $getAllEcity[] = $ecities->id;
        }

        $input = request()->all();
        $fileUrl = null;
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d');

        $ECity = isset($input['ECity']) && !empty($input['ECity']) ? [$input['ECity']] : $getAllEcity;
        $EDesg = $input['EDesg'] ?? '';
        $eName = $input['eName'] ?? '';

        if (($dateFrom && $dateTo) || $ECity || $EDesg || $eName) {
            if (isset($input['dateFrom']) && $input['dateFrom']) {
                $dateFrom = Common::fixDateFormat($input['dateFrom'], 'd-m-Y', 'Y-m-d');
            }
            if (isset($input['dateTo']) && $input['dateTo']) {
                $dateTo = Common::fixDateFormat($input['dateTo'], 'd-m-Y', 'Y-m-d');
            }
            if ($dateFrom > $dateTo) {
                return $this->sendResponse(false, '', 'Invalid Date Range.');
            }
            $attendance = Attendance::leftJoin('Employee_Master', 'Attendance.EID', 'Employee_Master.EID');
            if ($dateFrom && $dateTo) {
                $whereDateTo = $this->upateDateTo($dateTo);
                $attendance = $attendance->whereBetween('InStamp', [$dateFrom, $whereDateTo]);
            }
            if (count($ECity) > 0) {
                $attendance = $attendance->whereIn('Employee_Master.ECity', $ECity);
            }
            if ($EDesg) {
                $attendance = $attendance->where(['Employee_Master.EDesg' => $EDesg]);
            }
            if ($eName) {
                $attendance = $attendance->where(function($q) use ($eName) {
                    return $q->where('Employee_Master.EName', 'LIKE', '%' . $eName . '%')
                                    ->orWhere('Employee_Master.EID', 'LIKE', '%' . $eName . '%');
                });
            }
            /* if employee left */
//            $attendance = $attendance->where('Employee_Master.EStatus', '!=', EmployeeMaster::EStatusLeft);
            /* if employee left */
            if ($eid) {
                $attendance = $attendance->where(['Employee_Master.EID' => $eid])->get();
                return view('attendance.viewEmployeeAttendance', compact('attendance'));
            } else {
                $fileUrl = $this->createExcelFile($attendance, $dateFrom, $dateTo);
                $attendance = $attendance->paginate($this->pageSize);
            }
        }
        return view('attendance.viewSearchAttendance', compact('attendance', 'getAllCity', 'getEmployeeDesg', 'fileUrl'));
    }

    public function viewModifyAttendance($srNo = null) {
        $attendance = null;
        $getAllCity = EmployeeECity::all();
        $getEmployeeDesg = EmployeeEDesg::all();
        $input = request()->all();
        $fileUrl = null;
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d');
        $ECity = $input['ECity'] ?? '';
        $EDesg = $input['EDesg'] ?? '';
        $eName = $input['eName'] ?? '';
        if (($dateFrom && $dateTo) || $ECity || $EDesg || $eName) {

            if (isset($input['dateFrom']) && $input['dateFrom']) {
                $dateFrom = Common::fixDateFormat($input['dateFrom'], 'd-m-Y', 'Y-m-d');
            }
            if (isset($input['dateTo']) && $input['dateTo']) {
                $dateTo = Common::fixDateFormat($input['dateTo'], 'd-m-Y', 'Y-m-d');
            }
            if ($dateFrom > $dateTo) {
                return $this->sendResponse(false, '', 'Invalid Date Range.');
            }
            $attendance = Attendance::leftJoin('Employee_Master', 'Attendance.EID', 'Employee_Master.EID');
            if ($dateFrom && $dateTo) {
                $whereDateTo = $this->upateDateTo($dateTo);
                $attendance = $attendance->whereBetween('InStamp', [$dateFrom, $whereDateTo]);
            }
            if ($ECity) {
                $attendance = $attendance->where(['Employee_Master.ECity' => $ECity]);
            }
            if ($EDesg) {
                $attendance = $attendance->where(['Employee_Master.EDesg' => $EDesg]);
            }
            if ($eName) {
                $attendance = $attendance->where(function($q) use ($eName) {
                    return $q->where('Employee_Master.EName', 'LIKE', '%' . $eName . '%')
                                    ->orWhere('Employee_Master.EID', 'LIKE', '%' . $eName . '%');
                });
            }
            /* if employee left */
            $attendance = $attendance->where('Employee_Master.EStatus', '!=', EmployeeMaster::EStatusLeft);
            /* if employee left */
            if ($srNo) {
                $attendance = $attendance->where(['Attendance.SR_NO' => $srNo])->first();
                return view('attendance.modifyEmployeeAttendanceView', compact('attendance'));
            } else {
//                $attendancePrint = $attendance;
//                $fileUrl = $this->createExcelFile($attendancePrint, $dateFrom, $dateTo);
                $attendance = $attendance->paginate($this->pageSize);
            }
        }
        return view('attendance.viewModifyAttendance', compact('attendance', 'getAllCity', 'getEmployeeDesg', 'fileUrl'));
    }

    /**
     *
     * @param type $attendance
     * @param type $dateFrom
     * @param type $dateTo
     * @return type
     */
    public function createExcelFile($attendance, $dateFrom, $dateTo) {
        $arrayData = [];
        $getDateRange = Common::getDatesBetweenRange($dateFrom, $dateTo, 'Y-m-d');
        $getAllTendance = $attendance
                ->groupBy('Attendance.EID')
                ->get();
        $attendanceArray = [
            Attendance::ATTENDANCE_P => Attendance::ATTENDANCE_P_TEXT,
            Attendance::ATTENDANCE_HD => Attendance::ATTENDANCE_HD_TEXT,
            Attendance::ATTENDANCE_A => Attendance::ATTENDANCE_A_TEXT
        ];
        foreach ($getAllTendance as $val) {
            $result['EID'] = $val->EID;
            $result['Employee Name'] = $val->EName;
            $result['ECity'] = $val->getEmployee->getCity->Ecity ?? '';
            $result['EDesg'] = $val->getEmployee->getDesg->EDesg ?? '';

            $countTotalProd = 0;
            $result['TotProdHrs'] = 0;
            foreach ($getDateRange as $key => $dateVal) {
                $getAttendanceData = Attendance::where(['EID' => $val->EID])->whereDate('InStamp', $dateVal)->groupBy('EID')->first();
                $result[$dateVal] = Attendance::ATTENDANCE_A_TEXT;
                if ($getAttendanceData) {
                    $attendanceVal = $attendanceArray[$getAttendanceData->Attendance] ?? Attendance::ATTENDANCE_A_TEXT;
                    $result[$dateVal] = $getAttendanceData->AttnRemarks ?? $attendanceVal;
                    /**
                     * Count Production Hour
                     */
                    if ($getAttendanceData->TotProdHrs) {
                        $prodT = explode(':', $getAttendanceData->TotProdHrs);
                        $countTotalProd += $prodT[0] + ($prodT[1] / 60) + ($prodT[2] / (60 * 60));
                    }
                }
            }

            $result['TotProdHrs'] = number_format((float) $countTotalProd, 2, '.', '') . ' Hour';
            $arrayData[] = $result;
        }
        if (count($arrayData) == 0) {
            return;
        }
        $fileName = 'View_Attendance';
        return Common::writeXlsxFile($arrayData, $fileName);
    }

    public function modifyEmployeeAttendance($srNo, ModifyEmployeeAttendance $request) {
        $getAttendance = Attendance::where(['SR_NO' => $srNo])->first();
        if ($getAttendance) {
            $input = request()->all();
            $outStamp = Common::fixDateFormat($input['OutStamp'], 'd-m-Y H:i:s', 'Y-m-d H:i:s');
            $inStamp = Common::fixDateFormat($input['InStamp'], 'd-m-Y H:i:s', 'Y-m-d H:i:s');

            /*
             * That that the date is the same as attendance date
             */
            $inDate = Common::fixDateFormat($inStamp, 'Y-m-d H:i:s', 'Y-m-d');
            $outDate = Common::fixDateFormat($outStamp, 'Y-m-d H:i:s', 'Y-m-d');
            $createdAt = Common::fixDateFormat($getAttendance->created_at, 'Y-m-d H:i:s', 'Y-m-d');

            if (($inDate != $createdAt)) {
                return $this->sendResponse(false, '', 'Invalid In Stamp Date.');
            }
            if (($outDate != $createdAt)) {
                return $this->sendResponse(false, '', 'Invalid Out Stamp Date.');
            }
            if ($inStamp > $outStamp) {
                return $this->sendResponse(false, '', 'Checkout time can\'t be lower then check in time.');
            }

            $datetime1 = new \DateTime($inStamp);
            $datetime2 = new \DateTime($outStamp);
            $interval = $datetime1->diff($datetime2);
//        echo $interval->format('%Y-%m-%d %H:%i:%s');
            $TotProdHrs = $interval->format('%H:%i:%s');


//        If TotProdHrs>4 $$ <6, HD TotProdHrs>6,P
            $attendance = Attendance::ATTENDANCE_A;
            if ($TotProdHrs >= 3 && $TotProdHrs < 6) {
                $attendance = Attendance::ATTENDANCE_HD;
            } elseif ($TotProdHrs >= 6) {
                $attendance = Attendance::ATTENDANCE_P;
            }


            $updateData = [
                'AttnRemarks' => $input['AttnRemarks'] ?? '',
                'OutStamp' => $outStamp ?? '',
                'InStamp' => $inStamp ?? '',
                'Attendance' => $attendance ?? '',
                'TotProdHrs' => $TotProdHrs ?? ''
            ];

//            update production hour and total calculation ask for this question
            Attendance::where(['SR_NO' => $srNo])
                    ->update($updateData);

            return $this->sendResponse(true, route('viewModifyAttendance'), 'Attendance Successfully Updated.');
        }
        return $this->sendResponse(false, '', 'Record not found That try to edit data.');
    }

}
