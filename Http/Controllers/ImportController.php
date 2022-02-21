<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Session;
use SimpleXLSX;
use App\Model\Signup;
use App\Model\BatchNo;
use App\Helper\Common;
use App\Model\CharityIds;
use App\Model\CharityCode;
use App\Model\BCopyCalling;
use App\Model\Import\Import;
use App\Model\ProspectMaster;
use App\Model\SignupAccountChk;
use App\Model\BCopyCallingDetail;
use Illuminate\Support\Facades\Request;

/**
 * Employee Detail Get
 */

use App\Model\EmployeeEDesg;
use App\Model\EmployeeMaster;

class ImportController extends Controller {

    public $testXlsxFile = '';

    public function __construct() {
        parent::__construct();
        $this->testXlsxFile = public_path() . '/temp/test.xlsx';
        ini_set('max_execution_time', -1);
        set_time_limit(0);
    }

    public function importTableList() {
        $list = [
            $this->getTableName('IfscMaster') => [
                'title' => 'IFSC Code',
                'column' => ['bank', 'ifsc', 'branch', 'action'],
                'function' => 'infscCodeImport'
            ],
            $this->getTableName('PoList') => [
                'title' => 'Pin Code',
                'column' => ['pincode', 'city', 'state'],
                'function' => 'pinCodeImport'
            ],
            $this->getTableName('EmployeeTargetQaulity') => [
                'title' => 'Target And Quality',
                'column' => ['EID', 'target', 'workingDays', 'quality'],
                'function' => 'targetQualityImport'
            ],
            $this->getTableName('CharityIds') => [
                'title' => 'Chatity Ids',
                'column' => ['CharityCode', 'ModeOfDonation', 'Charity_ID'],
                'function' => 'charityIdsImport'
            ],
            $this->getTableName('BatchNo') => [
                'title' => 'Batch No',
                'column' => ['CharityCode', 'ModeOfDonation', 'batchNo'],
                'function' => 'batchNoImport'
            ],
            $this->getTableName('BCopyCalling') => [
                'title' => 'B-Copy Calling',
                'column' => ['title', 'FirstName', 'LastName', 'Gender', 'DateOfBirth', 'Mobile_1', 'Mobile_2', 'CompanyName', 'Address1', 'Address2', 'Address3', 'Address4', 'Postcode', 'City', 'State', 'Country', 'eMail_Address', 'LastDonationDate', 'LastDonationAmount', 'LastDonationFrequency', 'NoOfPayments', 'UniqueID', 'CallType', 'BCopyEID', 'isDelete'],
                'function' => 'bCopyCallingImport'
            ]
        ];
        return $list;
    }

    public function getTableName($modalName) {
        $getClass = "\App\Model\\" . $modalName;
        $class = new $getClass;
        $table = $class->getTable();
        return $table;
    }

    public function readXlsxFile($filePath = NULL) {
        if (!$filePath) {
            $filePath = $this->testXlsxFile;
        }

        $allData = [];
        if ($xlsx = SimpleXLSX::parse($filePath)) {
// Produce array keys from the array values of 1st array element
            $headerRow = $rows = [];
            foreach ($xlsx->rows() as $k => $r) {
                if ($k === 0) {
                    $headerRow = $r;
                    continue;
                }
                if (count($r) != count($headerRow)) {
                    $rKeyCount = 0;
                    while (count($headerRow) > $rKeyCount) {
                        if (!isset($r[$rKeyCount])) {
                            $r[$rKeyCount] = null;
                        }
                        $rKeyCount++;
                    }
                }
                $rows[] = array_combine($headerRow, $r);
//                $rows[] = $r;
            }
            $allData['headers'] = $headerRow;
            $allData['row'] = $rows;
        }
        return $allData;
    }

    public function importTableView() {
        $tableList = $this->importTableList();

        /** This if after import complete * */
        $getCompleteReport = '';
        if (Session::get('importComplete')) {
            $getCompleteReport = Session::get('importComplete');
            Session::forget('importComplete');
        }
        /** This if after import complete * */
        return view('import.importTableView', compact('tableList', 'getCompleteReport'));
    }

