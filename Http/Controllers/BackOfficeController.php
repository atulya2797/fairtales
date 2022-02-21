<?php

namespace App\Http\Controllers;

use Session;
use SimpleXLSX;
use App\Model\Signup;
use App\Helper\Common;
use App\Model\BatchNo;
use App\Model\Campaign;
use App\Model\CharityIds;
use App\Model\CharityCode;
use App\Model\BCopyCalling;
use App\Model\SignupFormChk;
use App\Model\EmployeeECity;
use App\Model\EmployeeEDesg;
use App\Model\WlcmCallDetail;
use App\Model\SignupWlcmCall;
use App\Model\EmployeeMaster;
use App\Model\ProspectMaster;
use App\Model\BCopyDataEntry;
use App\Model\SignupDataEntry;
use App\Model\EmployeeEAccess;
use App\Model\SignupAccountChk;
use App\Model\ClientDataExport;
use App\Model\BCopyCallingDetail;
use App\Model\BankingEnrolStatus;
use App\Model\BankingDebitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Requests\BCopySearchEdit;
use App\Http\Requests\BCopyCallingForm;
use App\Http\Requests\CreateExportQuery;
use App\Http\Requests\GetClientExportDataReport;

class BackOfficeController extends Controller {

    public function __construct() {
        parent::__construct();
    }

    public function bCopyView($id = null) {
        $selectedBCopy = null;
        if ($id) {
            $selectedBCopy = BCopyDataEntry::find($id);
            if (!$selectedBCopy) {
                abort(404);
            }
        }
        $allList = BCopyDataEntry::where('RefCrmID', '<>', '')
            ->where('RefCrmID', '<>', null)
            ->where(['dataEntryStatus' => null])
            ->get();
        return view('backOffice.bCopyView', compact('selectedBCopy', 'allList'));
    }

    public function bCopyAccept($id) {
        $input = request()->all();
        $input['DateOfBirth'] = $input['DateOfBirth'] ? Common::fixDateFormat($input['DateOfBirth'], 'd-m-Y', 'Y-m-d') : null;
        $input['PledgeStartDate'] = $input['PledgeStartDate'] ? Common::fixDateFormat($input['PledgeStartDate'], 'd-m-Y', 'Y-m-d') : null;
        $input['PledgeEndDate'] = $input['PledgeEndDate'] ? Common::fixDateFormat($input['PledgeEndDate'], 'd-m-Y', 'Y-m-d') : null;
        $input['dataEntryStatus'] = SignupDataEntry::dataEntryStatus_Accept;
        $getBCopyData = BCopyDataEntry::find($id);
        $getBCopyData->update($input);
        return $this->sendResponse(true, route('bCopyView'), 'Date Update Successfully');
    }

    public function bCopyReject($id) {
        $input = request()->all();
        $input['DateOfBirth'] = $input['DateOfBirth'] ? Common::fixDateFormat($input['DateOfBirth'], 'd-m-Y', 'Y-m-d') : null;
        $input['dataEntryStatus'] = SignupDataEntry::dataEntryStatus_Reject;
        $getBCopyData = BCopyDataEntry::find($id);
        $getBCopyData->update($input);
        return $this->sendResponse(true, route('bCopyView'), 'Date Update Successfully');
    }

//    BoSearchView $request
    public function bCopySearchView() {
        $searchData = [];
        $input = request()->all();
        $dateOfSignup = isset($input['dateOfSignup']) ? Common::fixDateFormat($input['dateOfSignup'], 'd-m-Y', 'Y-m-d') : null;
        $crmName = $input['crmName'] ?? null;
        $accNo = $input['AccNo'] ?? null;
        $mobile = $input['mobile'] ?? null;
        if ($dateOfSignup || $crmName || $accNo || $mobile) {
            $searchData = Signup::select(
                'Signup.CRM_ID as CRM_ID', 'Signup.EID as EID',
                'Signup.PDate as PDate',
                'Prospect_Master.FullName as FullName',
                'Prospect_Master.Mobile_1 as Mobile_1',
                'Prospect_Master.Mobile_2 as Mobile_2',
                'Signup_AccountChk.AccountNo as AccountNo'
            )
                ->leftJoin('Signup_AccountChk', 'Signup.CRM_ID', 'Signup_AccountChk.CRM_ID')
                ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                ->leftJoin('Prospect_Master', 'Signup.CRM_ID', 'Prospect_Master.CRM_ID');
            if ($dateOfSignup) {
                $searchData = $searchData->where(['Signup.PDate' => $dateOfSignup]);
            }
            if ($crmName) {
                $searchData = $searchData->where(fn($q) => $q->where('Prospect_Master.FullName', 'LIKE', '%' . $crmName . '%')
                    ->orWhere('Signup.CRM_ID', 'LIKE', '%' . $crmName . '%'));
            }
            if ($accNo) {
                $searchData = $searchData->where('Signup_AccountChk.AccountNo', 'LIKE', '%' . $accNo . '%');
            }
            if ($mobile) {
                $searchData = $searchData->where(fn($q) => $q->where('Prospect_Master.Mobile_1', 'LIKE', '%' . $mobile . '%')
                    ->orWhere('Prospect_Master.Mobile_2', 'LIKE', '%' . $mobile . '%'));
            }
            $searchData = $searchData->paginate($this->pageSize);
        }
        return view('backOffice.bCopySearchView', compact('searchData'));
    }

    public function bCopySearchEdit(BCopySearchEdit $request, $crmId) {
        $data = Signup::where(['CRM_ID' => $crmId])->first();
        if (!$data) {
            abort(404);
        }
        if (request()->isMethod('post')) {
            $this->bCopySearchEditData();
            return $this->sendResponse(true, '', 'Update Successfull.');
        }
        return view('backOffice.bCopySearchEdit', compact('data'));
    }

    public function bCopySearchEditData() {
        $input = request()->all();
        $input['BCopySub'] = (isset($input['BCopySub']) && ($input['BCopySub'] = 'on')) ? 0 : 1;
        $crmId = request()->route('crmId');

        $input['Call_FinalStatus_WlcmCall'] ?? null;
        $input['BOStatUpdate'] ?? null;
        $input['FFPStatus'] ?? null;
        $input['dataEntryStatus'] ?? null;

        $wlcmCallDetailData = [];
        if (isset($input['CallOutcome'])) {
            foreach ($input['CallOutcome'] as $key => $val) {
                $wlcmCallDetailData[] = [
                    'CallOutcome' => $val,
                    'Call_FinalStatus' => $input['Call_FinalStatus'][$key],
                    'Call_FinalStatusRemark' => $input['Call_FinalStatusRemark'][$key]
                ];
            }
            unset($input['CallOutcome']);
        }
        unset($input['Call_FinalStatus']);
        unset($input['Call_FinalStatusRemark']);
        $input['FormReceiveDate'] = $input['FormReceiveDate'] ? Common::fixDateFormat($input['FormReceiveDate'], 'd-m-Y', 'Y-m-d') : null;
        $input['PledgeStartDate'] = $input['PledgeStartDate'] ? Common::fixDateFormat($input['PledgeStartDate'], 'd-m-Y', 'Y-m-d') : null;
        $input['PledgeEndDate'] = $input['PledgeEndDate'] ? Common::fixDateFormat($input['PledgeEndDate'], 'd-m-Y', 'Y-m-d') : null;
        $input['DateOfBirth'] = $input['DateOfBirth'] ? Common::fixDateFormat($input['DateOfBirth'], 'd-m-Y', 'Y-m-d') : null;

        Signup::where(['CRM_ID' => $crmId])->update(['ModeOfDonation' => $input['ModeOfDonation']]);

        $getWlcmCallDetail = WlcmCallDetail::where(['CRM_ID' => $crmId])->get();
        foreach ($getWlcmCallDetail as $key => $val) {
            if (isset($wlcmCallDetailData[$key])) {
                $getWlcmCall = WlcmCallDetail::where(['id' => $val->id])->first();
                $getWlcmCall->update($wlcmCallDetailData[$key]);
            }
        }
        $getSignupWlcmCall = SignupWlcmCall::where(['CRM_ID' => $crmId])->first();
        if ($getSignupWlcmCall) {
            $getSignupWlcmCall->update(['Call_FinalStatus' => $input['Call_FinalStatus_WlcmCall']]);
        }
        $getAccSignup = SignupAccountChk::where(['CRM_ID' => $crmId])->first();
        if ($getAccSignup) {
            $getAccSignup->update($input);
        }
        $getSignupFrmChk = SignupFormChk::where(['CRM_ID' => $crmId])->first();
        if ($getSignupFrmChk) {
            $getSignupFrmChk->update($input);
        }
        $getSignupDataEntry = SignupDataEntry::where(['CRM_ID' => $crmId])->first();
        if ($getSignupDataEntry) {
            $getSignupDataEntry->update($input);
        }
    }

