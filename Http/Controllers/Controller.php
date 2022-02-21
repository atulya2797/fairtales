<?php

namespace App\Http\Controllers;

use App\Model\Signup;
use App\Model\SignupFormChk;
use App\Model\EmployeeEDesg;
use App\Model\SignupWlcmCall;
use App\Model\EmployeeMaster;
use App\Model\SignupDataEntry;
use App\Model\EmployeeEAccess;
use App\Model\SignupAccountChk;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController {

    use AuthorizesRequests,
        DispatchesJobs,
        ValidatesRequests;

    public $user;
    public $pageSize = 10;

    public function __construct() {
        $this->middleware('accessControl');
        $this->middleware('preventBackHistory');
    }

    /**
     * Send return response when Ajax call on form submit
     * 
     * @param boolean   $cstatus    This will be true or false.
     * @param string    $curl       This is the page Url where to redirect after form submit successfully.
     * @param string    $cmessage   This is to show message on error.
     * @param array     $cdata      Just in case if you want to send some data in return.
     * @param array     $function      This function will call in javascript like hgphpdev(param) (this is your custom function and param will be your data you send).
     * @return array    This will return all param detail with array.
     * 
     * */
    public function sendResponse($cstatus, $curl = '', $cmessage = '', $cdata = [], $function = '') {
        return [
            'status' => $cstatus,
            'url' => $curl,
            'message' => $cmessage,
            'data' => $cdata,
            'function' => $function
        ];
    }

    /**
     * 
     * @param type $step
     * @return type
     * 
     * This same function available in common helper for show count in header
     */
    public function getDonorList($step = null) {
        $request = request()->all();
        $sort = 'asc';
        if (isset($request['sort'])) {
            if ($request['sort'] == 'desc') {
                $sort = 'desc';
            }
        }
        $getAllRemainDonor = Signup::where(['accountCheck' => $step])
                ->select('Signup.*', 'Employee_Master.EID', 'Employee_Master.ECity', 'Employee_ECity.Ecity')
                ->leftJoin('Signup_AccountChk', 'Signup.CRM_ID', 'Signup_AccountChk.CRM_ID')
                ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                ->leftJoin('Employee_ECity', 'Employee_Master.ECity', 'Employee_ECity.id')
                ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                ->orderBy('Employee_ECity.Ecity', $sort)
                ->orderBy('Signup.CRM_ID', 'asc')
                ->where(function($checkNull) {
                    return $checkNull->where('BOStatUpdate', null)
                            ->orWhere('BOStatUpdate', SignupAccountChk::STATUS_RETRY);
                })
                ->where(function($q) {
            return $q->where(['BOStatRetryTime' => null])
                    ->orWhere(function($t) {
                        return $t->whereDate('BOStatRetryTime', '<=', date('Y-m-d'))
                                ->whereTime('BOStatRetryTime', '<=', date('H:i:s'));
                    });
        });

        if ($step == Signup::ACCOUNT_CHECK) {
            $getAllRemainDonor = Signup::select('Signup.*', 'Employee_Master.EID', 'Employee_Master.ECity', 'Employee_ECity.Ecity', 'Signup_WlcmCall.CallBackTime', 'Signup_WlcmCall.Call_FinalStatus')
                    ->where(function($wq) use ($step) {
                        return $wq->where(['accountCheck' => $step])
                                ->orWhere(function($dq) {
                                    return $dq->where(['accountCheck' => Signup::DATA_ENTRY_CHECK])
                                            ->where(['Signup_DataEntry.dataEntryStatus' => null]);
                                })
                                /**
                                 * Start:
                                 * This is only for that which is already listed in 
                                 * form receivable and still welcome call 
                                 * verification pending.
                                 */
                                ->orWhere(function($dqa) {
                                    return $dqa->where(['accountCheck' => Signup::FORM_RECEIVABLE]);
                                })
                                ->orWhere(function($dqa) {
                                    return $dqa->where(['accountCheck' => Signup::CALL_COMPLETE]);
                                });
                        /**
                         * End:
                         * This is only for that which is already listed in 
                         * form receivable and still welcome call 
                         * verification pending.
                         */
                    })
//                    ->where(['accountCheck' => $step])
                    ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->leftJoin('Employee_ECity', 'Employee_Master.ECity', 'Employee_ECity.id')
                    ->leftJoin('Signup_WlcmCall', 'Signup.CRM_ID', 'Signup_WlcmCall.CRM_ID')
                    ->where(function($checkNull) {
                        return $checkNull->where('Signup_WlcmCall.Call_FinalStatus', null)
                                ->orWhere(function($oq) {
                                    return $oq->where('Signup_WlcmCall.Call_FinalStatus', '<>', SignupWlcmCall::Call_FinalStatus_rejected)
                                            ->where('Signup_WlcmCall.Call_FinalStatus', '<>', SignupWlcmCall::Call_FinalStatus_process_unverified)
                                            ->where('Signup_WlcmCall.Call_FinalStatus', '<>', SignupWlcmCall::Call_FinalStatus_verified);
                                });
                    })
                    ->where(function($qAttempt) {
                        return $qAttempt->where(['Call_Attempt' => null])
                                ->orWhere('Call_Attempt', '<', SignupWlcmCall::MaxCountNotVerify);
                    })
                    ->where(function($q) {
                return $q->where(['CallBackTime' => null])
                        ->orWhere(function($t) {
                            return $t->where('CallBackTime', '<=', date('Y-m-d H:i:s'));
//                            return $t->whereDate('CallBackTime', '<=', date('Y-m-d'))
//                                    ->whereTime('CallBackTime', '<=', date('H:i:s'));
                        });
            });
        }

        if ($step == Signup::QUALITY_CHECK) {
            $getAllRemainDonor = Signup::where(['accountCheck' => $step])
                    ->select('Signup.*', 'Employee_Master.EID', 'Employee_Master.ECity', 'Employee_ECity.Ecity')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->leftJoin('Employee_ECity', 'Employee_Master.ECity', 'Employee_ECity.id')
                    ->leftJoin('Signup_FormChk', 'Signup.CRM_ID', 'Signup_FormChk.CRM_ID')
                    ->leftJoin('Signup_WlcmCall', 'Signup.CRM_ID', 'Signup_WlcmCall.CRM_ID')
                    ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                    ->where(function($q) {
                        return $q->where(['Signup_FormChk.FFPStatus' => null]);
                    })
                    /**
                     * This is to show record in both in form quality check and in welcome call
                     */
                    ->orWhere(function($oq) {
                return $oq->whereIn('Signup_WlcmCall.Call_FinalStatus', [SignupWlcmCall::Call_FinalStatus_not_verified, SignupWlcmCall::Call_FinalStatus_process_unverified])
                        ->where(['Signup_FormChk.FFPStatus' => null]);
            });
        }

        if ($step == Signup::DATA_ENTRY_CHECK) {
            $getAllRemainDonor = Signup::select('Signup.*', 'Employee_Master.EID', 'Employee_Master.ECity', 'Employee_ECity.Ecity')
                    ->where(function($dq) use ($step) {
                        return $dq->where(['accountCheck' => $step])
                                ->orWhere(function($qq) {
                                    return $qq->where(function($fq) {
                                                return $fq->where(['accountCheck' => Signup::QUALITY_CHECK])
                                                        ->where('Signup_FormChk.FFPStatus', '<>', SignupFormChk::FFPStatus_Reject)
                                                        ->where('Signup_FormChk.FFPStatus', '<>', null);
                                            });
                                });
                    })
//                    ->where(['accountCheck' => $step])
                    ->leftJoin('Signup_FormChk', 'Signup.CRM_ID', 'Signup_FormChk.CRM_ID')
                    ->leftJoin('Employee_Master', 'Signup.EID', 'Employee_Master.EID')
                    ->leftJoin('Employee_ECity', 'Employee_Master.ECity', 'Employee_ECity.id')
                    ->leftJoin('Signup_DataEntry', 'Signup.CRM_ID', 'Signup_DataEntry.CRM_ID')
                    ->where(function($checkNull) {
                return $checkNull->where('Signup_DataEntry.dataEntryStatus', null);
//                        ->orWhere('Signup_DataEntry.dataEntryStatus', '<>', SignupDataEntry::dataEntryStatus_Reject);
            });
        }
        $getAllRemainDonor = $getAllRemainDonor->where(['Signup_DataEntry.exportDate' => null]);

        $getAllRemainDonorCount = $getAllRemainDonor->count();
        $getAllRemainDonor = $getAllRemainDonor
                ->orderBy('Employee_ECity.Ecity', $sort)
                ->orderBy('Signup.CRM_ID', 'asc')
                ->groupBy('Signup.CRM_ID')
                ->get();
//                ->paginate($this->pageSize);
        $returnData = [
            'getAllRemainDonor' => $getAllRemainDonor,
            'getAllRemainDonorCount' => $getAllRemainDonorCount
        ];
        return $returnData;
    }

    /**
     * 
     * @param type $getAllRemainDonor
     * @param type $crmId
     * @return boolean
     */
    public function checkDonorIfInList($getAllRemainDonor, $crmId) {
        $inList = false;
        foreach ($getAllRemainDonor as $val) {
            if ($val->CRM_ID == $crmId) {
                $inList = true;
                break;
            }
        }
        return $inList;
    }

    public function getEmployeeAccessByAuth() {
        $dataArray = [];
        if ($this->user->EAccess == EmployeeEAccess::ACCESS_SUPER_ADMIN) {
            $dataArray = [
                EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_STL,
                EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_PM,
                EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_FR,
            ];
        }
        if ($this->user->EAccess == EmployeeEAccess::ACCESS_ADMIN) {
            $dataArray = [
                EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_RM,
                EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TL,
                EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_FR
            ];
        }
        if ($this->user->EAccess == EmployeeEAccess::ACCESS_OM) {
            $dataArray = [
                EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_CH,
                EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_BO,
                EmployeeEDesg::DESG_FR,
            ];
        }
        if ($this->user->EAccess == EmployeeEAccess::ACCESS_STL) {
            $dataArray = [
                EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_PM,
                EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_FR,
            ];
        }
        if ($this->user->EAccess == EmployeeEAccess::ACCESS_RM) {
            $dataArray = [
                EmployeeEDesg::DESG_TL
            ];
        }
        if ($this->user->EAccess == EmployeeEAccess::ACCESS_CH) {
            $dataArray = [
                EmployeeEDesg::DESG_TL
            ];
        }
        if ($this->user->EAccess == EmployeeEAccess::ACCESS_PM) {
            $dataArray = [
                EmployeeEDesg::DESG_TL, EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_FR,
            ];
        }
        if ($this->user->EAccess == EmployeeEAccess::ACCESS_TL) {
            $dataArray = [
                EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_FR
            ];
        }
        if ($this->user->EAccess == EmployeeEAccess::ACCESS_BO) {
            $dataArray = [
                EmployeeEDesg::DESG_FR,
            ];
        }
        return $dataArray;
    }

    public function getManagersDesgType() {
        $list = [
            EmployeeEDesg::find(EmployeeEDesg::DESG_TL),
            EmployeeEDesg::find(EmployeeEDesg::DESG_PM),
            EmployeeEDesg::find(EmployeeEDesg::DESG_CH),
            EmployeeEDesg::find(EmployeeEDesg::DESG_RM),
        ];
        return $list;
    }

    public function callAction($method, $parameters) {
        $authUser = auth()->user();
        if ($authUser) {
            $getEmplioyeeData = EmployeeMaster::where(['EID' => $authUser->EID])->first();
            if ($getEmplioyeeData) {
                $this->user = $getEmplioyeeData;
            } else {
                $this->user = NULL;
            }
        }
        return parent::callAction($method, $parameters);
    }

    /**
     * 
     * @param type $searchString
     * @return type
     */
    public function clearString($string) {
        $trimName = trim(strtolower($string));
        $explodeName = explode(' ', $trimName);
        $filterName = array_filter($explodeName);
        $newName = implode('-', $filterName);
        $rename = preg_replace('/[^A-Za-z0-9\-]/', '', $newName);
        $newExplodeName = explode('-', $rename);
        $newFilterName = array_filter($newExplodeName);
        $slug = implode(' ', $newFilterName);
        return $slug;
    }

    /**
     * Update date to for where between condition
     * Because wherebetween take dataTo previous date
     */
    public function upateDateTo($date, $format = 'Y-m-d') {
        $generateData = \DateTime::createFromFormat($format, $date);
        $dateTo = $generateData->modify('+1 day');
        return $generateData->format($format);
    }

}
