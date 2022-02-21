<?php

namespace App\Http\Controllers;

use App\Model\PoList;
use App\Model\Signup;
use App\Helper\Common;
use App\Model\Campaign;
use App\Model\CharityCode;
use App\Model\EmployeeEDesg;
use App\Model\EmployeeMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AjaxController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // space that we can use the repository from
    public function __construct() {
//        $this->middleware('auth');
    }

    /**
     * 
     * @param type $desg
     * @return types
     */
    public function getEmployeeListByDesg($desg) {
        $managerList = [EmployeeEDesg::DESG_TL => 'ETLID', EmployeeEDesg::DESG_PM => 'EPMID', EmployeeEDesg::DESG_CH => 'ECHID', EmployeeEDesg::DESG_RM => 'ERMID'];
        if (!array_key_exists($desg, $managerList)) {
            return $this->sendResponse(false, '', 'Invalid Data');
        }
        $list = EmployeeMaster::select(['EID', 'EName'])->whereHas('getUserInfo')->where(['EDesg' => $desg])->get();
        $data = [
            'fieldName' => $managerList[$desg],
            'list' => $list
        ];
        return $this->sendResponse(true, '', '', $data);
    }

    public function getCampaign() {
        $input = request()->all();
//        if (!isset($input['cityId'])) {
//            return $this->sendResponse(false, '', 'Invalid City Selected.');
//        }
//        if (!isset($input['charityCode'])) {
//            return $this->sendResponse(false, '', 'Invalid Process Selected.');
//        }
        $cityId = $input['cityId'];
        $charityCodeId = $input['charityCode'];
        $getCampaign = Campaign::where(['CharityCodeId' => $charityCodeId])->where(['CityId' => $cityId])->first();
        if (!$getCampaign) {
            return $this->sendResponse(false);
        }
        return $this->sendResponse(true, '', '', $getCampaign);
    }

    public function getPincodeDetail() {
        $input = request()->all();
        $getPinCodeDetail = null;
        if (isset($input['pincode'])) {
            $getPinCodeDetail = PoList::where(['pincode' => $input['pincode']])->first();
        }
        return $this->sendResponse(true, '', '', $getPinCodeDetail);
    }

    public function donorSelectAjaxList($accountCheck = null) {
        $getRouteName = Session::get('ListUrl');
        $getDonorList = $this->getDonorList($accountCheck);
        $getAllRemainDonor = $getDonorList['getAllRemainDonor'];
        return view('admin.donorListSection', compact('getAllRemainDonor', 'getRouteName'));
    }

    public function getBatchNoForCallDownload() {
        $input = request()->all();
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d');
        $charityCode = null;
        if (isset($input['BatchNo'])) {
            unset($input['BatchNo']);
        }
        if (isset($input['CharityCode']) && $input['CharityCode']) {
            $checkCharityCode = CharityCode::find($input['CharityCode']);
            $charityCode = $checkCharityCode ? $checkCharityCode->id : '';
        }
        if (isset($input['dateFrom']) && $input['dateFrom']) {
            $dateFrom = Common::fixDateFormat($input['dateFrom'], 'd-m-Y', 'Y-m-d');
        }
        if (isset($input['dateTo']) && $input['dateTo']) {
            $dateTo = Common::fixDateFormat($input['dateTo'], 'd-m-Y', 'Y-m-d');
        }
        $dateTo = $this->upateDateTo($dateTo);
        $getBatchNo = Signup::select(
                        DB::raw('Signup_DataEntry.BatchNo as BatchNo')
                )
                ->leftJoin('WlcmCall_Detail', 'Signup.CRM_ID', 'WlcmCall_Detail.CRM_ID')
                ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID');
        if ($dateFrom && $dateTo) {
            $getBatchNo = $getBatchNo->whereBetween('Call_TimeStamp', [$dateFrom, $dateTo]);
        }
        if ($charityCode) {
            $getBatchNo = $getBatchNo->where(['CharityCode' => $charityCode]);
        }
        $getBatchNo = $getBatchNo
                        ->where('Signup_DataEntry.BatchNo', '!=', '')
                        ->where('Signup_DataEntry.BatchNo', '!=', null)
                        ->groupBy('Signup_DataEntry.BatchNo')
                        ->get()->toArray();
        return $this->sendResponse(true, '', '', $getBatchNo);
    }

}