    public function bCopyCallingView($id = null) {
        $selectedBCopy = null;
        $searchWord = request()->get('search');
        if ($id) {
            $selectedBCopy = BCopyCalling::where(function ($q) {
                return $q->where(function ($t) {
                    return $t->where('CallBackDateTime', '<=', date('Y-m-d H:i:s'));
                })
                    ->orWhere('CallBackDateTime', null);
            })
                ->where(function ($q) {
                    return $q->where(['CallFinalStatus' => BCopyCalling::CallFinalStatus_Pending])
                        ->orWhere(['CallFinalStatus' => null]);
                })
                ->where(['BCopyEID' => $this->user->EID])
                ->where(['CRM_ID' => $id])
                ->first();
            if (!$selectedBCopy) {
                abort(404);
            }
        }
        $allList = BCopyCalling::where(function ($q) {
            return $q->where(function ($t) {
                return $t->where('CallBackDateTime', '<=', date('Y-m-d H:i:s'));
            })
                ->orWhere('CallBackDateTime', null);
        })
            ->where(function ($q) {
                return $q->where(['CallFinalStatus' => BCopyCalling::CallFinalStatus_Pending])
                    ->orWhere(['CallFinalStatus' => null]);
            })
            ->where(['BCopyEID' => $this->user->EID]);
        if ($searchWord) {
            $allList = $allList->where(function ($q) use ($searchWord) {
                return $q->where('CRM_ID', 'LIKE', '%' . $searchWord . '%')
                    ->orWhere('FirstName', 'LIKE', '%' . $searchWord . '%')
                    ->orWhere('LastName', 'LIKE', '%' . $searchWord . '%')
                    ->orWhere('created_at', 'LIKE', '%' . $searchWord . '%');
            });
        }
        $allList = $allList->orderBy('CallBackDateTime', 'desc')
            ->orderBy('updated_at', 'asc')
            ->paginate(5);
        return view('backOffice.bCopyCallingView', compact('selectedBCopy', 'allList'));
    }

    public function bCopyCallingInputs() {
        $inputs = request()->all();

        $inputs['DateOfBirth'] = $inputs['DateOfBirth'] ?? null;
        if ($inputs['DateOfBirth']) {
            $inputs['DateOfBirth'] = Common::fixDateFormat($inputs['DateOfBirth'], 'd-m-Y', 'Y-m-d');
        }
        $inputs['LastDonationDate'] = $inputs['LastDonationDate'] ?? null;
        if ($inputs['LastDonationDate']) {
            $inputs['LastDonationDate'] = Common::fixDateFormat($inputs['LastDonationDate'], 'd-m-Y', 'Y-m-d');
        }
        return $inputs;
    }

    public function bCopyCallingComplete(BCopyCallingForm $request, $id) {

        $selectedBCopy = BCopyCalling::where(['CRM_ID' => $id])->first();
        if (!$selectedBCopy) {
            abort(404);
        }
        $input = $this->bCopyCallingInputs();
        unset($input['page']);
        if (!$input['transactionId']) {
            return $this->sendResponse(false, '', 'Transaction Id is required.');
        }

        $input['CallFinalStatus'] = BCopyCalling::CallFinalStatus_Complete;
        $input['CallAttemps'] = $selectedBCopy->CallAttemps ? $selectedBCopy->CallAttemps + 1 : 1;

        if ($input['CallBackDateTime'] == '') {
            $tomorrow = new \DateTime('tomorrow');
            $tomorrowDate = $tomorrow->format('Y-m-d');
            $input['CallBackDateTime'] = $tomorrowDate . ' 09:00:00';
        } else {
            $input['CallBackDateTime'] = Common::fixDateFormat($input['CallBackDateTime'], 'd-m-Y H:i:s', 'Y-m-d H:i:s');
        }

        $recordingUrl = $input['Call_Recording'] ?? null;
        unset($input['Call_Recording']);
        BCopyCalling::where(['CRM_ID' => $id])->update($input);
        /**
         *
         * @param BCopyCallingForm $request
         * @param type $idSave Call Detail
         */
        $callDetail = [
            'CRM_ID' => $id,
            'Call_Agent' => $this->user->EID,
            'Call_TimeStamp' => date('Y-m-d H:i:s'),
            'Call_Recording' => $recordingUrl,
            'callFinalOutcome' => $input['callFinalOutcome'],
            'CallFinalStatus' => $input['CallFinalStatus'],
            'Remarks' => $input['Remarks']
        ];
        BCopyCallingDetail::create($callDetail);

        /**
         * Redirect to same page
         */
        $page = request()->input('page');
        $redirectRoute = route('bCopyCallingView');
        if ($page) {
            $redirectRoute = route('bCopyCallingView') . '?page=' . $page;
        }

        return $this->sendResponse(true, $redirectRoute, 'Status Mark as Completed.');
    }

    public function bCopyCallingPending(BCopyCallingForm $request, $id) {

        $selectedBCopy = BCopyCalling::where(['CRM_ID' => $id])->first();
        if (!$selectedBCopy) {
            abort(404);
        }

        $input = $this->bCopyCallingInputs();
        unset($input['page']);
        $input['CallFinalStatus'] = BCopyCalling::CallFinalStatus_Pending;
        $input['CallAttemps'] = $selectedBCopy->CallAttemps ? $selectedBCopy->CallAttemps + 1 : 1;

        if ($input['CallBackDateTime'] == '') {
            $tomorrow = new \DateTime('tomorrow');
            $tomorrowDate = $tomorrow->format('Y-m-d');
            $input['CallBackDateTime'] = $tomorrowDate . ' 09:00:00';
        } else {
            $input['CallBackDateTime'] = Common::fixDateFormat($input['CallBackDateTime'], 'd-m-Y H:i:s', 'Y-m-d H:i:s');
        }

        $recordingUrl = $input['Call_Recording'] ?? null;
        unset($input['Call_Recording']);
        BCopyCalling::where(['CRM_ID' => $id])->update($input);
        /**
         *
         * @param BCopyCallingForm $request
         * @param type $idSave Call Detail
         */
        $callDetail = [
            'CRM_ID' => $id,
            'Call_Agent' => $this->user->EID,
            'Call_TimeStamp' => date('Y-m-d H:i:s'),
            'Call_Recording' => $recordingUrl,
            'callFinalOutcome' => $input['callFinalOutcome'],
            'CallFinalStatus' => $input['CallFinalStatus'],
            'Remarks' => $input['Remarks']
        ];
        BCopyCallingDetail::create($callDetail);

        /**
         * Redirect to same page
         */
        $page = request()->input('page');
        $redirectRoute = route('bCopyCallingView');
        if ($page) {
            $redirectRoute = route('bCopyCallingView') . '?page=' . $page;
        }

        return $this->sendResponse(true, $redirectRoute, 'Status Mark as Pending.');
    }

    public function bCopyCallingDenied(BCopyCallingForm $request, $id) {
        $selectedBCopy = BCopyCalling::where(['CRM_ID' => $id])->first();
        if (!$selectedBCopy) {
            abort(404);
        }

        $input = $this->bCopyCallingInputs();
        unset($input['page']);
        $input['CallFinalStatus'] = BCopyCalling::CallFinalStatus_Denied;
        $input['CallAttemps'] = $selectedBCopy->CallAttemps ? $selectedBCopy->CallAttemps + 1 : 1;

        if ($input['CallBackDateTime'] == '') {
            $tomorrow = new \DateTime('tomorrow');
            $tomorrowDate = $tomorrow->format('Y-m-d');
            $input['CallBackDateTime'] = $tomorrowDate . ' 09:00:00';
        } else {
            $input['CallBackDateTime'] = Common::fixDateFormat($input['CallBackDateTime'], 'd-m-Y H:i:s', 'Y-m-d H:i:s');
        }

        $recordingUrl = $input['Call_Recording'] ?? null;
        unset($input['Call_Recording']);
        BCopyCalling::where(['CRM_ID' => $id])->update($input);
        /**
         *
         * @param BCopyCallingForm $request
         * @param type $idSave Call Detail
         */
        $callDetail = [
            'CRM_ID' => $id,
            'Call_Agent' => $this->user->EID,
            'Call_TimeStamp' => date('Y-m-d H:i:s'),
            'Call_Recording' => $recordingUrl,
            'callFinalOutcome' => $input['callFinalOutcome'],
            'CallFinalStatus' => $input['CallFinalStatus'],
            'Remarks' => $input['Remarks']
        ];
        BCopyCallingDetail::create($callDetail);

        /**
         * Redirect to same page
         */
        $page = request()->input('page');
        $redirectRoute = route('bCopyCallingView');
        if ($page) {
            $redirectRoute = route('bCopyCallingView') . '?page=' . $page;
        }

        return $this->sendResponse(true, $redirectRoute, 'Status Mark as Denied.');
    }