    public function getListOfEmployee() {
        $employeeDesg = [
            EmployeeEDesg::DESG_FR => 'FR',
            EmployeeEDesg::DESG_TL => 'TL',
            EmployeeEDesg::DESG_STL => 'STL',
            EmployeeEDesg::DESG_PM => 'PM'
        ];
        $wereDesg = array_keys($employeeDesg);

        /** Get List of All Employee * */
        $getResultData = EmployeeMaster::select('EID', 'EName', 'EDesg', 'ECity')
            ->whereIn('EDesg', $wereDesg)
            ->whereHas('getUserInfo')
            ->where('EStatus', '<>', EmployeeMaster::EStatusLeft)
            ->get()
            ->toArray();

        $headers = ['EID', 'EName', 'EDesg', 'ECity'];
        $resultData = [$headers, ...$getResultData];

        $fileName = uniqid() . '.csv';
        $file = fopen(public_path() . '/temp/' . $fileName, 'w');
        foreach ($resultData as $key => $row) {
            if ($key != 0) {
                $row['EDesg'] = $employeeDesg[$row['EDesg']];
                $getCity = \App\Model\EmployeeECity::find($row['ECity']);
                $row['ECity'] = $getCity->Ecity ?? '';
            }
            fputcsv($file, $row);
        }
        fclose($file);
        $fileUrl = url('/') . '/temp/' . $fileName;
        $data = [
            'fileUrl' => $fileUrl
        ];
        return $this->sendResponse(true, '', 'Employee File Downloaded', $data, 'getListOfEmployeeResult');
    }

    public function importTable(Request $request) {
        $input = request()->all();

        if (!isset($input['table']) || !isset($input['file'])) {
            Session::put('error', 'Input Data Not Valid.');
            return redirect()->route('importTableView');
        }

        if (!$this->checkModelExist($input['table'])) {
            Session::put('error', 'Invalid Table Input.');
            return redirect()->route('importTableView');
        }

        $filePath = $this->uploadImportFile($input);
        if (!$filePath) {
            return redirect()->route('importTableView');
        }

        $fileData = $this->readXlsxFile($filePath);

        if (!$this->checkColumnExcel($input['table'], $fileData)) {
            Session::put('error', 'Required Column Not Exist In Excel.');
            return redirect()->route('importTableView');
        }

        list($successReport, $failedReport) = $this->startDatabaseImport($input['table'], $fileData);

        Session::put('info', 'Upload Successful.');
        $importComplete = [
            'successReport' => $successReport, 'failedReport' => $failedReport
        ];

        Session::put('importComplete', $importComplete);
        return redirect()->route('importTableView');
    }

    public function checkModelExist($modelTableName) {
        $list = $this->importTableList();
        if (!isset($list[$modelTableName])) {
            return false;
        }
        return true;
    }

    public function uploadImportFile($input) {
        $file = $input['file'];
        $checkOrifinalExtenction = $file->getClientOriginalExtension();
        $filePath = '';
        if ($checkOrifinalExtenction != 'xlsx') {
            Session::put('error', 'File Format Not Valid');
            return $filePath;
        }
        try {
            $fileName = $file->getClientOriginalName();
            $newName = uniqid() . $this->clearString($fileName) . '.' . $checkOrifinalExtenction;
            $file->move(base_path() . '/public/temp/', $newName);
            $filePath = public_path() . '/temp/' . $newName;
        } catch (\Exception $ex) {
            Session::put('error', 'File upload operation failed');
            return $filePath;
        }
        return $filePath;
    }

    public function checkColumnExcel($table, $data) {
        $getImportList = $this->importTableList()[$table];
        $getRequiredColumn = $getImportList['column'];
//        $getHeaderColumns = array_map('strtolower', $data['headers']);
        $getHeaderColumns = $data['headers'];

        $checkColumnExcel = true;
        foreach ($getRequiredColumn as $column) {
            if (!in_array($column, $getHeaderColumns)) {
                $checkColumnExcel = false;
                break;
            }
        }

        return $checkColumnExcel;
    }

    public function startDatabaseImport($table, $data) {
        $getList = $this->importTableList()[$table];
        $getFunctinoName = $getList['function'];
        $importDetail = $this->$getFunctinoName($table, $data);

        $successRecord = count($importDetail['successRecord']) ? $importDetail['successRecord'] : '';
        $failedRecord = count($importDetail['failedRecord']) ? $importDetail['failedRecord'] : '';

        $successFile = Common::writeCsvFile($successRecord);
        $failedFile = Common::writeCsvFile($failedRecord);

        return [$successFile, $failedFile];
    }

