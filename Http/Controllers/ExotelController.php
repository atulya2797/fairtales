<?php

namespace App\Http\Controllers;

use Session;
use App\Model\Signup;
use App\Helper\Common;
use App\Model\CharityCode;
use App\Model\BCopyCalling;
use App\Model\SiteSettings;
use App\Helper\ExotelHelper;
use App\Model\WlcmCallDetail;
use App\Model\BCopyCallingDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class ExotelController extends Controller {

    public function __construct() {
        parent::__construct();
    }

    public function outgoingCall(Request $request) {

        $ePhoneNo = $this->user->wlcmCallNumber ?? '';
        if (!$ePhoneNo) {
            return $this->sendResponse(false, '', 'From Number Not Available.', '');
        }
        $urlNumber = request()->route('number');
        if (!$urlNumber) {
            return $this->sendResponse(false, '', 'Please Enter Number.', '');
        }
        $flag = request()->input('flag');
        if (!in_array($flag, ExotelHelper::callFlagList())) {
            return $this->sendResponse(false, '', 'Invalid Flag.', '');
        }
        $model = new ExotelHelper($ePhoneNo, $urlNumber, null, $flag);
        $callResult = $model->outgoingCall();
        return $this->sendResponse(true, '', '', $callResult['response'], 'exotelCall');
    }

    public function getCallDetail($callSid) {
        $model = new ExotelHelper(null, null, $callSid);
        $callResult = $model->getCallDetail();

        file_put_contents(storage_path() . '/logs/log_' . date("Y-m-d") . '.log', json_encode($callResult) . PHP_EOL, FILE_APPEND);

        if ($callResult['status']) {
            $response = $callResult['response'];
            if (isset($response['RecordingUrl']) && $response['RecordingUrl']) {
                return $this->sendResponse(true, '', 'Returned File Url.', $response['RecordingUrl']);
            }
            return $this->sendResponse(false, '', 'Recording not available');
        }
        return $this->sendResponse(false, '', 'Detail not available.');
    }

    public function saveCallRecording($url, $fileName) {
        $getFileExtenction = explode('.', $url);
        $extenction = end($getFileExtenction);
        $getFileData = file_get_contents($url);
        $newFilePath = public_path() . '/temp/' . $fileName . '.' . $extenction;
        file_put_contents($newFilePath, $getFileData);
        return $fileName . '.' . $extenction;
    }

    public function successAndFailedCall($mobileNo, $crmId, $reason, $fileName = null) {
        return [
            'Mobile No' => $mobileNo,
            'CRM_ID' => $crmId,
            'Reason' => $reason,
            'FileName' => $fileName
        ];
    }

    public function getCallDetailByCrmIds() {
        ini_set('max_execution_time', '0'); // for unlimited time executation
        ini_set('memory_limit', '-1'); // for unlimited time executation
        $input = request()->all();
        if (!isset($input['IsDownload'])) {
            return $this->sendResponse(false, '', 'Please Select Record.');
        }
        $leadCall = ((isset($input['listType']) && $input['listType']) == 'leadCall') ? 1 : 0;
        $getCharityCode = CharityCode::all();
        $charityCodeArray = [];
        foreach ($getCharityCode as $val) {
            $charityCodeArray[$val->id] = $val->CharityCode;
        }
        /* Apply Date Range */
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d');
        if (isset($input['dateFrom']) && $input['dateFrom']) {
            $dateFrom = Common::fixDateFormat($input['dateFrom'], 'd-m-Y', 'Y-m-d');
        }
        if (isset($input['dateTo']) && $input['dateTo']) {
            $dateTo = Common::fixDateFormat($input['dateTo'], 'd-m-Y', 'Y-m-d');
        }
        /**
         * Update dateTo
         */
        $dateTo = $this->upateDateTo($dateTo);
        /* Apply Date Range */
        $successData = [];
        $failedData = [];
        $fileNameList = [];
        foreach ($input['IsDownload'] as $val) {
            if ($leadCall) {
                $getWelcomeCallDetail = BCopyCallingDetail::where(['CRM_ID' => $val])
                        ->whereBetween('Call_TimeStamp', [$dateFrom, $dateTo])
                        ->get();
                if ($getWelcomeCallDetail) {
                    $i = 1;
                    foreach ($getWelcomeCallDetail as $wcal) {
                        $attemp = $i;
                        $i++;
                        /**
                         * Get MobileNumber
                         */
                        $getBcopyCalling = BCopyCalling::where(['CRM_ID' => $val])->first();
                        $mobileNo = $getBcopyCalling ? $getBcopyCalling->Mobile_1 : null;
                        /**
                         * Get MobileNumber
                         */
                        $getCallDetail = $this->getCallDetail($wcal->Call_Recording);
                        if ($getCallDetail['status']) {
                            $wlcallCharityCode = $wcal->getSignup->CharityCode ?? '';
                            $charityCode = '';
                            if ($wlcallCharityCode) {
                                $charityCode = $charityCodeArray[$wcal->getSignup->CharityCode] ?? '';
                            }
                            $createfileName = $charityCode . '_' . $val . '_' . $mobileNo . '_' . $wcal->Call_TimeStamp . '_' . $attemp;
                            $fileName = str_replace(' ', '_', $createfileName);
                            $newFileName = $this->saveCallRecording($getCallDetail['data'], $fileName);
                            if ($newFileName) {
                                $fileNameList[] = $newFileName;
                                $successData[] = $this->successAndFailedCall($mobileNo, $val, 'File Saved.', $newFileName);
                            } else {
                                $failedData[] = $this->successAndFailedCall($mobileNo, $val, 'File Can\'t Create.', $newFileName);
                            }
                        } else {
                            $failedData[] = $this->successAndFailedCall($mobileNo, $val, 'Call Detail Not Available.' . $wcal->Call_TimeStamp, '');
                        }
                    }
                } else {
                    $failedData[] = $this->successAndFailedCall($mobileNo, $val, 'Call Not Attempt Yet.', '');
                }
            } else {
                $getWelcomeCallDetail = WlcmCallDetail::where(['CRM_ID' => $val])->get();
                if ($getWelcomeCallDetail) {
                    $i = 1;
                    foreach ($getWelcomeCallDetail as $wcal) {
                        $attemp = $i;
                        $i++;

                        /**
                         * Get MobileNumber
                         */
                        $getProspect = $wcal->getSignup->getProspect ?? null;
                        $mobileNo = $getProspect ? $getProspect->Mobile_1 : null;
                        /**
                         * Get MobileNumber
                         */
                        $getCallDetail = $this->getCallDetail($wcal->Call_Recording);
                        if ($getCallDetail['status']) {
                            $charityCode = $charityCodeArray[$wcal->getSignup->CharityCode] ?? '';
                            $createfileName = $charityCode . '_' . $val . '_' . $mobileNo . '_' . $wcal->Call_TimeStamp . '_' . $attemp;
                            $fileName = str_replace(' ', '_', $createfileName);
                            $newFileName = $this->saveCallRecording($getCallDetail['data'], $fileName);
                            if ($newFileName) {
                                $fileNameList[] = $newFileName;
                                $successData[] = $this->successAndFailedCall($mobileNo, $val, 'File Saved.', $newFileName);
                            } else {
                                $failedData[] = $this->successAndFailedCall($mobileNo, $val, 'File Can\'t Create.', $newFileName);
                            }
                        } else {
                            $failedData[] = $this->successAndFailedCall($mobileNo, $val, 'Call Detail Not Available.' . $wcal->Call_TimeStamp, '');
                        }
                    }
                } else {
                    $failedData[] = $this->successAndFailedCall($mobileNo, $val, 'Call Not Attempt Yet.', '');
                }
            }
        }

        $successReport = Common::writeCsvFile($successData);
        $failedReport = Common::writeCsvFile($failedData);
        $recordZip = $this->createRecordZipFile($fileNameList);
        /* If you want to run ajax call on this then add ajexForm in form and remove these content -> start */
//        Session::put('successReport', $successReport);
//        Session::put('failedReport', $failedReport);
//        Session::put('recordZip', $recordZip);
//        if (request()->isMethod('post')) {
//            return redirect()->route('downloadCallDetail');
//        }
        /* If you want to run ajax call on this then add ajexForm in form and remove these content -> end */
        $responseData = [
            'fileUrl' => [$successReport, $failedReport, $recordZip]
        ];
        return $this->sendResponse(true, '', 'File Get Successfully.', $responseData, 'exportDataTable');
    }

    public function createRecordZipFile($fileNames) {
        $fullPath = public_path() . '/temp/';
        $zipFile = 'Recordings_' . uniqid() . '.zip';
        $command = 'cd ' . $fullPath . '; zip -r ' . $fullPath . $zipFile;
        $compress = false;
        foreach ($fileNames as $val) {
            if ($val && file_exists($fullPath . $val)) {
                $compress = true;
                $command .= ' ' . $val . ' ';
            }
        }
        if ($compress) {
            exec($command);
            return url('/') . '/temp/' . $zipFile;
        }
        return '';
    }

    public function downloadCallDetail() {
        ini_set('max_execution_time', '0'); // for unlimited time executation
        $tableData = null;
        $getCharityCode = CharityCode::all();
        $input = request()->all();
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d');
        $charityCode = null;
        $batchNo = $input['BatchNo'] ?? null;
        $leadCall = $input['leadCall'] ?? null;
        $listType = $leadCall ? 'leadCall' : 'default';
        $finalOutcome = $input['CallFinalStatus'] ?? null;
        $EidFilter = null;
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
        /**
         * Update dateTo
         */
        $dateTo = $this->upateDateTo($dateTo);
        if (isset($input['EidFilter']) && $input['EidFilter']) {
            $EidFilter = $input['EidFilter'];
        }
        if (request()->isMethod('post')) {
            if ($leadCall) {
                $tableData = BCopyCallingDetail::whereHas('getBCopyCalling', function($q) use ($finalOutcome) {
                            if ($finalOutcome) {
                                $q = $q->where(['CallFinalStatus' => $finalOutcome]);
                            }
                            return $q;
                        });
                if ($EidFilter) {
                    $tableData = $tableData->where('Call_Agent', 'LIKE', '%' . $EidFilter . '%');
                };
                if ($dateFrom && $dateTo) {
                    $tableData = $tableData->whereBetween('Call_TimeStamp', [$dateFrom, $dateTo]);
                }
                $tableData = $tableData->get();
            } else {
                $tableData = Signup::select(
                                DB::raw('WlcmCall_Detail.CRM_ID as CRM_ID'),
                                DB::raw('Signup.CRM_ID as SCRM_ID'),
                                DB::raw('WlcmCall_Detail.Call_TimeStamp as Call_TimeStamp')
                        )
                        ->leftJoin('WlcmCall_Detail', 'Signup.CRM_ID', 'WlcmCall_Detail.CRM_ID')
                        ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID');
                if ($dateFrom && $dateTo) {
                    $tableData = $tableData->whereBetween('Call_TimeStamp', [$dateFrom, $dateTo]);
                }
                if ($charityCode) {
                    $tableData = $tableData->where(['CharityCode' => $charityCode]);
                }
                if ($batchNo) {
                    $tableData = $tableData->where('Signup_DataEntry.BatchNo', 'LIKE', '%' . $batchNo . '%');
                }
                $tableData = $tableData
                        ->where('WlcmCall_Detail.Call_Recording', '!=', '')
                        ->where('WlcmCall_Detail.Call_Recording', '!=', null)
                        ->groupBy('WlcmCall_Detail.Call_Recording')
                        ->get();
            }
        }
        return view('export.downloadCallDetail', compact('tableData', 'getCharityCode', 'listType'));
    }

    public function siteSettings() {
        if (request()->isMethod('post')) {
            $input = request()->post('settings');
            foreach ($input as $key => $val) {
                $getSetting = SiteSettings::where(['name' => $key])->first();
                if ($getSetting) {
                    $getSetting->update(['value' => $val]);
                } else {
                    SiteSettings::create(['name' => $key, 'value' => $val]);
                }
            }
            return $this->sendResponse(true, null, 'Site Settings updated successfully');
        }
        $getNameList = SiteSettings::getNameList();
        $getAllSiteSettings = [];
        foreach ($getNameList as $key => $name) {
            $getSettingValue = SiteSettings::where(['name' => $name])->first();
            if ($getSettingValue) {
                $getAllSiteSettings[$name] = $getSettingValue->value;
            } else {
                $getAllSiteSettings[$name] = '';
            }
        }
        return view('siteSettings', compact('getAllSiteSettings'));
    }

}