    /**
     *
     * Create Export Query View Data
     */
    public function createExportQueryView() {
//        $tables = array_map('reset', \DB::select('SHOW TABLES'));
//        $removeTables = ['failed_jobs', 'migrations', 'password_resets', 'users'];
//        $mainTables = array_diff($tables, $removeTables);
        $tableData = [];
        $mainTables = $this->getTableList();

        foreach ($mainTables as $key => $val) {
            $tableData[] = $this->getTableDetail($key);
        }
        return view('backOffice.createExportQueryView', compact('tableData'));
    }

    public function updateExportQueryView($id) {
        $getQuery = ClientDataExport::find($id);
        if (!$getQuery) {
            return redirect()->route('clientExportQueryList');
        }
        $getQueryData = [];
        if ($getQuery) {
            $getQueryData = json_decode($getQuery->data, true);
        }
        $tableData = [];
        $mainTables = $this->getTableList();

        foreach ($mainTables as $key => $val) {
            $tableData[] = $this->getTableDetail($key);
        }
        return view('backOffice.updateExportQueryView', compact('tableData', 'getQueryData'));
    }

    public function getTableList() {
        $list = [
            'Signup' => [
                'title' => 'Signup',
                'rmColumn' => [],
                'relatedColumn' => ['RecordType', 'CharityCode', 'MethodOfFundraising', 'LocType', 'ModeOfDonation'],
                'relatedFunction' => 'signupConst'
            ],
            'Employee_Master' => [
                'title' => 'Employee Master',
                'rmColumn' => ['Epwd', 'remember_token', 'email_verified_at'],
                'relatedColumn' => ['Channel', 'CharityCode', 'CampaignCode', 'ECity', 'EDesg', 'TeamId', 'EAccess', 'EStatus', 'EType'],
                'relatedFunction' => 'employeeMasterConst'
            ],
            'Prospect_Master' => [
                'title' => 'Prospect Master',
                'rmColumn' => [],
                'relatedColumn' => ['RecordType', 'CharityCode', 'Channel', 'LocType', 'Title', 'Gender'],
                'relatedFunction' => 'prospectMasterConst'
            ],
            'Signup_DataEntry' => [
                'title' => 'Signup Data Entry',
                'rmColumn' => [],
                'relatedColumn' => ['Title', 'CampaignCode', 'dataEntryStatus', 'AccountType', 'DebitType', 'DoNotCall', 'Gender'],
                'relatedFunction' => 'signupDataEntryConst'
            ],
            'BCopyDataEntry' => [
                'title' => 'B-Copy Data Entry',
                'rmColumn' => [],
                'relatedColumn' => ['Title', 'CampaignCode', 'dataEntryStatus', 'AccountType', 'DebitType', 'DoNotCall', 'Gender'],
                'relatedFunction' => 'signupDataEntryConst'
            ],
            'Signup_AccountChk' => [
                'title' => 'Signup Account Check',
                'rmColumn' => [],
                'relatedColumn' => ['Frequency', 'BOStatUpdate'],
                'relatedFunction' => 'signupAccCheckConst'
            ],
            'Signup_WlcmCall' => [
                'title' => 'Signup Welcome Call',
                'rmColumn' => [],
                'relatedColumn' => ['Call_FinalStatus', 'SupLang', 'IsSupAwareCause', 'IsSupAwareMonthly', 'IsWillingAddrs', 'IsEnqCanx', 'IsSpotVer'],
                'relatedFunction' => 'signupWlcmCallConst'
            ],
            'WlcmCall_Detail' => [
                'title' => 'Welcome Call Detail',
                'rmColumn' => [],
                'relatedColumn' => ['CallOutcome'],
                'relatedFunction' => 'WlcmCallDetailConst'
            ],
        ];
        return $list;
    }

    public function getTableDetail($tbName) {
        $getTableList = $this->getTableList();
        $detail['tbColumn'] = $this->getTableColumn($tbName);
        $detail['tbName'] = $tbName;
        $detail['title'] = $getTableList[$tbName]['title'];
        return $detail;
    }

    public function getTableColumn($tbName) {
        $tbColumn = Schema::getColumnListing($tbName);
//        $removeColumns = ['created_at', 'updated_at'];
        $removeColumns = ['created_at', 'updated_at', ...$this->getTableList()[$tbName]['rmColumn']];
        return array_diff($tbColumn, $removeColumns);
    }

    /**
     *
     * Create Export Query Submit
     */
    public function createExportQuery(CreateExportQuery $request, $id = null) {
        $input = request()->all();
        /**
         * Save Query Data
         */
        $exportData = [
            'name' => $input['name'],
            'data' => json_encode($input),
        ];
        ClientDataExport::create($exportData);
        if ($id) {
            $getQuery = ClientDataExport::find($id);
            if ($getQuery->name == $input['name']) {
                $this->deleteClientDataQuery($id);
            }
        }
        return $this->sendResponse(true, route('createExportQueryView'), 'Query Has Been Saved.');
    }

    /**
     * clientExportData view
     */
    public function clientExportData() {
        $getAllQuery = $this->getCleintExportData();
        $charityCode = CharityCode::get();
        $modeOfDonation = [
            Signup::MODEOFDONATION_NACH => 'NACH',
            Signup::MODEOFDONATION_ONLINE => 'Online',
            Signup::MODEOFDONATION_CHEQUE => 'Cheque',
            Signup::MODEOFDONATION_ENACH => 'ENACH'
        ];
        return view('backOffice.clientExportData', compact('getAllQuery', 'charityCode', 'modeOfDonation'));
    }

    public function getCleintExportData() {
        return ClientDataExport::all();
    }

    public function clientExportQueryList() {
        $getAllQuery = $this->getCleintExportData();
        return view('backOffice.clientExportQueryList', compact('getAllQuery'));
    }

    public function deleteClientDataQuery($id) {
        $getQuery = ClientDataExport::find($id);
        if ($getQuery) {
            ClientDataExport::find($id)->delete();
        }
        $jsData = ['className' => 'removeTr_' . $id];
        return $this->sendResponse(true, '', 'Query Successfully Deleted.', $jsData, 'deleteClientDataQuery');
    }