    /*
     * ************************* Saperate Table Import ******************************
     */

    public function infscCodeImport($table, $data) {
        $successRecord = [];
        $failedRecord = [];
        $getImportList = $this->importTableList()[$table];
        $getRequiredColumn = $getImportList['column'];
        foreach ($data['row'] as $row) {
            try {
                /**
                 *
                 * Validate if data already exist then update
                 *
                 */
                $checkWhere = ['ifsc' => $row['ifsc']];
                $checkModel = new Import($table);
                $getData = $checkModel->where($checkWhere)->first();
                if ($row['action'] === 0) {
                    if ($getData) {
                        $checkModel->where($checkWhere)->delete();
                    }
                    continue;
                }
                /**
                 * create new Record
                 */
                if (!$getData) {
                    $importModel = new Import($table);
                    foreach ($getRequiredColumn as $val) {
                        if ($val != 'action') {
                            if ($val == 'ifsc') {
                                $importModel->$val = strtoupper($row[$val]);
                            } else {
                                $importModel->$val = $row[$val];
                            }
                        }
                    }
                    $importModel->save();
                } else {
                    $update = [];
                    foreach ($getRequiredColumn as $val) {
                        if ($val != 'action') {
                            if ($val == 'ifsc') {
                                $update[$val] = strtoupper($row[$val]);
                            } else {
                                $update[$val] = $row[$val];
                            }
                        }
                    }
                    $checkModel->where($checkWhere)->update($update);
                }
                $successRecord[] = $row;
            } catch (\Exception $ex) {
                $failedRecord[] = $row;
            }
        }

        return [
            'successRecord' => $successRecord,
            'failedRecord' => $failedRecord
        ];
    }

    public function pinCodeImport($table, $data) {
        $successRecord = [];
        $failedRecord = [];
        $getImportList = $this->importTableList()[$table];
        $getRequiredColumn = $getImportList['column'];

        foreach ($data['row'] as $row) {
            try {
                $checkWhere = ['pincode' => $row['pincode']];
                $checkModel = new Import($table);
                $getData = $checkModel->where($checkWhere)->first();
                /**
                 * create new Record
                 */
                if (!$getData) {
                    $importModel = new Import($table);
                    foreach ($getRequiredColumn as $val) {
                        $importModel->$val = $row[$val];
                    }
                    $importModel->save();
                } else {
                    $update = [];
                    foreach ($getRequiredColumn as $val) {
                        $update[$val] = $row[$val];
                    }
                    $checkModel->where($checkWhere)->update($update);
                }
                $successRecord[] = $row;
            } catch (\Exception $ex) {
                $failedRecord[] = $row;
            }
        }

        return [
            'successRecord' => $successRecord,
            'failedRecord' => $failedRecord
        ];
    }

    public function targetQualityImport($table, $data) {
        $successRecord = [];
        $failedRecord = [];
        $getImportList = $this->importTableList()[$table];
        $getRequiredColumn = $getImportList['column'];

        $dateM = date('m');
        $dateY = date('Y');
        $fullDate = date('Y-m-d H:i:s');
        foreach ($data['row'] as $row) {
            try {
                $checkWhere = ['EID' => $row['EID']];
                $checkModel = new Import($table);
                $getData = $checkModel->where($checkWhere)
                    ->whereMonth('date', $dateM)
                    ->whereYear('date', $dateY)
                    ->first();
                /**
                 * create new Record
                 */
                if (!$getData) {
                    $importModel = new Import($table);
                    foreach ($getRequiredColumn as $val) {
                        $importModel->$val = $row[$val];
                    }
                    $importModel->date = $fullDate;
                    $importModel->save();
                } else {
                    $update = [];
                    foreach ($getRequiredColumn as $val) {
                        if ($row[$val] != '') {
                            $update[$val] = $row[$val];
                        }
                    }
                    $checkModel->where($checkWhere)
                        ->whereMonth('date', $dateM)
                        ->whereYear('date', $dateY)
                        ->update($update);
                }
                $successRecord[] = $row;
            } catch (\Exception $ex) {
                $failedRecord[] = $row;
            }
        }

        return [
            'successRecord' => $successRecord,
            'failedRecord' => $failedRecord
        ];
    }

