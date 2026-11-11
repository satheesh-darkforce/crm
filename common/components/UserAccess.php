<?php

namespace common\components;

use backend\models\ClientModel;
use backend\models\Developer;
use backend\models\Employee;
use backend\models\User;
use Yii;

/**
 * Centralized access-control helper — mirrors the Yii1 UserAccess component.
 *
 * Usage:
 *   UserAccess::checkAccess('task/index')           // current user
 *   UserAccess::checkAccess('invoice/create')        // current user
 *
 * Rules:
 *   - Admin (usertype=1):     always TRUE
 *   - Sub-admin (usertype=4): TRUE if route is in Employee.access_menus (serialized array)
 *   - Developer (usertype=2): TRUE if route is in default list OR in developer.access_permissions
 *   - Client (usertype=3):    TRUE if route is in client.access_permissions
 *   - Guest:                  always FALSE
 */
class UserAccess
{
    /**
     * Default permissions every developer gets without explicit assignment.
     * Mapped from Yii1 getDeveloperDefaultAccess() — uses Yii2 controller-id/action-id format.
     */
    public static function getDeveloperDefaultAccess(): array
    {
        return [
            // Attendance — self-service + Monthly Attendance. 'attendance/index'
            // (All Attendance) and 'attendance/verify-attendance' (verifying others)
            // require explicit grant via developer.access_permissions.
            'attendance/my-attendance', 'attendance/monthly-attendance',
            'attendance/monthly-attendance-export',
            'attendance/verify-my-attendance', 'attendance/update-remarks',
            'attendance/check-in', 'attendance/check-out',

            // Work Reports — self-service viewing only. 'work-report/create'
            // (Add Work Report), 'work-report/index' (Daily Work Report — company-wide)
            // and 'work-report/developer-current-month' require explicit grant.
            'work-report/my-work-reports', 'work-report/view',

            // Leave Requests — `leave/index` (all-leave admin listing), `leave/update`
            // and `leave/decide` require explicit grant via developer.access_permissions.
            // Defaults cover only the developer's own-leave self-service routes.
            'leave/create', 'leave/view', 'leave/my-requests', 'leave/cancel',

            // Permission requests (hourly) — self-service. `permission/decide`
            // (approve/reject) requires explicit grant via developer.access_permissions.
            'permission/index', 'permission/create', 'permission/cancel',

            // Developer profile
            'developer/view', 'developer/update-emp-pin', 'developer/view-emp-pin',

            // Payslip
            'payslip/index', 'payslip/view',
            // Yii1 parity (developer_header.php → uploadpayslip/developer + viewpayslip
            // + downloadpayslip are all in the default developer access list).
            // payslip-upload/index (admin list), /create, /update and /delete still
            // require an explicit grant via developer.access_permissions.
            'payslip-upload/developer',
            'payslip-upload/view-payslip',
            'payslip-upload/download-payslip',

            // Projects — Yii1 parity: developers see only their own listings
            // (developer-projects + all-project under Work Reports) and the AJAX
            // helpers. The full 'project/index' listing requires explicit grant
            // (mirrors Yii1, where 'project/admin' is not in the developer defaults).
            'project/view', 'project/developer-projects', 'project/all-project',
            'project/get-projects-by-client', 'project/get-phase-value', 'project/get-milestone-value', 'project/search-title',
            'project-management/developer-projects',

            // Tasks
            'task/index', 'task/view', 'task/create', 'task/update', 'task/delete',
            'task/my-task-list', 'task/my-new-task-list', 'task/completed-task-list', 'task/assigned-tasks',
            'task/task-status', 'task/task-history', 'task/task-logs',
            'task/task-start', 'task/task-stop', 'task/complete-task', 'task/task-accept',
            'task/get-subtasks', 'task/reassign-task', 'task/full-reassign-task',
            'task/calc-expected-date',

            // Developer Tasks (tasks table)
            'tasks/developer-tasks', 'tasks/view', 'tasks/change-status', 'tasks/add-work-report',
            'tasks/autocomplete-developer',

            // Assets (utility AJAX)
            'asset/get-subcategory', 'asset/delete-upload',

            // IOT Products (utility AJAX only — landing pages like index/masters/purchased/
            // non-purchased/pick-vendor must be granted explicitly via developer.access_permissions)
            'iot-product/get-subcategory', 'iot-product/get-available-quantity',
            'iot-product/delete-upload', 'iot-product/download-pdf',
            'iot-product/delete-old-product', 'iot-product/create-return-product',
            'iot-product/check-quantity', 'iot-product/add-product-popup',
            'iot-product/purchase-view', 'iot-product/delete-attachment',
            'iot-product/extra-expense',
            'iot-product/product-history', 'iot-product/barcode',
            'iot-product/print-multi-barcode',
            'iot-product/create-return',

            // Inward Invoice (utility AJAX)
            'inward-invoice/autocomplete-ledger-category', 'inward-invoice/autocomplete-ledger',
            'inward-invoice/extra-expense', 'inward-invoice/get-payment-ledgers',
            'inward-invoice/delete-attachment',

            // Site — NOTE: 'site/index' (dashboard) is NOT defaulted.
            // Yii1 mirrors this: admin/dashboard is only for admin/sub-admin
            // or developers with explicit permission. Dashboard visibility is
            // driven exclusively by developer.access_permissions.
            'site/change-password', 'site/error',

            // Delivery / Quotation module — user-facing routes are NOT in
            // defaults; they must be explicitly granted via Settings →
            // Access Menus (Quotation group + DC group). Only utility/AJAX
            // endpoints are defaulted so that flows keep working for users
            // who have the primary route granted.
            'client-quotation/get-next-no',
            'client-quotation/get-client-address',
            'client-quotation/quote-by-users',
            'client-quotation/additional-quote-by-users',
            'client-quotation/developer-official-contact',
            'client-quotation/current-developer-official-contact',
            'client-quotation/save-developer-official-contact',
            'client-quotation/preview-pdf',
            'client-quotation/upload-file',
            'client-quotation/download-file',
            'client-quotation/delete-file',
            'client-quotation/add-po',
            'client-quotation/complete-quote',
            'client-quotation/download-po',
            // Dashboard drill-downs (list-dc / view-dc) come with
            // quotation-dashboard/index via checkAccess()'s implicit children.
            'dc/get-next-dc-no',
            'dc/get-client-info',
            'delivery-product/ajax-create',
            'delivery-product/ajax-list',
            'delivery-product/ajax-get',
        ];
    }