    /**
     *
     * @param type $data Array Data
     */
    public function getClientExportDataReport(GetClientExportDataReport $request) {

        $input = request()->all();
        $checkQuery = ClientDataExport::find($input['exportId']);
        $clearName = Common::clearName($checkQuery->name);
        $data = json_decode($checkQuery->data, true);

        $getTbColumn = array_filter($data['dbTableColumn']);
        $selectData = array_unique($getTbColumn);
        $selectCol[] = DB::raw('Signup.CRM_ID as Signup_col_CRM_ID');
        $selectCol[] = DB::raw('BCopyDataEntry.id as BCopyDataEntry_col_id');
        $selectCol[] = DB::raw('BCopyDataEntry.RefCrmID as BCopyDataEntry_col_RefCrmID');
        foreach ($selectData as $key => $val) {
            $selectCol[] = DB::raw($val . ' as ' . str_replace('.', '_col_', $val));
        }

        /*
         * Create Query
         * PDate
         */
        $charityCode = $input['CharityCode'] ?? null;
        $modeOfDonation = null;
        $unverifyCallAttemps = $input['unverifyAttempt'] ?? null;
        $regenerate = (isset($input['regenerate']) && $input['regenerate'] == 'on') ? true : false;
        $isBCopy = (isset($input['isBCopy']) && $input['isBCopy'] == 'on') ? true : false;
        $todayDate = date('Y-m-d');

        if (isset($input['ModeOfDonation'])) {
            $modArray = [
                Signup::MODEOFDONATION_NACH,
                Signup::MODEOFDONATION_ONLINE,
                Signup::MODEOFDONATION_CHEQUE,
                Signup::MODEOFDONATION_ENACH
            ];
            $modeOfDonation = (in_array($input['ModeOfDonation'], $modArray)) ? $input['ModeOfDonation'] : null;
        }


        $input['dateFrom'] = $input['dateFrom'] ? Common::fixDateFormat($input['dateFrom'], 'd-m-Y', 'Y-m-d') : date('Y-m-d');
        $input['dateTo'] = $input['dateTo'] ? Common::fixDateFormat($input['dateTo'], 'd-m-Y', 'Y-m-d') : date('Y-m-d');
        $dateFrom = $input['dateFrom'] ?? null;
        $dateTo = $input['dateTo'] ?? null;
        if ($dateFrom > $dateTo) {
            return $this->sendResponse(false, '', 'Invalid Signup Date Difference.');
        }
        /**
         * Update dateTo
         */
        $dateTo = $this->upateDateTo($dateTo);
        $input['pledgeDateFrom'] = $input['pledgeDateFrom'] ? Common::fixDateFormat($input['pledgeDateFrom'], 'd-m-Y', 'Y-m-d') : null;
        $input['pledgeDateTo'] = $input['pledgeDateTo'] ? Common::fixDateFormat($input['pledgeDateTo'], 'd-m-Y', 'Y-m-d') : null;
        $pledgeDateFrom = $input['pledgeDateFrom'] ?? null;
        $pledgeDateTo = $input['pledgeDateTo'] ?? null;
        if ($pledgeDateFrom > $pledgeDateTo) {
            return $this->sendResponse(false, '', 'Invalid Pledge Date Difference.');
        }
        if ($unverifyCallAttemps) {
//            $selectCol[] = DB::raw('count(WlcmCall_Detail.CRM_ID) as unverifyCount');
            $selectCol[] = DB::raw('Signup_WlcmCall.Call_Attempt as unverifyCount');
        }
        $query = Signup::select($selectCol)
            ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
            ->leftJoin('Prospect_Master', 'Signup.CRM_ID', 'Prospect_Master.CRM_ID')
            ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
            ->leftJoin('Signup_AccountChk', 'Signup.CRM_ID', 'Signup_AccountChk.CRM_ID')
            ->leftJoin('Signup_WlcmCall', 'Signup.CRM_ID', 'Signup_WlcmCall.CRM_ID')
            ->leftJoin('WlcmCall_Detail', 'Signup.CRM_ID', 'WlcmCall_Detail.CRM_ID')
            ->leftJoin('Signup_FormChk', 'Signup.CRM_ID', 'Signup_FormChk.CRM_ID')
            ->leftJoin('BCopyDataEntry', 'Signup.CRM_ID', 'BCopyDataEntry.RefCrmID');
        if ($isBCopy) {
            $query = $query->where(['Signup_DataEntry.BCopyFlag' => SignupDataEntry::BCopyFlagUsed]);
        }
        if ($dateFrom && $dateTo) {
            $query = $query->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
        }
        if ($charityCode) {
            $query = $query->where(['Signup.CharityCode' => $charityCode]);
        }
        if ($modeOfDonation) {
            $query = $query->where(['Signup.ModeOfDonation' => $modeOfDonation]);
        }
        if ($pledgeDateFrom && $pledgeDateTo) {
            if ($isBCopy) {
                $query = $query->whereBetween('BCopyDataEntry.PledgeStartDate', [$pledgeDateFrom, $pledgeDateTo]);
            } else {
                $query = $query->whereBetween('Signup_DataEntry.PledgeStartDate', [$pledgeDateFrom, $pledgeDateTo]);
            }
        }
        if (!$regenerate) {
            if ($isBCopy) {
                $query = $query->where('BCopyDataEntry.exportDate', '=', null);
            } else {
                $query = $query->where('Signup_DataEntry.exportDate', '=', null);
            }
        } else {
            if ($isBCopy) {
                $query = $query->where('BCopyDataEntry.exportDate', '<>', null);
            } else {
                $query = $query->where('Signup_DataEntry.exportDate', '<>', null);
            }
        }

        /**
         * Default Condition with filter
         */
        if ($unverifyCallAttemps) {
            $query = $query->where(function ($q) use ($unverifyCallAttemps) {
                return $q->where(function ($hv) use ($unverifyCallAttemps) {
                    return $hv->where(['Signup_WlcmCall.Call_FinalStatus' => SignupWlcmCall::Call_FinalStatus_not_verified])
                        ->where('Signup_WlcmCall.Call_Attempt', '>=', $unverifyCallAttemps);
//                                            ->having('unverifyCount', '>=', $unverifyCallAttemps);
                })
                    ->orWhere(function ($qqq) {
                        return $qqq->whereIn('Signup_WlcmCall.Call_FinalStatus', [SignupWlcmCall::Call_FinalStatus_verified, SignupWlcmCall::Call_FinalStatus_process_unverified]);
                    });
            });
        } else {
            $query = $query->whereIn('Signup_WlcmCall.Call_FinalStatus', [SignupWlcmCall::Call_FinalStatus_verified, SignupWlcmCall::Call_FinalStatus_process_unverified, SignupWlcmCall::Call_FinalStatus_not_verified]);
        }

        /**
         * Default Condition
         */
        $query = $query->where(['Signup_AccountChk.BOStatUpdate' => SignupAccountChk::STATUS_ACCEPTED])
            ->whereIn('Signup_FormChk.FFPStatus', [SignupFormChk::FFPStatus_Accept, SignupFormChk::FFPStatus_Modify]);
        if ($isBCopy) {
            $query = $query->where(['BCopyDataEntry.dataEntryStatus' => SignupDataEntry::dataEntryStatus_Accept]);
        } else {
            $query = $query->where(['Signup_DataEntry.dataEntryStatus' => SignupDataEntry::dataEntryStatus_Accept]);
        }

        $query = $query->where('Signup_DataEntry.FormReceiveDate', '<>', null);

        $query = $query->groupBy('Signup.CRM_ID')
            ->orderBy('Signup.CRM_ID')
            ->get();
        if (!count($query)) {
            return $this->sendResponse(false, '', 'No Record Found.');
        }

        $getBatchNumber = BatchNo::where([
            'CharityCode' => $charityCode,
            'ModeOfDonation' => $modeOfDonation,
            'status' => BatchNo::StatusUnUsed
        ])->first();
        if (!$getBatchNumber) {
            return $this->sendResponse(false, '', 'Batch No. Not available in system.');
        }

        /*
         * End Create Query
         */

        $row = [];
        $error = false;
        $errorMessage = '';
        foreach ($query as $val) {
            $result = [];
            /**
             * Add batch no./charity_id/export date to signup date entry table
             */
            if (!$regenerate) {
                $getCharityId = CharityIds::where([
                    'CharityCode' => $charityCode,
                    'ModeOfDonation' => $modeOfDonation,
                    'status' => CharityIds::StatusUnUsed
                ])->first();
                if (!$getCharityId) {
                    $error = true;
                    $errorMessage = 'Charity Id is not available.';
                    break;
                }
                if ($isBCopy) {
                    /**
                     * Update data in new bcopydataentry table
                     */
                    $updateBatchAndCharityID = $this->updateBatchAndCharityIdBCopy($val->BCopyDataEntry_col_id, $val->BCopyDataEntry_col_RefCrmID, $todayDate, $getBatchNumber, $getCharityId);
                } else {
                    $updateBatchAndCharityID = $this->updateBatchAndCharityId($val->Signup_col_CRM_ID, $todayDate, $getBatchNumber, $getCharityId);
                }
                if (!$updateBatchAndCharityID) {
                    $error = true;
                    $errorMessage = 'Can\'t update B copy record and signup record.';
                    break;
                }
                $getSignupData = SignupDataEntry::where(['CRM_ID' => $val->Signup_col_CRM_ID])->first();
                $val->Signup_DataEntry_col_exportDate = $getSignupData->exportDate;
                $val->Signup_DataEntry_col_BatchNo = $getSignupData->BatchNo;
                $val->Signup_DataEntry_col_Charity_ID = $getSignupData->Charity_ID;
                $val->BCopyDataEntry_col_Charity_ID = $getCharityId->Charity_ID;
                $getCharityId->update(['status' => CharityIds::StatusUsed]);
            }
            foreach ($data['headerName'] as $key => $hval) {
                $defaultValue = $data['defaultValue'][$key];
                if ($defaultValue) {
                    $result[$hval] = $defaultValue;
                } else {
                    $getColName = $data['dbTableColumn'][$key];
                    /*
                     * Check if column is related to another table
                     */
                    if ($getColName) {
                        $colData = explode('.', $getColName);
                        $tblName = $colData[0];
                        $relColName = $colData[1];
                        $relatedTblArray = $this->getTableList()[$tblName]['relatedColumn'];
                        if (count($relatedTblArray) && in_array($relColName, $relatedTblArray)) {
                            $colName = str_replace('.', '_col_', $getColName);
                            $result[$hval] = $this->getRelatedTableConsVal($getColName, $val->$colName);
                        } else {
                            $colName = str_replace('.', '_col_', $getColName);
                            $result[$hval] = $val->$colName;
                        }
                    } else {
                        $result[$hval] = '';
                    }
                }
            }
            $row[] = $result;
        }
        if (!$regenerate) {
            $getBatchNumber->update(['status' => BatchNo::StatusUsed]);
        }
        if ($error) {
            return $this->sendResponse(false, '', $errorMessage);
        }

        $file = Common::writeXlsxFile($row, $clearName);
        $result = [
            'fileUrl' => $file
        ];

        return $this->sendResponse(true, '', 'File Exported Successfully.', $result, 'exportDataTable');
    }