    public function charityIdsImport($table, $data) {
        $successRecord = [];
        $failedRecord = [];
        foreach ($data['row'] as $row) {
            $downloadInputData = $row;
            try {
                $charityCode = $row['CharityCode'] ?? null;
                $modeOfDonation = strtolower($row['ModeOfDonation']) ?? null;
                $charity_ID = $row['Charity_ID'] ?? null;
                if (!$charityCode || !$modeOfDonation || !$charity_ID) {
                    $row['Reason'] = 'Column value missing';
                    $failedRecord[] = $row;
                    continue;
                }

                /*
                 * Set mode of donation
                 */
                $getAllModeOfDonation = Signup::modeOfDonation();
                $getAllModeOfDonationArray = array_map(fn($e) => strtolower($e), $getAllModeOfDonation);
                if (!in_array($modeOfDonation, $getAllModeOfDonationArray)) {
                    $row['Reason'] = 'Invalid Mode Of Donation.';
                    $failedRecord[] = $row;
                    continue;
                }
                $row['ModeOfDonation'] = array_flip($getAllModeOfDonationArray)[$modeOfDonation] ?? null;
                /*
                 * Set charity code
                 */
                $getChatiryCode = CharityCode::where(['CharityCode' => $charityCode])->first();
                if (!$getChatiryCode) {
                    $row['Reason'] = 'Charity Code not available in system.';
                    $failedRecord[] = $row;
                    continue;
                }
                $row['CharityCode'] = $getChatiryCode->id ?? null;
                $checkValue = CharityIds::where(['Charity_ID' => $charity_ID])
                    ->first();
                if ($checkValue) {
                    $failedRecord[] = $downloadInputData;
                    continue;
                }
                $row['status'] = CharityIds::StatusUnUsed;
                CharityIds::create($row);
                $successRecord[] = $downloadInputData;
            } catch (\Exception $ex) {
                $failedRecord[] = $downloadInputData;
            }
        }

        return [
            'successRecord' => $successRecord,
            'failedRecord' => $failedRecord
        ];
    }

    public function batchNoImport($table, $data) {
        $successRecord = [];
        $failedRecord = [];
        foreach ($data['row'] as $row) {
            $downloadInputData = $row;
            try {
                $charityCode = $row['CharityCode'] ?? null;
                $modeOfDonation = strtolower($row['ModeOfDonation']) ?? null;
                $batchNo = $row['batchNo'] ?? null;
                if (!$charityCode || !$modeOfDonation || !$batchNo) {
                    $row['Reason'] = 'Column value missing';
                    $failedRecord[] = $row;
                    continue;
                }

                /*
                 * Set mode of donation
                 */
                $getAllModeOfDonation = Signup::modeOfDonation();
                $getAllModeOfDonationArray = array_map(fn($e) => strtolower($e), $getAllModeOfDonation);
                if (!in_array($modeOfDonation, $getAllModeOfDonationArray)) {
                    $row['Reason'] = 'Invalid Mode Of Donation.';
                    $failedRecord[] = $row;
                    continue;
                }
                $row['ModeOfDonation'] = array_flip($getAllModeOfDonationArray)[$modeOfDonation] ?? null;
                /*
                 * Set charity code
                 */
                $getChatiryCode = CharityCode::where(['CharityCode' => $charityCode])->first();
                if (!$getChatiryCode) {
                    $row['Reason'] = 'Charity Code not available in system.';
                    $failedRecord[] = $row;
                    continue;
                }
                $row['CharityCode'] = $getChatiryCode->id ?? null;

                $checkValue = BatchNo::where(['CharityCode' => $row['CharityCode'], 'ModeOfDonation' => $row['ModeOfDonation'], 'batchNo' => $batchNo])
                    ->first();
                if ($checkValue) {
                    $failedRecord[] = $downloadInputData;
                    continue;
                }
                $row['status'] = BatchNo::StatusUnUsed;
                BatchNo::create($row);
                $successRecord[] = $downloadInputData;
            } catch (\Exception $ex) {
                $failedRecord[] = $downloadInputData;
            }
        }

        return [
            'successRecord' => $successRecord,
            'failedRecord' => $failedRecord
        ];
    }

