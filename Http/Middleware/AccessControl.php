<?php

namespace App\Http\Middleware;

use Closure;
use App\Helper\Common;
use App\Model\EmployeeEDesg;
use App\Model\EmployeeMaster;

class AccessControl {

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $this->allowAccessToDesg();
        return $next($request);
    }

    public function accessPath() {
        return [
            'HomeController' => [
                'dashboardReport' => [EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN]
            ],
            'AdminController' => [
                'donorList' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorSelect' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorCallList' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorCallSelect' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorQualityList' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorQualitySelect' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorDataEntryList' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorDataEntrySelect' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'formReceivableView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'employeeList' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'employeeEditView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'employeeRegisterView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN]
            ],
            'BackOfficeController' => [
                'bCopyView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'bCopySearchView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'bCopySearchEdit' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'bCopyCallingView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_TM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN, EmployeeEDesg::DESG_TM],
                'clientExportData' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'clientReportImportView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'createExportQueryView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'clientExportQueryList' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
            ],
            'AttendanceController' => [
                'viewSearchAttendance' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'viewModifyAttendance' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN]
            ],
            'ReportController' => [
                'productivityReportView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'streetVsPermissionReportView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'frPerformanceReportView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorQualityReportView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'welcomeCallReportView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'oneOffReportView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'formQualityReportView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorAwarenessReportView' => [EmployeeEDesg::DESG_BO, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_CH, EmployeeEDesg::DESG_RM, EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                /*                 * */
                'processHealthReportView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'locationProductivityReportView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'locationPerformanceReportView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'donorFeedbackReportView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'probationalReportView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'billingReportView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'incentiveDataReportView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
            ],
            'ExportController' => [
                'showTables' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_STL, EmployeeEDesg::DESG_PM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'exportTable' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
            ],
            'ImportController' => [
                'importTableView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN]
            ],
            'PubNubController' => [
                'groupManage' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'sendMessageToGroupsView' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN]
            ],
            'ExotelController' => [
                'downloadCallDetail' => [EmployeeEDesg::DESG_OM, EmployeeEDesg::DESG_ADMIN, EmployeeEDesg::DESG_SUPER_ADMIN],
                'siteSettings' => [EmployeeEDesg::DESG_SUPER_ADMIN],
            ]
        ];
    }

    public function authDesg() {
        $authUser = auth()->user();
        $employeeMaster = EmployeeMaster::where(['EID' => $authUser->EID])->first();
        return $employeeMaster->EDesg;
    }

    public function allowAccessToDesg() {
        list($controller, $method) = Common::getCandF();
        $accessPath = $this->accessPath();
        if (!isset($accessPath[$controller][$method])) {
            return;
        }
        if (in_array($this->authDesg(), $accessPath[$controller][$method])) {
            return;
        } else {
            abort(403, 'You are not allowed to access this functionality');
        }
    }

}