    public function billingReportSubmit() {
        $input = request()->all();
        $queryId = $input['exportId'] ?? null;
        /*
         * Create Query
         * PDate
         */
        $charityCode = $input['CharityCode'] ?? null;
        $modeOfDonation = null;
        if (isset($input['ModeOfDonation'])) {
            $modArray = [
                Signup::MODEOFDONATION_NACH,
                Signup::MODEOFDONATION_ONLINE,
                Signup::MODEOFDONATION_CHEQUE,
                Signup::MODEOFDONATION_ENACH
            ];
            $modeOfDonation = (in_array($input['ModeOfDonation'], $modArray)) ? $input['ModeOfDonation'] : null;
        }
        $input['dateFrom'] = $input['dateFrom'] ? Common::fixDateFormat($input['dateFrom'], 'd-m-Y', 'Y-m-d') : date('Y-m-d');
        $input['dateTo'] = $input['dateTo'] ? Common::fixDateFormat($input['dateTo'], 'd-m-Y', 'Y-m-d') : date('Y-m-d');
        $dateFrom = $input['dateFrom'] ?? null;
        $dateTo = $input['dateTo'] ?? null;
        if ($dateFrom > $dateTo) {
            return $this->sendResponse(false, '', 'Invalid Signup Date Difference.');
        }
        if (!$queryId) {
            return $this->sendResponse(false, '', 'Please select Template.');
        }
        if (!$modeOfDonation) {
            return $this->sendResponse(false, '', 'Please select mode of donation.');
        }
        if (!$charityCode) {
            return $this->sendResponse(false, '', 'Please select Client.');
        }
        $dateTo = $this->upateDateTo($dateTo);
        $enrolStatus = $input['enrolStatus'] ?? null;
        $debitStatus = $input['debitStatus'] ?? null;
        /**
         * Update dateTo
         */
        $checkQuery = ClientDataExport::find($queryId);
        $clearName = Common::clearName($checkQuery->name);
        $data = json_decode($checkQuery->data, true);

        $getTbColumn = array_filter($data['dbTableColumn']);
        $selectData = array_unique($getTbColumn);
        $selectCol[] = DB::raw('Signup.CRM_ID as Signup_col_CRM_ID');
        $selectCol[] = DB::raw('BCopyDataEntry.id as BCopyDataEntry_col_id');
        $selectCol[] = DB::raw('BCopyDataEntry.RefCrmID as BCopyDataEntry_col_RefCrmID');
        foreach ($selectData as $key => $val) {
            $selectCol[] = DB::raw($val . ' as ' . str_replace('.', '_col_', $val));
        }


        $query = Signup::select($selectCol)
            ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
            ->leftJoin('Prospect_Master', 'Signup.CRM_ID', 'Prospect_Master.CRM_ID')
            ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
            ->leftJoin('Signup_AccountChk', 'Signup.CRM_ID', 'Signup_AccountChk.CRM_ID')
            ->leftJoin('Signup_WlcmCall', 'Signup.CRM_ID', 'Signup_WlcmCall.CRM_ID')
            ->leftJoin('WlcmCall_Detail', 'Signup.CRM_ID', 'WlcmCall_Detail.CRM_ID')
            ->leftJoin('Signup_FormChk', 'Signup.CRM_ID', 'Signup_FormChk.CRM_ID')
            ->leftJoin('BCopyDataEntry', 'Signup.CRM_ID', 'BCopyDataEntry.RefCrmID')
            ->leftJoin('Banking_Debit_Status', 'Signup.CRM_ID', 'Banking_Debit_Status.CRM_ID')
            ->leftJoin('Banking_Enrol_Status', 'Signup.CRM_ID', 'Banking_Enrol_Status.CRM_ID');

        if ($dateFrom && $dateTo) {
            $query = $query->whereBetween('Signup.PDate', [$dateFrom, $dateTo]);
        }
        if ($charityCode) {
            $query = $query->where(['Signup.CharityCode' => $charityCode]);
        }
        if ($enrolStatus != null) {
            $query = $query->where(['Banking_Enrol_Status.EnrolStatus' => $enrolStatus]);
        }
        if ($debitStatus != null) {
            $query = $query->where(['Banking_Debit_Status.DebitStatus' => $debitStatus]);
        }
        if ($modeOfDonation) {
            $query = $query->where(['Signup.ModeOfDonation' => $modeOfDonation]);
        }

        /**
         * Default Condition
         */
        $query = $query->where('Signup_DataEntry.exportDate', '!=', null)
            ->where('Signup.invoice_no', '=', null);
//                ->where(['Signup_AccountChk.BOStatUpdate' => SignupAccountChk::STATUS_ACCEPTED])
//                ->whereIn('Signup_FormChk.FFPStatus', [SignupFormChk::FFPStatus_Accept, SignupFormChk::FFPStatus_Modify])
//                ->where(['Signup_DataEntry.dataEntryStatus' => SignupDataEntry::dataEntryStatus_Accept]);

        $query = $query->groupBy('Signup.CRM_ID')
            ->orderBy('Signup.CRM_ID')
            ->get();
        if (!count($query)) {
            return $this->sendResponse(false, '', 'No Record Found.');
        }

        $row = [];
        $error = false;
        $errorMessage = '';
        foreach ($query as $val) {
            $result = [];
            /**
             * Add batch no./charity_id/export date to signup date entry table
             */
            foreach ($data['headerName'] as $key => $hval) {
                $defaultValue = $data['defaultValue'][$key];
                if ($defaultValue) {
                    $result[$hval] = $defaultValue;
                } else {
                    $getColName = $data['dbTableColumn'][$key];
                    /*
                     * Check if column is related to another table
                     */
                    if ($getColName) {
                        $colData = explode('.', $getColName);
                        $tblName = $colData[0];
                        $relColName = $colData[1];
                        $relatedTblArray = $this->getTableList()[$tblName]['relatedColumn'];
                        if (count($relatedTblArray) && in_array($relColName, $relatedTblArray)) {
                            $colName = str_replace('.', '_col_', $getColName);
                            $result[$hval] = $this->getRelatedTableConsVal($getColName, $val->$colName);
                        } else {
                            $colName = str_replace('.', '_col_', $getColName);
                            $result[$hval] = $val->$colName;
                        }
                    } else {
                        $result[$hval] = '';
                    }
                }
            }
            $row[] = $result;
        }

        $file = Common::writeXlsxFile($row, $clearName);
        $result = [
            'fileUrl' => $file
        ];

        return $this->sendResponse(true, '', 'File Exported Successfully.', $result, 'exportDataTable');
    }

    public function updateBatchAndCharityId($crmId, $todayDate, $getBatchNumber, $getCharityId) {
        $getSignupDataEntryRecord = SignupDataEntry::where(['CRM_ID' => $crmId])->first();
        $getSignupData = Signup::where(['CRM_ID' => $crmId])->first();
        $charityCode = $getSignupData->CharityCode ?? null;
        $mod = $getSignupData->ModeOfDonation ?? null;

        $getChatiryCode = CharityCode::find($charityCode);
        $chatiryCodeName = $getChatiryCode->CharityCode ?? '';
        $modName = Signup::modeOfDonation()[$mod] ?? '';

        $generateBatchNo = $chatiryCodeName . '_' . $modName . '_' . $getBatchNumber->batchNo;
        $dataEntryUpdateData = [
            'exportDate' => $todayDate,
            'BatchNo' => $generateBatchNo,
            'Charity_ID' => $getCharityId->Charity_ID
        ];

        if ($getSignupDataEntryRecord) {
            $getSignupDataEntryRecord->update($dataEntryUpdateData);
            return true;
        }
        return false;
    }

    public function updateBatchAndCharityIdBCopy($id, $refCrmId, $todayDate, $getBatchNumber, $getCharityId) {
        $getSignupDataEntryRecord = BCopyDataEntry::find($id);
        $getSignupData = Signup::where(['CRM_ID' => $refCrmId])->first();
        $charityCode = $getSignupData->CharityCode ?? null;
        $mod = $getSignupData->ModeOfDonation ?? null;

        $getChatiryCode = CharityCode::find($charityCode);
        $chatiryCodeName = $getChatiryCode->CharityCode ?? '';
        $modName = Signup::modeOfDonation()[$mod] ?? '';
        $generateBatchNo = $chatiryCodeName . '_' . $modName . '_' . $getBatchNumber->batchNo;
        $dataEntryUpdateData = [
            'exportDate' => $todayDate,
            'BatchNo' => $generateBatchNo,
            'Charity_ID' => $getCharityId->Charity_ID
        ];
        if ($getSignupDataEntryRecord) {
            $getSignupDataEntryRecord->update($dataEntryUpdateData);
            return true;
        }
        return false;
    }

    public function getRelatedTableConsVal($tblColName, $colVal) {
//        return 'default related val';
        $colData = explode('.', $tblColName);
        $tblName = $colData[0];
        $colName = $colData[1];
        $getFunctionName = $this->getTableList()[$tblName]['relatedFunction'];
        return $this->$getFunctionName($colName, $colVal);
    }