    public function bCopyCallingImport($table, $data) {
        $successRecord = [];
        $failedRecord = [];
        foreach ($data['row'] as $row) {
            $downloadInputData = $row;
            /**
             * Start Importing data
             */
            try {
                if ($row['isDelete'] == 'yes') {
                    if ($row['CRM_ID'] == '') {
                        $downloadInputData['Reason'] = 'To delete record crm id is required.';
                        $failedRecord[] = $downloadInputData;
                        continue;
                    }
                    BCopyCallingDetail::where(['CRM_ID' => $row['CRM_ID']])->delete();
                    BCopyCalling::where(['CRM_ID' => $row['CRM_ID']])->delete();
                    continue;
                }
                unset($row['isDelete']);

                if ($row['Mobile_1'] == '') {
                    $downloadInputData['Reason'] = 'Mobile Number Required.';
                    $failedRecord[] = $downloadInputData;
                    continue;
                }
                if ((strlen($row['Mobile_1']) != 10) || (!is_numeric($row['Mobile_1']))) {
                    $downloadInputData['Reason'] = 'Invalid Mobile Number.';
                    $failedRecord[] = $downloadInputData;
                    continue;
                }
                if ($row['CallType'] == '') {
                    $downloadInputData['Reason'] = 'Call Type Required.';
                    $failedRecord[] = $downloadInputData;
                    continue;
                }
                if ($row['BCopyEID'] == '') {
                    $downloadInputData['Reason'] = 'BCopyEID is required.';
                    $failedRecord[] = $downloadInputData;
                    continue;
                }
                /**
                 * Escape record if mobile number already exist
                 */
                $mobileNumberArray = [];
                if ($row['Mobile_1'] != '') {
                    $mobileNumberArray[] = $row['Mobile_1'];
                }
                if ($row['Mobile_2'] != '') {
                    $mobileNumberArray[] = $row['Mobile_2'];
                }

                /**
                 * Escape record if mobile number already exist
                 */
                $getAllTitleData = ProspectMaster::getAllTitle();
                $getAllTitle = array_flip($getAllTitleData);
                $row['title'] = $getAllTitle[$row['title']] ?? null;

                $getAllGenderData = ProspectMaster::getAllGender();
                $getAllGender = array_flip($getAllGenderData);
                $row['Gender'] = $getAllGender[$row['Gender']] ?? null;

                if (isset($row['DateOfBirth']) && !empty($row['DateOfBirth'])) {
                    $dob = Common::fixDateFormat($row['DateOfBirth'], 'Y-m-d', 'Y-m-d');
                    $row['DateOfBirth'] = $dob ?? null;
                } else {
                    $row['DateOfBirth'] = null;
                }
                if (isset($row['LastDonationDate']) && !empty($row['LastDonationDate'])) {
                    $lastDonationDate = Common::fixDateFormat($row['LastDonationDate'], 'Y-m-d', 'Y-m-d');
                    $row['LastDonationDate'] = $lastDonationDate ?? null;
                } else {
                    $row['LastDonationDate'] = null;
                }
                $getAllFrequency = SignupAccountChk::getAllFrequency();
                $frequencyArray = array_keys($getAllFrequency);
                if (!in_array($row['LastDonationFrequency'], $frequencyArray)) {
                    $row['LastDonationFrequency'] = null;
                }

                if (isset($row['CRM_ID']) && !empty($row['CRM_ID'])) {
                    $checkIfExist = BCopyCalling::where(['CRM_ID' => $row['CRM_ID']])->first();
                    if ($checkIfExist) {
                        BCopyCalling::where(['CRM_ID' => $row['CRM_ID']])->update($row);
                    } else {
                        if ($mobileNumberArray) {
                            $checkIfMobileExist = BCopyCalling::whereIn('Mobile_1', $mobileNumberArray)->first();
                            if ($checkIfMobileExist) {
                                $downloadInputData['Reason'] = 'Record already exist with given mobile number.';
                                $failedRecord[] = $downloadInputData;
                                continue;
                            }
                        }
                        BCopyCalling::create($row);
                    }
                } else {
                    if ($mobileNumberArray) {
                        $checkIfMobileExist = BCopyCalling::whereIn('Mobile_1', $mobileNumberArray)->first();
                        if ($checkIfMobileExist) {
                            $downloadInputData['Reason'] = 'Record already exist with given mobile number.';
                            $failedRecord[] = $downloadInputData;
                            continue;
                        }
                    }
                    if (isset($row['CRM_ID'])) {
                        unset($row['CRM_ID']);
                    }
                    BCopyCalling::create($row);
                }

                $successRecord[] = $downloadInputData;
            } catch (\Exception $ex) {
                $downloadInputData['Reason'] = $ex->getMessage();
                $failedRecord[] = $downloadInputData;
            }
        }

        return [
            'successRecord' => $successRecord,
            'failedRecord' => $failedRecord
        ];
    }

