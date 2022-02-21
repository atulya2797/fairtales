<?php

namespace App\Http\Controllers\Api;

use App\Model\Attendance;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class ApiController extends BaseController {

    use AuthorizesRequests,
        DispatchesJobs,
        ValidatesRequests;

    public $user;

    /**
     * 
     * @param type $status  This is required(true || false)
     * @param type $code    Error Code or success code
     * @param type $message This is message of code or any message with the response
     * @param type $data    If you want to send any return data with response
     */
    public function jsonResponse($status, $code = 200, $message = '', $data = []) {
        $responseData = [
            'status' => $status,
            'message' => $message,
            'isDayStart' => $this->checkDayStart(),
//            'code' => $code,
            'data' => ($data === []) ? (object) $data : $data
        ];
        $headers = [];
        $options = 0;
        return response()->json($responseData, $code, $headers, $options);
    }

    public function callAction($method, $parameters) {
        $authUser = auth()->user();
        if ($authUser) {
            $getEmplioyeeData = \App\Model\EmployeeMaster::where(['EID' => $authUser->EID])->first();
            if ($getEmplioyeeData) {
                $this->user = $getEmplioyeeData;
            } else {
                $this->user = NULL;
            }
        }
        return parent::callAction($method, $parameters);
    }

    public function checkDayStart() {
        /**
         * 
         * Check Today Attendance
         * 
         */
        if ($this->user) {
            $getAttendance = Attendance::where(['EID' => $this->user->EID])
                    ->whereDate('InStamp', date('Y-m-d'))
                    ->first();
            if (!$getAttendance) {
                return false;
            }
            return true;
        }
        return false;
    }

}
