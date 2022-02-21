<?php

namespace App\Http\Controllers;

use App\Model\ChatGroups;
use App\Model\CharityCode;
use App\Model\EmployeeECity;
use App\Helper\PubNubHelper;
use App\Model\ChatTeamGroup;
use App\Model\ChatCityGroup;
use App\Model\EmployeeMaster;
use App\Http\Requests\SendMessageToGroups;

class PubNubController extends Controller {

    public function __construct() {
        parent::__construct();
    }

    public function groupManage() {
        $teamGroups = ChatGroups::where(['type' => ChatGroups::TypeTeam])->groupBy('GrpId')->get();
        $cityGroups = ChatGroups::where(['type' => ChatGroups::TypeCity])->groupBy('GrpId')->get();
        $input = request()->all();

        $employee = null;
        if (isset($input['search'])) {
            $search = $input['search'];
            $employee = EmployeeMaster::where('EID', 'LIKE', '%' . $search . '%')
                    ->whereHas('getUserInfo')
                    ->orWhere('EName', 'LIKE', '%' . $search . '%')
                    ->paginate($this->pageSize);
        }

        return view('pubNub.groupManage', compact('teamGroups', 'cityGroups', 'employee'));
    }

    public function manageEmployeeGroups() {
        $input = request()->all();
        $eid = $input['EID'];
        $teamGroups = $input['teamGroups'] ?? [];
        $cityGroups = $input['cityGroups'] ?? [];
        $checkEmployee = EmployeeMaster::where(['EID' => $eid])->first();
        if (!$checkEmployee) {
            return $this->sendResponse(false, '', 'Invalid Data.');
        }
        /**
         * Assign Employee to new groups
         */
//        Setup Team Groups
        $this->setupEmployeeTeamGroups($eid, $teamGroups);
        $this->setupEmployeeCityGroups($eid, $cityGroups);

        /**
         * Delete Group If Not Assigned
         */
        try {
            $this->deleteNonAssignedGroup();
        } catch (\Exception $ex) {
            
        }

        return $this->sendResponse(true, '', 'Group Assigned Successfully.');
    }

    public function deleteNonAssignedGroup() {
        /*
         * $deleteNonUsedTeamGroup
         */
        ChatGroups::where(['ChatGroups.type' => ChatGroups::TypeTeam])
                ->whereDoesntHave('getChatTeamGroup')
                ->delete();
        /*
         * $deleteNonUsedCityGroup
         */
        ChatGroups::where(['ChatGroups.type' => ChatGroups::TypeCity])
                ->whereDoesntHave('getChatCityGroup')
                ->delete();
    }

    /**
     * 
     * @param type $eid
     * @param type $teamGroups
     */
    public function setupEmployeeTeamGroups($eid, $teamGroups) { //12345678 
        $getExestingTeamGroups = ChatTeamGroup::where(['EID' => $eid])->groupBy('GrpId')->get();
        $deleteGroup = [];
        $getGrpup = [];
        $addGroup = [];
        foreach ($getExestingTeamGroups as $val) {
            if (!in_array($val->GrpId, $teamGroups)) {
                $deleteGroup[] = $val->GrpId; // -> 9
            }
            $getGrpup[] = $val->GrpId; //124569 -> 378
        }
        foreach ($teamGroups as $val) {
            if (!in_array($val, $getGrpup)) {
                $addGroup[] = $val;
            }
        }
        /**
         * Unsubscribe Group From Database and PubNub server
         */
        foreach ($deleteGroup as $val) {
            PubNubHelper::pubNubResponse('delete', $eid, $val);
            ChatTeamGroup::where(['EID' => $eid, 'GrpId' => $val])->delete();
        }
        /**
         * Subscribe Group In Database and PubNub Server
         */
        foreach ($addGroup as $val) {
            PubNubHelper::pubNubResponse('GET', $eid, $val);
            $getGroup = ChatGroups::where(['GrpId' => $val, 'type' => ChatGroups::TypeTeam])->first();
            $data = [
                'EID' => $eid,
                'GrpId' => $val,
                'GrpName' => $getGroup->GrpName
            ];
            ChatTeamGroup::create($data);
        }
    }