    public function billingReportImport() {
        $input = request()->all();
        $filePath = $this->uploadImportFile($input);
        if (!$filePath) {
            return redirect()->route('billingReportView');
        }
        $fileData = $this->readXlsxFile($filePath);

        $getRequiredColumn = ['CRM_ID', 'Charity_ID', 'invoice_no', 'invoice_date', 'claw_back'];
        $getHeaderColumns = $fileData['headers'];
        $checkColumnExcel = true;
        foreach ($getRequiredColumn as $column) {
            if (!in_array($column, $getHeaderColumns)) {
                $checkColumnExcel = false;
                break;
            }
        }

        if (!$checkColumnExcel) {
            Session::put('error', 'Required Column Not Exist In Excel.');
            return redirect()->route('billingReportView');
        }

        $successRecord = [];
        $failedRecord = [];

        foreach ($fileData['row'] as $row) {
            try {
                $charityId = $row['Charity_ID'] ?? null;
                $crmId = $row['CRM_ID'] ?? null;
                if (!$crmId && !$charityId) {
                    $row['reason'] = 'Charity Id or Crm Id must be present.';
                    $failedReport[] = $row;
                    continue;
                }

                $row['invoice_date'] = Common::fixDateFormat($row['invoice_date'], 'Y-m-d', 'Y-m-d');

                $getClawBack = Signup::getClawBack();
//                $getClawBack = array_keys($getClawBack);
                $setClowBack = null;
                $getFileClowBack = strtolower($row['claw_back']);
                if (in_array($getFileClowBack, $getClawBack) && $getFileClowBack != null) {
                    $getClowBackValue = array_flip($getClawBack);
                    $setClowBack = $getClowBackValue[$getFileClowBack];
                }
                $row['claw_back'] = $setClowBack;
//                $row['claw_back'] = in_array($row['claw_back'], $getClawBack) ? $row['claw_back'] : null;

                $getRecord = Signup::select('*',
                    DB::raw('BCopyDataEntry.RefCrmID as Ref_CRM_ID')
                )
                    ->leftJoin('BCopyDataEntry', 'Signup.CRM_ID', 'BCopyDataEntry.RefCrmID');
                if ($crmId) {
                    $getRecord = $getRecord->where(['CRM_ID' => $crmId]);
                } elseif ($charityId) {
                    $getRecord = $getRecord->whereHas('getSignupDataEntry', function ($q) use ($charityId) {
                        return $q->where(['Charity_ID' => $charityId]);
                    })->orWhereHas('getBCopyDataEntry', function ($q) use ($charityId) {
                        return $q->where(['Charity_ID' => $charityId]);
                    });
                    /*Also search from bCopyDataEntry -> use refCrmId - Or condition*/
                } else {
                    $row['reason'] = 'Record not available';
                    $failedReport[] = $row;
                }
                $getRecord = $getRecord->first();
                if (!$getRecord) {
                    $row['reason'] = 'Record not available';
                    $failedReport[] = $row;
                }
                $newCrmId = $getRecord->CRM_ID ? $getRecord->CRM_ID : $getRecord->Ref_CRM_ID;
                if (!$newCrmId) {
                    $row['reason'] = 'Record not available';
                    $failedReport[] = $row;
                }

                $updateRecord = [
                    'invoice_no' => $row['invoice_no'],
                    'invoice_date' => $row['invoice_date'],
                    'claw_back' => $row['claw_back']
                ];
                Signup::where(['CRM_ID' => $newCrmId])
                    ->update($updateRecord);
                $successRecord[] = $row;
            } catch (\Exception $ex) {
                $failedRecord[] = $row;
            }
        }


        $successRecord = count($successRecord) ? $successRecord : '';
        $failedRecord = count($failedRecord) ? $failedRecord : '';

        $successFile = Common::writeCsvFile($successRecord);
        $failedFile = Common::writeCsvFile($failedRecord);

        Session::put('info', 'Upload Successful.');
        $importComplete = [
            'successReport' => $successFile, 'failedReport' => $failedFile
        ];

        Session::put('importComplete', $importComplete);
        return redirect()->route('billingReportView');
    }

}