    /**
     *
     * All Constant Value Functions
     *
     * @param type $colName
     * @param type $colVal
     * @return string
     */
    public function signupConst($colName, $colVal) {
        if ($colName == 'RecordType') {
            if ($colVal == Signup::RECORDTYPE_PROSPECT) {
                return 'Prospect';
            }
            if ($colVal == Signup::RECORDTYPE_SUPPORTER) {
                return 'Donor';
            }
        }
        if ($colName == 'CharityCode') {
            $getCharityCode = CharityCode::find($colVal);
            return $getCharityCode->CharityCode ?? '';
        }
        if ($colName == 'MethodOfFundraising') {
            $arr = [
                Signup::METHODOFFUNDRAISING_F2F => 'F2F',
                Signup::METHODOFFUNDRAISING_D2D => 'D2D',
                Signup::METHODOFFUNDRAISING_TMA => 'TMA',
                Signup::METHODOFFUNDRAISING_TMR => 'TMR'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'LocType') {
            $arr = [
                Signup::LOCTYPE_PERMISSION => 'Permission',
                Signup::LOCTYPE_STREET => 'Street'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'ModeOfDonation') {
            $arr = [
                Signup::MODEOFDONATION_NACH => 'NACH',
                Signup::MODEOFDONATION_ONLINE => 'Online',
                Signup::MODEOFDONATION_CHEQUE => 'CHEQUE',
                Signup::MODEOFDONATION_ENACH => 'ENACH'
            ];
            return $arr[$colVal] ?? '';
        }
        return '';
    }

    public function employeeMasterConst($colName, $colVal) {
        if ($colName == 'Channel') {
            $arr = [
                EmployeeMaster::CHANNEL_F2F => 'F2F',
                EmployeeMaster::CHANNEL_D2D => 'D2D',
                EmployeeMaster::CHANNEL_TMA => 'TMA',
                EmployeeMaster::CHANNEL_TMR => 'TMR'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'CharityCode') {
            $getCharityCode = CharityCode::find($colVal);
            return $getCharityCode->CharityCode ?? '';
        }
        if ($colName == 'CampaignCode') {
            $getCampaign = Campaign::find($colVal);
            return $getCampaign->Campaign ?? '';
        }
        if ($colName == 'ECity') {
            $getValue = EmployeeECity::find($colVal);
            return $getValue->Ecity ?? '';
        }
        if ($colName == 'EDesg') {
            $getValue = EmployeeEDesg::find($colVal);
            return $getValue->EDesg ?? '';
        }
        if ($colName == 'EDesg') {
            $getValue = EmployeeEDesg::find($colVal);
            return $getValue->EDesg ?? '';
        }
        if ($colName == 'TeamId') {
            $getValue = Team::find($colVal);
            return $getValue->TeamName ?? '';
        }
        if ($colName == 'EAccess') {
            $getValue = EmployeeEAccess::find($colVal);
            return $getValue->EAccess ?? '';
        }
        if ($colName == 'EStatus') {
            $arr = [
                EmployeeMaster::EStatusConfirmed => 'Confirmed',
                EmployeeMaster::EStatusProbation => 'Probation',
                EmployeeMaster::EStatusExtendedProbation => 'Extended Probation',
                EmployeeMaster::EStatusLeft => 'Left'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'EType') {
            $arr = [
                EmployeeMaster::ETypePartTime => 'Part Time',
                EmployeeMaster::ETypeFullTime => 'Full Time'
            ];
            return $arr[$colVal] ?? '';
        }
        return '';
    }

    public function prospectMasterConst($colName, $colVal) {
        if ($colName == 'RecordType') {
            $arr = [
                ProspectMaster::RECORDTYPE_PROSPECT => 'Prospect',
                ProspectMaster::RECORDTYPE_SUPPORTER => 'Donor'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'CharityCode') {
            $getCharityCode = CharityCode::find($colVal);
            return $getCharityCode->CharityCode ?? '';
        }
        if ($colName == 'Channel') {
            $arr = [
                ProspectMaster::CHANNEL_F2F => 'F2F',
                ProspectMaster::CHANNEL_D2D => 'D2D',
                ProspectMaster::CHANNEL_TMA => 'TMA',
                ProspectMaster::CHANNEL_TMR => 'TMR'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'LocType') {
            $arr = [
                ProspectMaster::LOCTYPE_PERMISSION => 'Permission',
                ProspectMaster::LOCTYPE_STREET => 'Street'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'Title') {
            $arr = [
                ProspectMaster::TITLE_MR => 'Mr',
                ProspectMaster::TITLE_MISS => 'Miss',
                ProspectMaster::TITLE_MRS => 'Mrs',
                ProspectMaster::TITLE_DR => 'Dr'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'Gender') {
            $arr = [
                ProspectMaster::GENDER_MALE => 'Male',
                ProspectMaster::GENDER_FEMALE => 'Female',
                ProspectMaster::GENDER_OTHER => 'Other'
            ];
            return $arr[$colVal] ?? '';
        }

        return '';
    }

    public function signupDataEntryConst($colName, $colVal) {
        if ($colName == 'Title') {
            $arr = [
                ProspectMaster::TITLE_MR => 'Mr',
                ProspectMaster::TITLE_MISS => 'Miss',
                ProspectMaster::TITLE_MRS => 'Mrs',
                ProspectMaster::TITLE_DR => 'Dr'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'CampaignCode') {
            $getCampaign = Campaign::find($colVal);
            return $getCampaign->Campaign ?? '';
        }
        if ($colName == 'dataEntryStatus') {
            $arr = [
                SignupDataEntry::dataEntryStatus_Reject => 'Reject',
                SignupDataEntry::dataEntryStatus_Accept => 'Accept'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'AccountType') {
            $arr = [
                SignupDataEntry::AccountType_SB => 'SB',
                SignupDataEntry::AccountType_Ca => 'CA',
                SignupDataEntry::AccountType_Cc => 'CC',
                SignupDataEntry::AccountType_SB_Nre => 'SB_NRE',
                SignupDataEntry::AccountType_SB_Nro => 'SB_NRO',
                SignupDataEntry::AccountType_Other => 'Other'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'DebitType') {
            $arr = [
                SignupDataEntry::DebitType_FixedAmount => 'Fixed Amount',
                SignupDataEntry::DebitType_MaximumAmount => 'Maximum Acmount'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'DoNotCall') {
            $arr = [
                SignupDataEntry::DoNotCall_No => 'NO',
                SignupDataEntry::DoNotCall_Yes => 'Yes'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'Gender') {
            $arr = [
                ProspectMaster::GENDER_MALE => 'Male',
                ProspectMaster::GENDER_FEMALE => 'Female',
                ProspectMaster::GENDER_OTHER => 'Other'
            ];
            return $arr[$colVal] ?? '';
        }
        return '';
    }

    public function signupAccCheckConst($colName, $colVal) {
        if ($colName == 'Frequency') {
            $arr = [
                SignupAccountChk::Frequency_Monthly => 'Monthly',
                SignupAccountChk::Frequency_OneTime => 'One Time'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'BOStatUpdate') {
            $arr = [
                SignupAccountChk::STATUS_ACCEPTED => 'Accepted',
                SignupAccountChk::STATUS_RETRY => 'Retry',
                SignupAccountChk::STATUS_REJECTED => 'Rejected'
            ];
            return $arr[$colVal] ?? '';
        }
        return '';
    }

    public function signupWlcmCallConst($colName, $colVal) {
        if ($colName == 'Call_FinalStatus') {
            $arr = [
                SignupWlcmCall::Call_FinalStatus_verified => 'Verified',
                SignupWlcmCall::Call_FinalStatus_not_verified => 'Not Verified',
                SignupWlcmCall::Call_FinalStatus_rejected => 'Rejected',
                SignupWlcmCall::Call_FinalStatus_process_unverified => 'Process UnVerified'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'SupLang') {
            $arr = [
                SignupWlcmCall::SupLang_English => 'English',
                SignupWlcmCall::SupLang_Hingi => 'Hingi',
                SignupWlcmCall::SupLang_Tamil => 'Tabmil',
                SignupWlcmCall::SupLang_Bangla => 'Bangla',
                SignupWlcmCall::SupLang_Malayalam => 'Malayalam',
                SignupWlcmCall::SupLang_Kannada => 'Kannada',
                SignupWlcmCall::SupLang_Local => 'Local'
            ];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'IsSupAwareCause') {
            $arr = [0 => 'No', 1 => 'Yes'];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'IsSupAwareMonthly') {
            $arr = [0 => 'No', 1 => 'Yes'];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'IsWillingAddrs') {
            $arr = [0 => 'No', 1 => 'Yes'];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'IsEnqCanx') {
            $arr = [0 => 'No', 1 => 'Yes'];
            return $arr[$colVal] ?? '';
        }
        if ($colName == 'IsSpotVer') {
            $arr = [0 => 'No', 1 => 'Yes'];
            return $arr[$colVal] ?? '';
        }
        return '';
    }

    public function WlcmCallDetailConst($colName, $colVal) {
        if ($colName == 'CallOutcome') {
            $arr = SignupWlcmCall::getCallOutcome();
            return $arr[$colVal] ?? '';
        }
        return '';
    }

    /**
     *
     * Client Report Import start
     *
     */
    public function clientReportImportView() {
        $getTableData = $this->tableConfig();
        return view('backOffice.clientReportImportView', compact('getTableData'));
    }

    public function clientReportImport() {
        $input = request()->all();
        $getConfig = $this->tableConfig();
        $tableName = $input['table'] ?? '';
        $file = $input['file'] ?? '';
        $checkTable = $getConfig[$tableName] ?? null;
        if (!$checkTable) {
            Session::put('error', 'Invalid Table Name');
            return redirect()->route('clientReportImportView');
        }
        if (!$file) {
            Session::put('error', 'Please Select File');
            return redirect()->route('clientReportImportView');
        }
        $filePath = $this->uploadImportFile($input);
        if (!$filePath) {
            return redirect()->route('clientReportImportView');
        }
        $fileData = $this->readXlsxFile($filePath);

        if (!$this->checkColumnExcel($input['table'], $fileData)) {
            Session::put('error', 'Required Column Not Exist In Excel.');
            return redirect()->route('clientReportImportView');
        }

        list($successReport, $failedReport) = $this->startDatabaseImport($input['table'], $fileData);

        Session::put('info', 'Upload Successful.');
        $importComplete = [
            'successReport' => $successReport, 'failedReport' => $failedReport
        ];

        Session::put('importComplete', $importComplete);
        return redirect()->route('clientReportImportView');
    }

    public function readXlsxFile($filePath) {
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
            }
            $allData['headers'] = $headerRow;
            $allData['row'] = $rows;
        }
        return $allData;
    }

    public function tableConfig() {
        $config = [
            $this->getTableName('BankingEnrolStatus') => [
                'title' => 'Banking Enrol Status',
                'columns' => ['CRM_ID', 'Charity_ID', 'CharityCode', 'PledgeStatus', 'EnrolRefNum', 'InitialRejectReason', 'EnrolStatus', 'ReasonDesc', 'EnrolStatDate', 'Resub', 'Frequency'],
                'function' => 'bankingEnrolImport'
            ],
            $this->getTableName('BankingDebitStatus') => [
                'title' => 'Banking Debit Status',
                'columns' => ['CRM_ID', 'Charity_ID', 'CharityCode', 'DebitRefNum', 'Debit_Attempt', 'DebitStatus', 'DebitReasonDesc', 'DebitStatusDate'],
                'function' => 'bankingDebitImport'
            ],
        ];
        return $config;
    }

    public function getTableName($modalName) {
        $getClass = "\App\Model\\" . $modalName;
        $class = new $getClass;
        $table = $class->getTable();
        return $table;
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
        $getImportList = $this->tableConfig()[$table];
        $getRequiredColumn = $getImportList['columns'];
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
        $getList = $this->tableConfig()[$table];
        $getFunctinoName = $getList['function'];
        $importDetail = $this->$getFunctinoName($table, $data);

        $successRecord = count($importDetail['successRecord']) ? $importDetail['successRecord'] : '';
        $failedRecord = count($importDetail['failedRecord']) ? $importDetail['failedRecord'] : '';

        $successFile = Common::writeCsvFile($successRecord);
        $failedFile = Common::writeCsvFile($failedRecord);

        return [$successFile, $failedFile];
    }

    /**
     * Import Functions
     */
    public function bankingEnrolImport($table, $data) {
        $successRecord = [];
        $failedRecord = [];

        foreach ($data['row'] as $row) {
            $rowData = $row;
            try {
                $crmID = $row['CRM_ID'] ?? null;
                $charityID = $row['Charity_ID'] ?? null;
                $charityCode = $row['CharityCode'] ?? null;
                if ($charityCode) {
                    $getCharityCode = CharityCode::where(['CharityCode' => $charityCode])->first();
                    $charityCode = null;
                    if ($getCharityCode) {
                        $charityCode = $getCharityCode->id;
                    }
                }

                $enrolStatusDate = Common::fixDateFormat($row['EnrolStatDate'], 'Y-m-d', 'Y-m-d') ?? null;

                $getAllEnrolStatus = BankingEnrolStatus::getEnrolStatus();
                $allEnrolStatusVal = array_flip($getAllEnrolStatus);
                $enrolStatus = $allEnrolStatusVal[$row['EnrolStatus']] ?? null;

                $enrolReasonDesc = $row['ReasonDesc'] ?? null;
                $initialRejectReason = $row['InitialRejectReason'] ?? null;
                /**
                 * Check Condition
                 */
                if (!$crmID && !$charityID) {
                    $row['Reason'] = 'CRM Id or Charity Id is required';
                    $failedRecord[] = $row;
                    continue;
                }
                if (!$charityCode) {
                    $row['Reason'] = 'Charity Code is required';
                    $failedRecord[] = $row;
                    continue;
                }
                if (!$enrolStatusDate) {
                    $row['Reason'] = 'Date is required with format(Y-m-d) : 2020-12-31';
                    $failedRecord[] = $row;
                    continue;
                }
                if (($initialRejectReason == null) && ($enrolStatus === null)) {
                    $row['Reason'] = 'Initial Reject Reason or Enrol Status is required';
                    $failedRecord[] = $row;
                    continue;
                }
                if (($enrolStatus != null) && ($enrolStatus == BankingEnrolStatus::EnrolStatusFail) && !$enrolReasonDesc) {
                    $row['Reason'] = 'Reject Reason is required';
                    $failedRecord[] = $row;
                    continue;
                }
                /**
                 * Apply real value to submit in database
                 */
                $getAllPledgeStatus = BankingEnrolStatus::getPledgeStatus();
                $allPledgeVal = array_flip($getAllPledgeStatus);
                $getResub = BankingEnrolStatus::getResub();
                $resub = array_flip($getResub);
                $row['PledgeStatus'] = $allPledgeVal[$row['PledgeStatus']] ?? null;
                $row['EnrolRefNum'] = $row['EnrolRefNum'] ?? null;
                $row['InitialRejectReason'] = $initialRejectReason;

                $row['CRM_ID'] = $crmID;
                $row['Charity_ID'] = $charityID;
                $row['CharityCode'] = $charityCode;
                $row['EnrolStatus'] = $enrolStatus;
                $row['ReasonDesc'] = $enrolReasonDesc;
                $row['EnrolStatDate'] = $enrolStatusDate;
                $row['Resub'] = $resub[strtoupper($row['Resub'])] ?? BankingEnrolStatus::Resub_No;
                /*
                 * Find Available Values
                 */
                $condition = [];
                if ($charityID && !$crmID) {
                    $condition = ['Signup_DataEntry.Charity_ID' => $charityID];
                } elseif ($charityID && $crmID) {
                    $condition = ['Signup_DataEntry.CRM_ID' => $crmID, 'Signup_DataEntry.Charity_ID' => $charityID];
                } else {
                    $condition = ['Signup_DataEntry.CRM_ID' => $crmID];
                }
                if ($charityCode) {
                    $condition['Signup.CharityCode'] = $charityCode;
                }
                $checkRecordDataEntry = SignupDataEntry::where($condition)
                    ->leftJoin('Signup', 'Signup_DataEntry.CRM_ID', 'Signup.CRM_ID')
                    ->first();
                if (!$checkRecordDataEntry) {
                    $row['Reason'] = 'Data Entry Record Not Available.';
                    $failedRecord[] = $row;
                    continue;
                }
                $crmID = $checkRecordDataEntry->CRM_ID;
                $row['CRM_ID'] = $checkRecordDataEntry->CRM_ID;
                $row['Charity_ID'] = $checkRecordDataEntry->Charity_ID;

                $eid = $checkRecordDataEntry->getSignup->EID ?? null;
                $employeeDetail = null;
                if ($eid) {
                    $employeeDetail = EmployeeMaster::where(['EID' => $eid])->first();
                }
                if (!$employeeDetail) {
                    $row['Reason'] = 'Employee Not Exist.';
                    $failedRecord[] = $row;
                    continue;
                }
                /**
                 * Update frequency in signup account check table with match of crm id start
                 */
                $getAllFrequency = SignupAccountChk::getAllFrequency();
                $allFrequency = array_keys($getAllFrequency);
                $frequency = isset($row['Frequency']) ? $row['Frequency'] : null;
                if (($frequency !== null) && in_array($frequency, $allFrequency)) {
                    $getSAC = SignupAccountChk::where(['CRM_ID' => $crmID])->first();
                    if ($getSAC) {
                        $getSAC->update(['Frequency' => $frequency]);
                    }
                }

                /**
                 * Update frequency in signup account check table with match of crm id end
                 */
                $row['EID'] = $employeeDetail->EID ?? '';
                $row['FID'] = $employeeDetail->FID ?? '';
                $row['EName'] = $employeeDetail->EName ?? '';
                $row['ETL'] = $employeeDetail->ETL ?? '';
                $row['SignupDate'] = $checkRecordDataEntry->getSignup->PDate;   //need to complete
                $row['ModeOfDonation'] = $checkRecordDataEntry->getSignup->ModeOfDonation ?? null;
                $row['BatchNo'] = $checkRecordDataEntry->BatchNo ?? null;

                $checkEnrolStatus = BankingEnrolStatus::where(['CRM_ID' => $crmID])
                    ->first();
                if ($checkEnrolStatus) {
//                    $input = array_filter($row);
                    $input = $row;
                    $checkEnrolStatus->update($input);
                } else {
                    BankingEnrolStatus::create($row);
                }
                $this->bCopyOperation($crmID, $row);
                $successRecord[] = $rowData;
            } catch (\Exception $ex) {
                $rowData['Reason'] = $ex->getMessage();
                $failedRecord[] = $rowData;
            }
        }
        return [
            'successRecord' => $successRecord,
            'failedRecord' => $failedRecord
        ];
    }

    /**
     * Make replica of b copy to new table
     *
     * @param type $crmID
     * @param type $row
     * @return type
     */
    public function bCopyOperation($crmID, $row) {
        $checkBCopyAvailablity = SignupFormChk::where(['CRM_ID' => $crmID, 'BCopySub' => SignupFormChk::BCopySubAvailable])->first();
        if (!$checkBCopyAvailablity) {
            $updateEnrolStatus = BankingEnrolStatus::where(['CRM_ID' => $crmID])->first();
            if ($updateEnrolStatus) {
                $updateEnrolStatus->update(['Resub' => BankingEnrolStatus::Resub_No]);
            }
            return;
        }
        if ($checkBCopyAvailablity && isset($row['Resub']) && ($row['Resub'] == BankingEnrolStatus::Resub_Yes)) {
            $checkDataEntryFlag = SignupDataEntry::where(['CRM_ID' => $crmID])->first();
            if ($checkDataEntryFlag && (($checkDataEntryFlag->BCopyFlag == null) || ($checkDataEntryFlag->BCopyFlag == 0))) {
                /**
                 * Update old crm id data
                 */
                $data = $checkDataEntryFlag->toArray();
                /**
                 * Add ref crm id
                 */
                $data['RefCrmID'] = $checkDataEntryFlag->CRM_ID;
                $data['FormType'] = SignupDataEntry::FormTypeB;
                unset($data['CRM_ID']);
                unset($data['id']);
                $data['BatchNo'] = null;
                $data['BCopyFlag'] = null;
                $data['Charity_ID'] = null;
                $data['exportDate'] = null;
                $data['dataEntryStatus'] = null;
                $checkRefCrmExist = BCopyDataEntry::where(['RefCrmID' => $checkDataEntryFlag->CRM_ID])
                    ->first();
                /**
                 * replicate data to new table
                 */
                if ($checkRefCrmExist) {
                    $checkRefCrmExist->update($data);
                } else {
                    BCopyDataEntry::create($data);
                }
                $checkDataEntryFlag = SignupDataEntry::where(['CRM_ID' => $crmID])->first();
                $checkDataEntryFlag->update(['BCopyFlag' => SignupDataEntry::BCopyFlagUsed]);
            }
        }
    }

    public function bankingDebitImport($table, $data) {
        $successRecord = [];
        $failedRecord = [];

        $deletedCrmIds = [];

        foreach ($data['row'] as $row) {
            $rowData = $row;
            try {
                $crmID = $row['CRM_ID'] ?? null;
                $charityID = $row['Charity_ID'] ?? null;
                $charityCode = $row['CharityCode'] ?? null;
                if ($charityCode) {
                    $getCharityCode = CharityCode::where(['CharityCode' => $charityCode])->first();
                    $charityCode = null;
                    if ($getCharityCode) {
                        $charityCode = $getCharityCode->id;
                    }
                }

                $debitStatusDate = Common::fixDateFormat($row['DebitStatusDate'], 'Y-m-d', 'Y-m-d') ?? null;

                $getAllDebitStatus = BankingDebitStatus::getDebitStatus();
                $allDebitStatusVal = array_flip($getAllDebitStatus);
                $debitStatus = $allDebitStatusVal[$row['DebitStatus']] ?? null;

                $debitAttempt = is_numeric($row['Debit_Attempt']) ?? null;
                $debitReasonDesc = $row['DebitReasonDesc'] ?? null;
                /**
                 * Check Condition
                 */
                if (!$crmID && !$charityID) {
                    $row['Reason'] = 'CRM Id or Charity Id is required';
                    $failedRecord[] = $row;
                    continue;
                }
                if (!$charityCode) {
                    $row['Reason'] = 'Charity Code is required';
                    $failedRecord[] = $row;
                    continue;
                }
                if (!$debitAttempt) {
                    $row['Reason'] = 'Attempt required and must me numeric';
                    $failedRecord[] = $row;
                    continue;
                }
                if (!$debitStatusDate) {
                    $row['Reason'] = 'Date is required with format(Y-m-d) : 2020-12-31';
                    $failedRecord[] = $row;
                    continue;
                }
                if ($debitStatus === null) {
                    $row['Reason'] = 'Debit Status is required';
                    $failedRecord[] = $row;
                    continue;
                }

                if (($debitStatus !== null) && ($debitStatus == BankingDebitStatus::DebitStatusFail) && !$debitReasonDesc) {
                    $row['Reason'] = 'Reject Reason is required';
                    $failedRecord[] = $row;
                    continue;
                }

                /**
                 * Apply real value to submit in database
                 */
                $row['CRM_ID'] = $crmID;
                $row['Charity_ID'] = $charityID;
                $row['CharityCode'] = $charityCode;
                $row['DebitRefNum'] = $row['DebitRefNum'] ?? null;
                $row['Debit_Attempt'] = $debitAttempt;
                $row['DebitStatus'] = $debitStatus;
                $row['DebitReasonDesc'] = $debitReasonDesc;
                $row['DebitStatusDate'] = $debitStatusDate;

                /*
                 * Find Available Values
                 */
                $condition = [];
                if ($charityID && !$crmID) {
                    $condition = ['Signup_DataEntry.Charity_ID' => $charityID];
                } elseif ($charityID && $crmID) {
                    $condition = ['Signup_DataEntry.CRM_ID' => $crmID, 'Signup_DataEntry.Charity_ID' => $charityID];
                } else {
                    $condition = ['Signup_DataEntry.CRM_ID' => $crmID];
                }
                if ($charityCode) {
                    $condition['Signup.CharityCode'] = $charityCode;
                }
                $checkRecordDataEntry = SignupDataEntry::where($condition)
                    ->leftJoin('Signup', 'Signup_DataEntry.CRM_ID', 'Signup.CRM_ID')
                    ->first();
                if (!$checkRecordDataEntry) {
                    $row['Reason'] = 'Data Entry Record Not Available.';
                    $failedRecord[] = $row;
                    continue;
                }
                $crmID = $checkRecordDataEntry->CRM_ID;
                $row['CRM_ID'] = $checkRecordDataEntry->CRM_ID;
                $row['Charity_ID'] = $checkRecordDataEntry->Charity_ID;

//                $getEnrollStatus = BankingDebitStatus::where(['CRM_ID' => $crmID])->first();
//                if (!$getEnrollStatus) {
//                    $row['Reason'] = 'Debit Status Record Not Available.';
//                    $failedRecord[] = $row;
//                    continue;
//                }
                $eid = $checkRecordDataEntry->getSignup->EID ?? null;
                $employeeDetail = null;
                if ($eid) {
                    $employeeDetail = EmployeeMaster::where(['EID' => $eid])->first();
                }
                if (!$employeeDetail) {
                    $row['Reason'] = 'Employee Not Exist.';
                    $failedRecord[] = $row;
                    continue;
                }
                $row['EID'] = $employeeDetail->EID ?? '';
                $row['FID'] = $employeeDetail->FID ?? '';
                $row['EName'] = $employeeDetail->EName ?? '';
                $row['ETL'] = $employeeDetail->ETL ?? '';
                $row['SignupDate'] = $checkRecordDataEntry->getSignup->PDate;   //need to complete
                $row['ModeOfDonation'] = $checkRecordDataEntry->getSignup->ModeOfDonation ?? null;
                $row['BatchNo'] = $checkRecordDataEntry->BatchNo ?? null;

                $checkBankingDebitStatus = BankingDebitStatus::where(['CRM_ID' => $crmID, 'Debit_Attempt' => $debitAttempt])
                    ->first();
                if ($checkBankingDebitStatus) {
                    $checkBankingDebitStatus->update($row);
                } else {
                    BankingDebitStatus::create($row);
                }
                $successRecord[] = $rowData;
            } catch (\Exception $ex) {
                $rowData['Reason'] = 'Please contact admin for more report.';
                $failedRecord[] = $rowData;
            }
        }

        return [
            'successRecord' => $successRecord,
            'failedRecord' => $failedRecord
        ];
    }

}