    /**
     * Maps Yii2 controller/action routes to their Yii1 equivalents stored in the DB.
     * Permissions in developer.access_permissions are stored in Yii1 route format.
     *
     * @return array  [ 'yii2-route' => ['yii1-route', ...], ... ]
     */
    public static function getYii2ToYii1RouteMap(): array
    {
        return [
            // Site
            'site/index'                        => ['admin/dashboard'],
            // Clients
            'client/index'                      => ['client/admin'],
            'client/create'                     => ['client/create'],
            'client/update'                     => ['client/update'],
            'client/delete'                     => ['client/delete'],
            'client/view'                       => ['client/view'],
            'client/access-permissions'         => ['client/accesspermissions'],
            // Domains
            'domain/index'                      => ['domains/admin'],
            'domain/create'                     => ['domains/create'],
            'domain/update'                     => ['domains/update'],
            'domain/view'                       => ['domains/view'],
            'domain/delete'                     => ['domains/delete'],
            'domain/update-status'              => ['domains/domainstatus'],
            'domain/renewal'                    => ['domains/renewal'],
            'domain/renewal-history'            => ['domains/renewalhistory'],
            // Domain Followups
            'domain/follow-up'                  => ['domainfollowup/admin'],
            'domain/add-follow-up'              => ['domainfollowup/create'],
            'domain/update-follow-up'           => ['domainfollowup/update'],
            'domain/view-follow-up'             => ['domainfollowup/view'],
            // Projects
            'project/index'                     => ['project/admin'],
            'project/create'                    => ['project/create'],
            'project/update'                    => ['project/update'],
            'project/view'                      => ['project/view'],
            'project/delete'                    => ['project/delete'],
            'project/all-project'               => ['project/admin'],
            'project/change-status'             => ['project/changestatus'],
            'project/get-projects-by-client'    => ['project/getprojectbyclient'],
            'project/get-phase-value'           => ['project/getphasevalue'],
            'project/get-milestone-value'       => ['project/getmilestonevalue'],
            'project/developer-projects'        => ['project/developerprojects'],
            'project-management/index'          => ['projectmanagement/admin'],
            'project-management/developer-projects' => ['projectmanagement/admin'],
            // Employees
            'developer/index'                   => ['developer/admin'],
            'developer/create'                  => ['developer/create'],
            'developer/update'                  => ['developer/update'],
            'developer/view'                    => ['developer/view'],
            'developer/delete'                  => ['developer/delete'],
            'developer/ex-employees'            => ['developer/exemployees'],
            'developer/access-permissions'      => ['developer/accesspermissions'],
            'developer/access-permissions-new'  => ['developer/accesspermissionsnew'],
            'developer/apk-access-permissions'  => ['developer/apkaccesspermissions'],
            'developer/create-payslip'          => ['developer/createpayslip'],
            'developer/export-employees'        => ['developer/getexportemployees'],
            'developer/export-ex-employees'     => ['developer/getexportexemployees'],
            // Attendance
            'attendance/index'                    => ['attendance/admin', 'attendance/allattendance'],
            'attendance/my-attendance'            => ['attendance/myattendance'],
            'attendance/monthly-attendance'       => ['attendance/monthlyattendance'],
            'attendance/update-remarks'           => ['attendance/updateremarks'],
            'attendance/leave-settings'           => ['attendance/leavesettings'],
            'attendance/attendance-verification'  => ['attendance/attendanceverisication'],
            'attendance/verify-my-attendance'     => ['attendance/verifymyattendance'],
            'attendance/check-in'                 => ['attendance/checkin'],
            'attendance/check-out'                => ['attendance/checkout'],
            'attendance/change-attendance'        => ['attendance/changeattendance'],
            'holiday/index'                     => ['holidayssetup/admin'],
            'holiday/update'                    => ['holidayssetup/update'],
            'holiday/delete'                    => ['holidayssetup/delete'],
            'holiday/view'                      => ['holidayssetup/view'],
            // RFID card sub-actions all accept the "Manage RFID cards" parent
            // permission (rfidcards/admin) so granting the single menu option
            // grants all CRUD/assign/status operations — matches Yii1 behaviour.
            'rfid-card/index'                   => ['rfidcards/admin'],
            'rfid-card/create'                  => ['rfidcards/create', 'rfidcards/admin'],
            'rfid-card/update'                  => ['rfidcards/update', 'rfidcards/admin'],
            'rfid-card/delete'                  => ['rfidcards/delete', 'rfidcards/admin'],
            'rfid-card/view'                    => ['rfidcards/view',   'rfidcards/admin'],
            'rfid-card/assign'                  => ['rfidcards/assign', 'rfidcards/admin'],
            // Leave Requests
            'leave/index'                       => ['leaverequests/admin', 'leaverequests/allleaverequests'],
            'leave/create'                      => ['leaverequests/create'],
            'leave/update'                      => ['leaverequests/update'],
            'leave/view'                        => ['leaverequests/view'],
            'leave/my-requests'                 => ['leaverequests/myrequests'],
            'leave/cancel'                      => ['leaverequests/myrequests'],
            'leave/decide'                      => ['leaverequests/changestatus'],
            // Additional Works
            'additional-works/index'            => ['additionalworks/admin'],
            'additional-works/create'           => ['additionalworks/create'],
            'additional-works/update'           => ['additionalworks/update'],
            'additional-works/view'             => ['additionalworks/view'],
            'additional-works/delete'           => ['additionalworks/delete'],
            // Payslips
            'payslip/index'                     => ['payslip/admin'],
            'payslip-upload/index'              => ['uploadpayslip/admin'],
            'payslip-upload/create'             => ['uploadpayslip/create'],
            'payslip-upload/view'               => ['uploadpayslip/view'],
            'payslip-upload/view-payslip'       => ['uploadpayslip/viewpayslip'],
            'payslip-upload/download-payslip'   => ['uploadpayslip/downloadpayslip'],
            'payslip-upload/delete'             => ['uploadpayslip/delete'],
            'payslip-upload/developer'          => ['uploadpayslip/view'],
            // Salary Allowance
            'salary-allowance/index'            => ['salaryallowancededuction/allowances'],
            'salary-allowance/allowances'       => ['salaryallowancededuction/allowances'],
            'salary-allowance/deductions'       => ['salaryallowancededuction/deductions'],
            'salary-allowance/earnings-split'   => ['salaryallowancededuction/earningssplit'],
            'salary-allowance/create'           => ['salaryallowancededuction/createallowance', 'salaryallowancededuction/creatededuction'],
            'salary-allowance/update'           => ['salaryallowancededuction/update'],
            'salary-allowance/view'             => ['salaryallowancededuction/view'],
            'salary-allowance/delete'           => ['salaryallowancededuction/delete'],
            'salary-allowance/developer'        => ['salaryallowancededuction/allowances'],
            // Leads
            'lead/index'                        => ['lead/admin'],
            'lead/create'                       => ['lead/create'],
            'lead/update'                       => ['lead/update'],
            'lead/view'                         => ['lead/view'],
            'lead/change-status'                => ['lead/changestatus'],
            'lead/converted'                    => ['lead/converted'],
            'lead/dropped'                      => ['lead/dropped'],
            'lead/my-leads'                     => ['lead/myleads'],
            'lead/assigned'                     => ['lead/assigned'],
            'lead/view-assigned'                => ['lead/viewassigned'],
            'lead/change-lead-status'           => ['lead/changeleadstatus'],
            'lead/change-followup-status'       => ['lead/changefollowupstatus'],
            'lead/add-to-client'                => ['lead/addtoclient'],
            'lead/delete'                       => ['lead/delete'],
            'lead/delete-attachment'            => ['lead/deleteattachment'],
            'lead/lead-master'                  => ['lead/leadmaster'],
            // Yii1 Lead Master buttons checked the underscore names, so accept both.
            'lead/lead-master-view'             => ['lead/leadmasterview', 'lead/lead_master_view'],
            'lead/create-lead-master'           => ['lead/createleadmaster'],
            'lead/update-lead-master'           => ['lead/updateleadmaster', 'lead/update_lead_master'],
            'lead/delete-lead-master'           => ['lead/deleteleadmaster', 'lead/delete_lead_master'],
            'lead/convert-to-lead'              => ['lead/converttolead', 'lead/convert_to_lead'],
            'lead/upload-lead'                  => ['lead/uploadlead'],
            'lead-followers/index'              => ['leadfollowers/admin'],
            'lead-followers/create'             => ['leadfollowers/create'],
            'lead-followers/update'             => ['leadfollowers/update'],
            'lead-followers/view'               => ['leadfollowers/view'],
            'lead-followers/view-attachments'   => ['leadfollowers/viewattachments'],
            // Quotations
            'quotation/index'                   => ['quotations/admin'],
            'quotation/create'                  => ['quotations/create'],
            'quotation/update'                  => ['quotations/update'],
            'quotation/view'                    => ['quotations/view'],
            'quotation/delete'                  => ['quotations/delete'],
            // Proposals
            'proposal/index'                    => ['proposals/admin'],
            'proposal/create'                   => ['proposals/create'],
            'proposal/update'                   => ['proposals/update'],
            'proposal/view'                     => ['proposals/view'],
            'proposal/delete'                   => ['proposals/delete'],
            // Invoices
            'invoice/index'                     => ['invoice/admin'],
            'invoice/create'                    => ['invoice/create', 'invoice/addproformainvoice', 'invoice/addinvoice'],
            'invoice/update'                    => ['invoice/update'],
            'invoice/view'                      => ['invoice/view'],
            'invoice/delete'                    => ['invoice/delete'],
            'invoice/proforma'                  => ['invoice/proforma'],
            'invoice/add-receipt'               => ['invoicereceipt/addreceipt'],
            'invoice/create-receipt'            => ['invoicereceipt/create'],
            'invoice/cancel'                    => ['invoice/cancelinvoice'],
            'invoice/monthly-report'            => ['invoice/monthlyreport'],
            'invoice/client-invoice'            => ['invoice/clientinvoice'],
            'invoice/download-pdf'              => ['invoice/downloadinvoicepdf'],
            'invoice/print'                     => ['invoice/printinvoice'],
            'invoice/autocomplete-invoice'      => ['invoice/autocompleteinvoice'],
            'invoice/receipts'                  => ['invoicereceipt/admin'],
            'invoice/delete-receipt'            => ['invoicereceipt/delete'],
            'invoice/update-tds'                => ['invoice/updatetds'],
            'invoice/convert-to-invoice'        => ['invoice/converttoinvoice'],
            'invoice/confirminvoice'            => ['invoice/confirminvoice'],
            // Inward Invoices
            'inward-invoice/index'              => ['inwardinvoice/admin'],
            'inward-invoice/create'             => ['inwardinvoice/create'],
            'inward-invoice/update'             => ['inwardinvoice/update'],
            'inward-invoice/view'               => ['inwardinvoice/view'],
            'inward-invoice/delete'             => ['inwardinvoice/delete'],
            'inward-invoice/autocomplete-ledger-category' => ['inwardinvoice/autocompleteledgercategory'],
            'inward-invoice/autocomplete-ledger'          => ['inwardinvoice/autocompleteledger'],
            'inward-invoice/extra-expense'                => ['inwardinvoice/extraexpense'],
            'inward-invoice/get-payment-ledgers'          => ['inwardinvoice/getpaymentledgers'],
            'inward-invoice/delete-attachment'            => ['inwardinvoice/deleteattachment'],
            // Vendors
            'vendor/index'                      => ['vendors/admin'],
            'vendor/create'                     => ['vendors/create'],
            'vendor/update'                     => ['vendors/update'],
            'vendor/view'                       => ['vendors/admin'],
            'vendor/delete'                     => ['vendors/delete'],
            // Bank Details
            'bank-detail/index'                 => ['bankdetails/admin'],
            'bank-detail/create'                => ['bankdetails/create'],
            'bank-detail/update'                => ['bankdetails/update'],
            'bank-detail/view'                  => ['bankdetails/view'],
            'bank-detail/delete'                => ['bankdetails/delete'],
            'bank-detail/set-default'           => ['bankdetails/admin'],
            // Work Reports
            'work-report/index'                 => ['workreport/admin'],
            'work-report/create'                => ['workreport/create'],
            'work-report/update'                => ['workreport/update'],
            'work-report/delete'                => ['workreport/delete'],
            'work-report/my-work-reports'       => ['workreport/myworkreports'],
            'work-report/yesterday'             => ['workreport/lastworkreport'],
            'work-report/developer-current-month' => ['workreport/developercurrentmonthreport'],
            'work-report/cumulative-employee'   => ['workreport/developerbasedcumulativereport'],
            'work-report/monthly-by-project'    => ['workreport/projectbasedmonthlyreport'],
            'work-report/cumulative-client'     => ['workreport/clientbasedcumulativereport'],
            'work-report/monthly-by-employee'   => ['workreport/monthlydeveloperreport'],
            'work-report/overall'               => ['workreport/overallreport'],
            'work-report/performance'           => ['workreport/performancereport'],
            'work-report/export-daily-work-report'       => ['workreport/exportdailyworkreport'],
            'work-report/export-monthly-developer-report' => ['workreport/exportmonthlydeveloperreport'],
            'work-report/export-client-cumulative-report' => ['workreport/exportclientcumulativeworkreport'],
            'work-report/export-developer-cumulative-report' => ['workreport/exportdevelopercumulativeworkreport'],
            'work-report/export-overall-report'           => ['workreport/exportoverallreport'],
            // Tasks
            'task/index'                        => ['task/admin'],
            'task/my-task-list'                 => ['task/mytasklist'],
            'task/my-new-task-list'             => ['task/mynewtasklist'],
            'task/completed-task-list'          => ['task/completedtasklist'],
            'task/assigned-tasks'               => ['task/assignedtasks'],
            'task/task-status'                  => ['task/taskstatus', 'task/changestatus'],
            'task/task-history'                 => ['task/taskhistory'],
            'task/task-logs'                    => ['task/tasklogs'],
            'task/task-start'                   => ['task/taskstart'],
            'task/task-stop'                    => ['task/taskstop'],
            'task/complete-task'                => ['task/completetask'],
            'task/task-accept'                  => ['task/taskaccept'],
            'task/get-subtasks'                 => ['task/getsubtasks'],
            'task/reassign-task'                => ['task/reassigntask', 'task/reassign'],
            'task/full-reassign-task'           => ['task/fullreassigntask', 'task/fullreassign'],
            'task/calc-expected-date'           => ['task/calcexpecteddate'],
            // Developer Tasks
            'tasks/index'                       => ['tasks/admin'],
            'tasks/developer-tasks'             => ['tasks/developertasks', 'task/developertasks'],
            'tasks/change-status'               => ['tasks/changestatus'],
            'tasks/add-work-report'             => ['tasks/taskcreate'],
            'tasks/autocomplete-developer'      => ['tasks/autocompletedeveloper'],
            // Assets — sub-actions also accept the parent index ('assets/assetsindex')
            // and the maintenance/handover parents, so granting the single "Assets"
            // menu item works just like in Yii1 (where granting the parent gave CRUD).
            'asset/index'                       => ['assets/assetsindex'],
            'asset/create'                      => ['assets/assetsadd',    'assets/assetsindex'],
            'asset/update'                      => ['assets/assetsupdate', 'assets/assetsindex'],
            'asset/view'                        => ['assets/assetsview',   'assets/assetsindex'],
            'asset/delete'                      => ['assets/assetsdelete', 'assets/assetsindex'],
            'asset/maintenance'                 => ['assets/assetsmaintenance'],
            // Maintenance sub-actions: Yii1 controller uses underscored route names
            // (actionAdd_assetsmaintenance → assets/add_assetsmaintenance) and the
            // access-permissions UI saves the same string into Employee.access_menus.
            // Without the underscores the in_array() match in checkAccess() fails
            // → 403 even though the developer has the permission.
            'asset/add-maintenance'             => ['assets/add_assetsmaintenance',    'assets/assetsmaintenance'],
            'asset/update-maintenance'          => ['assets/update_assetsmaintenance', 'assets/assetsmaintenance'],
            'asset/view-maintenance'            => ['assets/view_assetsmaintenance',   'assets/assetsmaintenance'],
            'asset/delete-maintenance'          => ['assets/delete_assetsmaintenance', 'assets/assetsmaintenance'],
            'asset/handover'                    => ['assets/assetshandover'],
            'asset/add-handover'                => ['assets/add_assets_handover',    'assets/assetshandover'],
            'asset/update-handover'             => ['assets/update_assets_handover', 'assets/assetshandover'],
            'asset/delete-handover'             => ['assets/delete_assets_handover', 'assets/assetshandover'],
            'asset/handover-history'            => ['assets/assetshandoverview',     'assets/assetshandover'],
            'asset/delete-upload'               => ['assets/deleteupload'],
            // IOT Products
            'iot-product/index'                 => ['iotproducts/admin'],
            'iot-product/create'                => ['iotproducts/create'],
            'iot-product/update'                => ['iotproducts/update'],
            'iot-product/view'                  => ['iotproducts/view'],
            'iot-product/delete'                => ['iotproducts/delete'],
            'iot-product/issued'                => ['iotproducts/issuedproduct'],
            'iot-product/purchased'             => ['iotproducts/purchasedproduct'],
            'iot-product/non-purchased'         => ['iotproducts/nonpurchaseproduct'],
            'iot-product/get-subcategory'       => ['iotproducts/getsubcategory'],
            'iot-product/get-available-quantity'=> ['iotproducts/getavailablequantity'],
            'iot-product/masters'              => ['iotproducts/managedropdown'],
            'iot-product/save-master'          => ['iotproducts/adddropdownvalue'],
            'iot-product/get-master'           => ['iotproducts/adddropdownvalue'],
            'iot-product/delete-master'        => ['iotproducts/removedropdownvalue'],
            'iot-product/create-issue'         => ['iotproducts/createrequest'],
            'iot-product/update-issue'         => ['iotproducts/updaterequest'],
            'iot-product/view-issue'           => ['iotproducts/issuedproduct'],
            'iot-product/change-status'        => ['iotproducts/issuedproduct'],
            'iot-product/create-non-purchase'  => ['iotproducts/createnonpurchaseproduct'],
            'iot-product/update-non-purchase'  => ['iotproducts/nonpurchaseupdate'],
            'iot-product/view-non-purchase'    => ['iotproducts/nonpurchaseproduct'],
            'iot-product/create-purchase'      => ['iotproducts/createpurchaseinvoice'],
            'iot-product/update-purchase'      => ['iotproducts/purchaseupdate'],
            'iot-product/view-purchase'        => ['iotproducts/purchasedproduct'],
            'iot-product/check-quantity'       => ['iotproducts/checkquantity'],
            'iot-product/delete-upload'        => ['iotproducts/deleteupload'],
            'iot-product/download-pdf'         => ['iotproducts/downloadpdf'],
            'iot-product/delete-old-product'   => ['iotproducts/deleteoldproduct'],
            'iot-product/create-return-product'=> ['iotproducts/createreturnproduct'],
            'iot-product/create-return'        => ['iotproducts/createreturnproduct'],
            'iot-product/delete-purchase'      => ['iotproducts/purchasedelete'],
            'iot-product/convert-to-invoice'   => ['iotproducts/converttoactualinvoice'],
            'iot-product/pick-vendor'          => ['iotproducts/createpurchaseinvoice'],
            'iot-product/add-product-popup'    => ['iotproducts/addproductpopup'],
            'iot-product/purchase-view'        => ['iotproducts/purchaseview'],
            'iot-product/delete-attachment'    => ['iotproducts/deleteattachment'],
            'iot-product/extra-expense'        => ['iotproducts/extraexpense'],
            'iot-product/product-history'      => ['iotproducts/productoverview'],
            'iot-product/barcode'              => ['iotproducts/barcode'],
            // PM Tool
            'pm-task/index'                    => ['pmtask/admin'],
            'pm-task/my-task-list'             => ['pmtask/mytasklist'],
            'pm-task/completed-task-list'      => ['pmtask/completedtasklist'],
            'pm-quotation/index'               => ['pmquotations/admin', 'Pmquotations/admin'],
            // Work Report extras
            'work-report/view'                 => ['workreport/view'],
            // Task CRUD
            'task/view'                        => ['task/view'],
            'task/create'                      => ['task/create'],
            'task/update'                      => ['task/update'],
            'task/delete'                      => ['task/delete'],
            // Tasks CRUD
            'tasks/create'                     => ['tasks/create'],
            'tasks/update'                     => ['tasks/update'],
            'tasks/delete'                     => ['tasks/delete'],
            // Asset categories & sub-actions
            'asset/categories'                 => ['assets/managedropdown'],
            // UI lowercases all menu paths; route names are case-insensitive in
            // Yii1 routing but checkAccess() uses strict in_array — match in lowercase.
            'asset/barcode'                    => ['assets/barcode', 'assets/Barcode'],
            'asset/print-multi-barcode'        => ['assets/printmultibarcode'],
            'asset/get-asset-value'            => ['assets/getassetvalue'],
            'asset/get-asset-list'             => ['assets/getassetlist'],
            'asset/save-master'                => ['assets/adddropdownvalue'],
            'asset/delete-master'              => ['assets/removedropdownvalue', 'assets/Removedropdownvalue'],
            'asset/add-vendor'                 => ['assets/addvendor'],
            'asset/delete-maintenance-bill'    => ['assets/deletemaintenancebill'],
            // Handover sub-actions are declared earlier (with the parent
            // 'assets/assetshandover' fallback) so they are not redeclared here.
            'asset/get-subcategory'            => ['assets/getsubcategory'],
            // Settings
            'settings/index'                   => ['mastersettings/admin'],
            'settings/invoice'                 => ['mastersettings/invoicesettings'],
            'settings/accounts-portal'         => ['mastersettings/admin'],
            'settings/access-menus'            => ['mastersettings/admin'],
            // Employee (sub-admin management)
            'employee/index'                   => ['employee/admin'],
            'employee/create'                  => ['employee/create'],
            'employee/view'                    => ['employee/view'],
            'employee/update'                  => ['employee/update'],
            'employee/delete'                  => ['employee/delete'],
            'employee/permission'              => ['employee/permission'],
            'employee/reset-password'          => ['employee/resetpassword'],
            // User management
            'user/index'                       => ['employee/admin'],
            'user/view'                        => ['employee/view'],
            'user/reset-password'              => ['employee/resetpassword'],
            'user/toggle-status'               => ['employee/admin'],
            'user/access-menus'                => ['mastersettings/admin'],
            // Developer extras
            'developer/reset-password'         => ['developer/resetpassword', 'admin/resetpassword'],
            'developer/generate-payslip'       => ['developer/generatepayslip'],
            'developer/update-emp-pin'         => ['developer/updateemppin'],
            'developer/view-emp-pin'           => ['developer/viewemppin'],
            'developer/update-emp-code'        => ['developer/updateempcode'],
            // Attendance extras
            'attendance/view'                  => ['attendance/admin'],
            'attendance/publish-attendance'     => ['attendance/publishattendance'],
            'attendance/verify-attendance'      => ['attendance/verifyattendance'],
            // RFID extras
            'rfid-card/toggle-status'          => ['rfidcards/changestatus', 'rfidcards/admin'],
            // Client extras
            'client/reset-password'            => ['client/accesspermissions'],
            // Work Report extras
            'work-report/client-work-reports'  => ['workreport/clientworkreports'],
            'work-report/project-detail'       => ['workreport/projectdetailsview'],
            // Project extras
            'project/change-phase-status'      => ['project/changephasestatus'],
            'project/delete-phase'             => ['project/deletephase'],
            // Domain extras
            'domain/export-domains'            => ['domains/exportdomains'],
            'domain/import-domains'            => ['domains/importdom'],
            'domain/client-details'            => ['domains/viewclientdetails'],
            'domain/renewal-details'           => ['domains/viewrenewaldetails'],
            'domain/delete-renewal-history'    => ['domains/deletehistory'],
            'domain/add-followup'              => ['domainfollowup/create'],
            'domain/change-status'             => ['domains/domainstatus'],
            // Inward Invoice extras
            'inward-invoice/convert-to-invoice' => ['inwardinvoice/converttoactualinvoice'],
            // Payslip extras
            'payslip/view-month'               => ['payslip/admin'],
            'payslip/delete-month'             => ['payslip/admin'],
            'payslip/selectmonth'              => ['payslip/selectmonth'],
            'payslip/createpayslip'            => ['developer/createpayslip'],
            'payslip/generatepayslip'          => ['developer/generatepayslip'],
            'payslip/calculatesalary'          => ['payslip/calculatesalary'],
            'payslip/create'                   => ['payslip/admin'],
            'payslip/update'                   => ['payslip/update'],
            'payslip/print'                    => ['payslip/view'],
            'payslip/download-pdf'             => ['payslip/downloadpayslip'],
            'payslip/delete'                   => ['payslip/admin'],
            'payslip/export-payslip'           => ['payslip/exportpayslip'],
            // Payslip Upload extras
            'payslip-upload/download'          => ['uploadpayslip/downloadpayslip'],
            'payslip-upload/delete-upload'     => ['uploadpayslip/deleteupload'],
            // Quotation extras
            'quotation/delete-detail'          => ['quotations/admin'],
            'quotation/add-remark'             => ['quotations/addremark'],
            // Proposal extras
            'proposal/get-projects-by-client'  => ['proposals/admin'],
            // PM Tool extras
            'pm-task/view'                     => ['pmtask/admin'],
            'pm-task/create'                   => ['pmtask/admin'],
            'pm-task/update'                   => ['pmtask/admin'],
            'pm-task/delete'                   => ['pmtask/admin'],
            'pm-task/task-status'              => ['pmtask/taskstatus'],
            'pm-task/task-start'               => ['pmtask/taskstart'],
            'pm-task/task-stop'                => ['pmtask/taskstop'],
            'pm-task/reassign-task'            => ['pmtask/reassigntask'],
            'pm-task/get-subtasks'             => ['pmtask/getsubtasks'],
            'pm-task/complete-task'            => ['pmtask/completetask'],
            'pm-task/task-accept'              => ['pmtask/taskaccept'],
            'pm-task/task-logs'                => ['pmtask/tasklogs'],
            // PM Quotation extras
            'pm-quotation/view'                => ['pmquotations/admin'],
            'pm-quotation/create'              => ['pmquotations/admin'],
            'pm-quotation/update'              => ['pmquotations/admin'],
            'pm-quotation/delete'              => ['pmquotations/admin'],
            'pm-quotation/add-sub-quotation'   => ['pmquotations/admin'],
            'pm-quotation/add-remark'          => ['pmquotations/addremark'],
            // Project Management extras
            'project-management/view'          => ['projectmanagement/view'],
            'project-management/create'        => ['projectmanagement/create'],
            'project-management/update'        => ['projectmanagement/update'],
            'project-management/delete'        => ['projectmanagement/delete'],
            'project-management/allproject'    => ['projectmanagement/allproject'],
            // Salary Allowance extras
            'salary-allowance/create-allowance' => ['salaryallowancededuction/createallowance'],
            'salary-allowance/create-deduction' => ['salaryallowancededuction/creatededuction'],
            // Client Quotation / DC / Delivery Product / Quotation Dashboard
            // (new Yii2 modules — no Yii1 equivalents; permission tokens are
            //  introduced here so they can be granted via Settings → Access Menus.)
            'client-quotation/index'            => ['clientquotation/admin'],
            'client-quotation/create'           => ['clientquotation/create'],
            'dc/index'                          => ['dc/admin'],
            'dc/create'                         => ['dc/create'],
            'dc/inward-index'                   => ['dc/inwardadmin'],
            'delivery-product/index'            => ['deliveryproduct/admin'],
            'quotation-dashboard/index'         => ['quotationdashboard/admin'],
        ];
    }