    /**
     * 
     * @param type $eid
     * @param type $cityGroups
     */
    public function setupEmployeeCityGroups($eid, $cityGroups) {
        $getExestingCityGroups = ChatCityGroup::where(['EID' => $eid])->groupBy('GrpId')->get();
        $deleteGroup = [];
        $getGrpup = [];
        $addGroup = [];
        foreach ($getExestingCityGroups as $val) {
            if (!in_array($val->GrpId, $cityGroups)) {
                $deleteGroup[] = $val->GrpId; // -> 9
            }
            $getGrpup[] = $val->GrpId; //124569 -> 378
        }
        foreach ($cityGroups as $val) {
            if (!in_array($val, $getGrpup)) {
                $addGroup[] = $val;
            }
        }
        /**
         * Unsubscribe Group From Database and PubNub server
         */
        foreach ($deleteGroup as $val) {
            PubNubHelper::pubNubResponse('delete', $eid, $val);
            ChatCityGroup::where(['EID' => $eid, 'GrpId' => $val])->delete();
        }
        /**
         * Subscribe Group In Database and PubNub Server
         */
        foreach ($addGroup as $val) {
            PubNubHelper::pubNubResponse('GET', $eid, $val);
            $getGroup = ChatGroups::where(['GrpId' => $val, 'type' => ChatGroups::TypeCity])->first();
            $data = [
                'EID' => $eid,
                'GrpId' => $val,
                'GrpName' => $getGroup->GrpName
            ];
            ChatCityGroup::create($data);
        }
    }

    public function sendMessageToGroupsView() {
        $getAllCharityCode = CharityCode::get();
        $groupData = [];
        $getAllCity = EmployeeECity::get();
        $addedGroup = [];
        foreach ($getAllCharityCode as $val) {
            $chatiryCode = str_replace(' ', '%', $val->CharityCode);
            foreach ($getAllCity as $city) {
                $teamGroupData = [];
                $cityGroupData = [];
                $cityName = str_replace(' ', '%', $city->Ecity);
                /*                 * Get Team Group* */
                $getTeamGroup = ChatGroups::where(['type' => ChatGroups::TypeTeam])
                        ->where('GrpName', 'LIKE', $chatiryCode . '%' . $cityName . '%')
                        ->orderBy('GrpName')
                        ->get();
                foreach ($getTeamGroup as $key => $tgd) {
                    $teamGroupData[$tgd->GrpId] = $tgd->GrpName;
                    $addedGroup[] = $tgd->GrpId;
                }
                /*                 * Get City Group* */
                $getCityGroup = ChatGroups::where(['type' => ChatGroups::TypeCity])
                                ->where('GrpName', 'LIKE', 'tl%' . $chatiryCode . '%' . $cityName . '%')->get();
                foreach ($getCityGroup as $key => $cgd) {
                    $cityGroupData[$cgd->GrpId] = $cgd->GrpName;
                    $addedGroup[] = $cgd->GrpId;
                }
                $groupData[$val->CharityCode][$city->Ecity]['Team Group'] = $teamGroupData;
                $groupData[$val->CharityCode][$city->Ecity]['City Group'] = $cityGroupData;
            }
        }
        /* get all remain group which is not filter by city and charity code */
        $remainGroups = [];
        $getRemainGroups = \App\Model\ChatGroups::whereNotIn('GrpId', $addedGroup)->get();
        foreach ($getRemainGroups as $key => $rg) {
            $remainGroups[$rg->GrpId] = $rg->GrpName;
        }

//        print_r($groupData);
//        print_r($remainGroups);
//        $getCityGroups = ChatCityGroup::groupBy('GrpId')->get();
//        $getTeamGroups = ChatTeamGroup::groupBy('GrpId')->get();
        return view('pubNub.sendMessageToGroupsView', compact('groupData', 'remainGroups'));
    }

    public function sendMessageToGroups(SendMessageToGroups $request) {
        $input = request()->all();
        $grpIds = $input['GrpId'];
        foreach ($grpIds as $grpId) {
            $message = $input['message'];
            $eid = $this->user->EID;
            $eName = $this->user->EName ?? null;
            PubNubHelper::pubNubResponse('GET', $eid, $grpId);
            /**
             * Send Message
             */
            PubNubHelper::createFormatAndSendMessage($grpId, $message, null, null, $eid, $eName);
        }
        return $this->sendResponse(true, '', 'Message Send To Group Successfully.');
    }

}