    /** Whether the AMC module is switched on (params['features.amc']). */
    public static function isAmcEnabled(): bool
    {
        return !empty(Yii::$app->params['features.amc']);
    }

    /**
     * Check if the current user has access to a given route.
     *
     * Mirrors Yii1 UserAccess::checkaccess() exactly:
     *   - Admin (1):     always TRUE
     *   - Sub-admin (4): check Employee.access_menus (PHP serialized array)
     *   - Developer (2): check defaults + developer.access_permissions
     *   - Client (3):    check client.access_permissions
     *
     * @param string $route  Format: "controller-id/action-id" (Yii2 style)
     */
    public static function checkAccess(string $route): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        // Disabled modules are hidden from everyone, admins included.
        if (str_starts_with($route, 'amc/') && !self::isAmcEnabled()) {
            return false;
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;

        // Admin — full access
        if ($user->isAdmin()) {
            return true;
        }

        // Implicit children: if the user has the parent route granted, these
        // child routes come along automatically — no separate checkbox needed.
        // Map is one-way (parents are never children) so the recursive
        // checkAccess() below cannot loop.
        //
        // DC view / PDF used to be allowed for every logged-in user (clients
        // included), exposing any challan by id. They now come with any DC
        // workflow permission instead — still no separate checkbox needed.
        $dcPages = ['dc/view', 'dc/challan-pdf'];
        $implicitChildren = [
            'dc/index'                  => $dcPages,
            'dc/create'                 => $dcPages,
            'dc/update'                 => $dcPages,
            'dc/inward-index'           => $dcPages,
            'dc/create-inward'          => $dcPages,
            'dc/add-return'             => $dcPages,
            'quotation-dashboard/index' => array_merge($dcPages, ['quotation-dashboard/list-dc', 'quotation-dashboard/view-dc']),
            'client-quotation/index' => [
                'client-quotation/view',
                'client-quotation/download-latest',
            ],
            // "Show all live data" implies access to the Live board itself, so
            // granting only the all-data permission still shows the menu/page.
            'attendance/live-all-data' => [
                'attendance/live',
            ],
            // Leads: the sample template belongs to the upload page; the
            // assigned list opens its own read-only view; editing a lead
            // includes removing its attachments.
            'lead/upload-lead' => [
                'lead/download-sample',
            ],
            'lead/assigned' => [
                'lead/view-assigned',
                'lead/today',
            ],
            'lead/update' => [
                'lead/delete-attachment',
                'lead/check-duplicate',
                'lead/estimate',
            ],
            // Smart lead helpers: duplicate check + value estimate on the form,
            // the Today board for anyone who works leads, insights with the full list.
            'lead/create' => ['lead/check-duplicate', 'lead/estimate'],
            'lead/index' => ['lead/today', 'lead/insights'],
            'lead/my-leads' => ['lead/today'],
            // Product catalogue: the list includes the detail page and the
            // picker search; editing includes removing the product image.
            'product/index' => [
                'product/view',
                'product/search',
            ],
            'product/update' => [
                'product/remove-image',
                'product/toggle-active',
            ],
            'product/categories' => [
                'product/category-save',
                'product/category-delete',
            ],
            // PO Listing permission: View + Edit of existing POs.
            'po/index' => [
                'po/view',
                'po/update',
                'po/get-client-company',
            ],
            // Add PO is a separate menu permission; needs the client AJAX
            // helper too so the form can populate company names.
            'po/create' => [
                'po/get-client-company',
            ],
            // Granting the "PO Dashboard" permission gives the dashboard plus
            // every action it dispatches (status change, invoices CRUD,
            // payments CRUD, file streaming). It also reuses po/view and
            // po/update for row-level actions, so those come along too.
            'po-dashboard/index' => [
                'po-dashboard/view',
                'po-dashboard/change-status',
                'po-dashboard/set-delivery-date',
                'po-dashboard/add-invoice',
                'po-dashboard/list-invoices',
                'po-dashboard/delete-invoice',
                'po-dashboard/download-invoice',
                'po-dashboard/add-payment',
                'po-dashboard/list-payments',
                'po-dashboard/delete-payment',
                'po-dashboard/export',
                'po/view',
                'po/update',
                'po/get-client-company',
            ],
        ];
        foreach ($implicitChildren as $parent => $children) {
            if (in_array($route, $children, true) && self::checkAccess($parent)) {
                return true;
            }
        }

        // Sub-admin — check Employee.access_menus (PHP serialized array)
        // Mirrors Yii1: Employee.access_menus stores serialized array of Yii1-format routes
        if ($user->isSubAdmin()) {
            $employee = Employee::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if ($employee && !empty($employee->access_menus)) {
                $menus = @unserialize($employee->access_menus);
                if (is_array($menus)) {
                    // Check Yii2 route directly
                    if (in_array($route, $menus, true)) {
                        return true;
                    }
                    // Check Yii1 equivalents (DB may store Yii1-format routes)
                    $yii1Routes = static::getYii2ToYii1RouteMap()[$route] ?? [];
                    foreach ($yii1Routes as $yii1Route) {
                        if (in_array($yii1Route, $menus, true)) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }

        // Developer — default access + custom permissions from DB
        if ($user->isDeveloper()) {
            // Check default permissions first
            $defaults = static::getDeveloperDefaultAccess();
            if (in_array($route, $defaults, true)) {
                return true;
            }

            // Check custom permissions stored in developer.access_permissions
            $developer = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if ($developer) {
                // Special check for accounts portal access (Python & React app)
                if ($route === 'accounts_portal_access') {
                    return !empty($developer->access_permissions_new);
                }

                if (!empty($developer->access_permissions)) {
                    $permissions = array_map('trim', explode(',', $developer->access_permissions));

                    // Check Yii2 route directly (for permissions saved via the new Yii2 UI)
                    if (in_array($route, $permissions, true)) {
                        return true;
                    }

                    // Check Yii1 equivalent routes (for permissions saved via Yii1 or migrated data)
                    $yii1Routes = static::getYii2ToYii1RouteMap()[$route] ?? [];
                    foreach ($yii1Routes as $yii1Route) {
                        if (in_array($yii1Route, $permissions, true)) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        // Client — custom permissions from DB
        if ($user->isClient()) {
            $client = ClientModel::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if ($client && !empty($client->access_permissions)) {
                $permissions = array_map('trim', explode(',', $client->access_permissions));

                // Check Yii2 route directly
                if (in_array($route, $permissions, true)) {
                    return true;
                }

                // Check Yii1 equivalent routes (DB may store Yii1-format routes)
                $yii1Routes = static::getYii2ToYii1RouteMap()[$route] ?? [];
                foreach ($yii1Routes as $yii1Route) {
                    if (in_array($yii1Route, $permissions, true)) {
                        return true;
                    }
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Check access for a specific extra permission (not in defaults).
     * Used for checking custom developer permissions only.
     * Mirrors Yii1 checkSpecificMenuAccess().
     */
    public static function checkSpecificMenuAccess(string $route): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;

        if (!$user->isDeveloper()) {
            return false;
        }

        $developer = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
        if ($developer && !empty($developer->access_permissions)) {
            $permissions = array_map('trim', explode(',', $developer->access_permissions));

            if (in_array($route, $permissions, true)) {
                return true;
            }

            $yii1Routes = static::getYii2ToYii1RouteMap()[$route] ?? [];
            foreach ($yii1Routes as $yii1Route) {
                if (in_array($yii1Route, $permissions, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
