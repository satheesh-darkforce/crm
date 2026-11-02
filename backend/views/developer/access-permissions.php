<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer $developer */
/** @var array $permissions  flat array of granted route strings (Yii1 format, from DB) */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Access Permissions — ' . $developer->developer_name;

// Route strings match Yii1 DB format (controller/action in Yii1 camelCase style)
$menus = [
    [
        'main_menu' => 'Dashboard',
        'menus' => [
            ['menu_name' => 'Live Attendance',     'menu_path' => 'attendance/live'             ],
            ['menu_name' => 'Show all live data',  'menu_path' => 'attendance/live-all-data'    ],
            ['menu_name' => '',  'menu_path' => 'admin/dashboard'],
        ],
    ],
    [
        'main_menu' => 'Clients',
        'menus' => [
            ['menu_name' => '',                     'menu_path' => 'client/admin'              ],
            ['menu_name' => 'Create',               'menu_path' => 'client/create'             ],
            ['menu_name' => 'Update',               'menu_path' => 'client/update'             ],
            ['menu_name' => 'Delete',               'menu_path' => 'client/delete'             ],
            ['menu_name' => 'View',                 'menu_path' => 'client/view'               ],
            ['menu_name' => 'Reset Password',       'menu_path' => 'admin/resetpassword'       ],
            ['menu_name' => 'Access Permissions',   'menu_path' => 'client/accesspermissions'  ],
        ],
    ],
    [
        'main_menu' => 'Domains',
        'menus' => [
            ['menu_name' => '',                'menu_path' => 'domains/admin'          ],
            ['menu_name' => 'Create',          'menu_path' => 'domains/create'         ],
            ['menu_name' => 'Update',          'menu_path' => 'domains/update'         ],
            ['menu_name' => 'Delete',          'menu_path' => 'domains/delete'         ],
            ['menu_name' => 'View',            'menu_path' => 'domains/view'           ],
            ['menu_name' => 'Export',          'menu_path' => 'domains/exportdomains'  ],
            ['menu_name' => 'Renewal',         'menu_path' => 'domains/renewal'        ],
            ['menu_name' => 'Renewal History', 'menu_path' => 'domains/renewalhistory' ],
            ['menu_name' => 'Delete History',  'menu_path' => 'domains/deletehistory'  ],
            ['menu_name' => 'View History',    'menu_path' => 'domains/viewhistory'    ],
            ['menu_name' => 'View Client',     'menu_path' => 'domains/viewclientdetails'  ],
            ['menu_name' => 'View Renewal',    'menu_path' => 'domains/viewrenewaldetails' ],
            ['menu_name' => 'Domain Status',   'menu_path' => 'domains/domainstatus'   ],
        ],
    ],
    [
        'main_menu' => 'Domain Followups',
        'menus' => [
            ['menu_name' => '',       'menu_path' => 'followup/admin'  ],
            ['menu_name' => 'Create', 'menu_path' => 'followup/create' ],
            ['menu_name' => 'Update', 'menu_path' => 'followup/update' ],
            ['menu_name' => 'Delete', 'menu_path' => 'followup/delete' ],
            ['menu_name' => 'View',   'menu_path' => 'followup/view'   ],
        ],
    ],
    [
        'main_menu' => 'Projects',
        'menus' => [
            ['menu_name' => '',                   'menu_path' => 'project/admin'           ],
            ['menu_name' => 'Create',             'menu_path' => 'project/create'          ],
            ['menu_name' => 'Update',             'menu_path' => 'project/update'          ],
            ['menu_name' => 'Delete',             'menu_path' => 'project/delete'          ],
            ['menu_name' => 'View',               'menu_path' => 'project/view'            ],
            ['menu_name' => 'Change Status',      'menu_path' => 'project/changestatus'    ],
            ['menu_name' => 'Add Payment Detail', 'menu_path' => 'project/addprojectpayment'],
            ['menu_name' => 'Export',             'menu_path' => 'project/getexportprojects'],
        ],
    ],
    [
        'main_menu' => 'Employees',
        'menus' => [
            ['menu_name' => '',                        'menu_path' => 'developer/admin'                ],
            ['menu_name' => 'Create',                  'menu_path' => 'developer/create'               ],
            ['menu_name' => 'Update',                  'menu_path' => 'developer/update'               ],
            ['menu_name' => 'Delete',                  'menu_path' => 'developer/delete'               ],
            ['menu_name' => 'View',                    'menu_path' => 'developer/view'                 ],
            ['menu_name' => 'Create Payslip',          'menu_path' => 'developer/createpayslip'        ],
            ['menu_name' => 'Generate Payslip',        'menu_path' => 'developer/generatepayslip'      ],
            ['menu_name' => 'Allowance Deduction',     'menu_path' => 'developer/allowancededuction'   ],
            ['menu_name' => 'Access Permissions',      'menu_path' => 'developer/accesspermissions'    ],
            ['menu_name' => 'Access Permissions New',  'menu_path' => 'developer/accesspermissionsnew' ],
            ['menu_name' => 'APK Access Permissions',  'menu_path' => 'developer/apkaccesspermissions' ],
            ['menu_name' => 'Ex-Employees',            'menu_path' => 'developer/exemployees'          ],
            ['menu_name' => 'Export Employees',        'menu_path' => 'developer/getexportemployees'   ],
            ['menu_name' => 'Export Ex-Employees',     'menu_path' => 'developer/getexportexemployees' ],
            ['menu_name' => 'Update RFID',             'menu_path' => 'developer/updaterfid'           ],
        ],
    ],
    [
        'main_menu' => 'Attendance',
        'menus' => [
            ['menu_name' => 'All Attendance',      'menu_path' => 'attendance/allattendance'    ],
            ['menu_name' => 'Monthly Attendance',  'menu_path' => 'attendance/monthlyattendance'],
            ['menu_name' => 'Update Remarks',      'menu_path' => 'attendance/updateremarks'    ],
            ['menu_name' => 'Leave Settings',      'menu_path' => 'attendance/leavesettings'    ],
            ['menu_name' => 'Holidays Settings',   'menu_path' => 'holidayssetup/admin'         ],
            ['menu_name' => 'Manage RFID cards',   'menu_path' => 'rfidcards/admin'             ],
        ],
    ],
    [
        'main_menu' => 'Leave Requests',
        'menus' => [
            ['menu_name' => '',                    'menu_path' => 'leaverequests/admin'            ],
            ['menu_name' => 'Create',              'menu_path' => 'leaverequests/create'           ],
            ['menu_name' => 'Update',              'menu_path' => 'leaverequests/update'           ],
            ['menu_name' => 'Delete',              'menu_path' => 'leaverequests/delete'           ],
            ['menu_name' => 'View',                'menu_path' => 'leaverequests/view'             ],
            ['menu_name' => 'All Leave Requests',  'menu_path' => 'leaverequests/allleaverequests' ],
            ['menu_name' => 'Change Status',       'menu_path' => 'leaverequests/changestatus'     ],
        ],
    ],
    [
        'main_menu' => 'Permission Requests',
        'menus' => [
            ['menu_name' => 'List',                'menu_path' => 'permission/index'  ],
            ['menu_name' => 'Create',              'menu_path' => 'permission/create' ],
            ['menu_name' => 'Approve / Reject',    'menu_path' => 'permission/decide' ],
        ],
    ],
    [
        'main_menu' => 'Additional Works',
        'menus' => [
            ['menu_name' => '',       'menu_path' => 'additionalworks/admin'  ],
            ['menu_name' => 'Create', 'menu_path' => 'additionalworks/create' ],
            ['menu_name' => 'Update', 'menu_path' => 'additionalworks/update' ],
            ['menu_name' => 'Delete', 'menu_path' => 'additionalworks/delete' ],
            ['menu_name' => 'View',   'menu_path' => 'additionalworks/view'   ],
        ],
    ],
    [
        'main_menu' => 'Payslips',
        'menus' => [
            ['menu_name' => '',                        'menu_path' => 'payslip/admin'               ],
            ['menu_name' => 'Manage Payslips',         'menu_path' => 'uploadpayslip/admin'         ],
            ['menu_name' => 'Upload',                  'menu_path' => 'uploadpayslip/create'        ],
            ['menu_name' => 'View Monthly Payslip',    'menu_path' => 'uploadpayslip/view'          ],
            ['menu_name' => 'View Individual Payslip', 'menu_path' => 'uploadpayslip/viewpayslip'   ],
            ['menu_name' => 'Download',                'menu_path' => 'uploadpayslip/downloadpayslip'],
            ['menu_name' => 'Delete',                  'menu_path' => 'uploadpayslip/delete'        ],
        ],
    ],
    // Leads — Yii1-format routes; UserAccess maps the Yii2 routes onto these.
    [
        'main_menu' => 'Leads',
        'menus' => [
            ['menu_name' => '',                   'menu_path' => 'lead/admin'              ],
            ['menu_name' => 'Create',             'menu_path' => 'lead/create'             ],
            ['menu_name' => 'Update',             'menu_path' => 'lead/update'             ],
            ['menu_name' => 'View',               'menu_path' => 'lead/view'               ],
            ['menu_name' => 'Delete',             'menu_path' => 'lead/delete'             ],
            ['menu_name' => 'Change Status',      'menu_path' => 'lead/changestatus'       ],
            ['menu_name' => 'Active / Inactive',  'menu_path' => 'lead/changeleadstatus'   ],
            ['menu_name' => 'Converted',          'menu_path' => 'lead/converted'          ],
            ['menu_name' => 'Dropped',            'menu_path' => 'lead/dropped'            ],
            ['menu_name' => 'Add To Client',      'menu_path' => 'lead/addtoclient'        ],
            ['menu_name' => 'Follow Ups',         'menu_path' => 'leadfollowers/admin'     ],
            ['menu_name' => 'Create Follow Up',   'menu_path' => 'leadfollowers/create'    ],
            ['menu_name' => 'Update Follow Up',   'menu_path' => 'leadfollowers/update'    ],
            ['menu_name' => 'View Follow Up',     'menu_path' => 'leadfollowers/view'      ],
            ['menu_name' => 'Close Follow Up',    'menu_path' => 'lead/changefollowupstatus'],
            ['menu_name' => 'View Attachments',   'menu_path' => 'leadfollowers/viewattachments'],
            ['menu_name' => 'Assigned Lead',      'menu_path' => 'lead/assigned'           ],
            ['menu_name' => 'My Lead',            'menu_path' => 'lead/myleads'            ],
            ['menu_name' => 'Lead Master',        'menu_path' => 'lead/leadmaster'         ],
            ['menu_name' => 'Lead Master View',   'menu_path' => 'lead/leadmasterview'     ],
            ['menu_name' => 'Create Lead Master', 'menu_path' => 'lead/createleadmaster'   ],
            ['menu_name' => 'Update Lead Master', 'menu_path' => 'lead/updateleadmaster'   ],
            ['menu_name' => 'Upload Lead Master', 'menu_path' => 'lead/uploadlead'         ],
            ['menu_name' => 'Delete Lead Master', 'menu_path' => 'lead/deleteleadmaster'   ],
            ['menu_name' => 'Convert to Lead',    'menu_path' => 'lead/converttolead'      ],
        ],
    ],
    [
        'main_menu' => 'Invoices',
        'menus' => [
            ['menu_name' => '',                    'menu_path' => 'invoice/admin'           ],
            ['menu_name' => 'Create',              'menu_path' => 'invoice/create'          ],
            ['menu_name' => 'Update',              'menu_path' => 'invoice/update'          ],
            ['menu_name' => 'Delete',              'menu_path' => 'invoice/delete'          ],
            ['menu_name' => 'View',                'menu_path' => 'invoice/view'            ],
            ['menu_name' => 'Add Invoice',         'menu_path' => 'invoice/addinvoice'      ],
            ['menu_name' => 'Confirm Invoice',     'menu_path' => 'invoice/confirminvoice'  ],
            ['menu_name' => 'Download Invoice',    'menu_path' => 'invoice/downloadinvoicepdf'],
            ['menu_name' => 'Print Invoice',       'menu_path' => 'invoice/printinvoice'    ],
            ['menu_name' => 'Convert To Invoice',  'menu_path' => 'invoice/converttoinvoice'],
            ['menu_name' => 'Cancel Invoice',      'menu_path' => 'invoice/cancelinvoice'   ],
            ['menu_name' => 'Monthly Report',      'menu_path' => 'invoice/monthlyreport'   ],
            ['menu_name' => 'Update TDS',          'menu_path' => 'invoice/updatetds'       ],
            ['menu_name' => 'Proforma Invoice',    'menu_path' => 'invoice/proforma'        ],
            ['menu_name' => 'Add Proforma Invoice','menu_path' => 'invoice/addproformainvoice'],
        ],
    ],
    [
        'main_menu' => 'Invoice Receipts',
        'menus' => [
            ['menu_name' => '',                   'menu_path' => 'invoicereceipt/admin'              ],
            ['menu_name' => 'Create',             'menu_path' => 'invoicereceipt/create'             ],
            ['menu_name' => 'Update',             'menu_path' => 'invoicereceipt/update'             ],
            ['menu_name' => 'Delete',             'menu_path' => 'invoicereceipt/delete'             ],
            ['menu_name' => 'View',               'menu_path' => 'invoicereceipt/view'               ],
            ['menu_name' => 'Add Receipt',        'menu_path' => 'invoicereceipt/addreceipt'         ],
            ['menu_name' => 'Download Invoice',   'menu_path' => 'invoicereceipt/downloadinvoicepdf' ],
            ['menu_name' => 'Delete Attachment',  'menu_path' => 'invoicereceipt/deleteattachment'   ],
        ],
    ],
    [
        'main_menu' => 'Inward Invoices',
        'menus' => [
            ['menu_name' => '',                          'menu_path' => 'inwardinvoice/admin'                 ],
            ['menu_name' => 'Create',                    'menu_path' => 'inwardinvoice/create'                ],
            ['menu_name' => 'Update',                    'menu_path' => 'inwardinvoice/update'                ],
            ['menu_name' => 'Delete',                    'menu_path' => 'inwardinvoice/delete'                ],
            ['menu_name' => 'View',                      'menu_path' => 'inwardinvoice/view'                  ],
            ['menu_name' => 'Add Inward Invoice',        'menu_path' => 'inwardinvoice/addinwardinvoice'      ],
            ['menu_name' => 'Delete Attachment',         'menu_path' => 'inwardinvoice/deleteattachment'      ],
            ['menu_name' => 'Change Invoice Role',       'menu_path' => 'inwardinvoice/changestatus'          ],
            ['menu_name' => 'Convert to Actual Invoice', 'menu_path' => 'inwardinvoice/converttoactualinvoice'],
        ],
    ],
    [
        'main_menu' => 'Quotation',
        'menus' => [
            ['menu_name' => 'Dashboard',        'menu_path' => 'quotation-dashboard/index'       ],
            ['menu_name' => 'List',             'menu_path' => 'client-quotation/index'          ],
            ['menu_name' => 'Create',           'menu_path' => 'client-quotation/create'         ],
            ['menu_name' => 'Add Revision',     'menu_path' => 'client-quotation/add-child'      ],
            ['menu_name' => 'Cancel',           'menu_path' => 'client-quotation/cancel'         ],
        ],
    ],
    [
        'main_menu' => 'DC (Delivery Challan)',
        'menus' => [
            ['menu_name' => 'List',             'menu_path' => 'dc/index'                    ],
            ['menu_name' => 'Create',           'menu_path' => 'dc/create'                   ],
            ['menu_name' => 'Edit',             'menu_path' => 'dc/update'                   ],
            ['menu_name' => 'Cancel',           'menu_path' => 'dc/cancel'                   ],
            ['menu_name' => 'Mark Returned',    'menu_path' => 'dc/save-return'              ],
            ['menu_name' => 'Products — List',  'menu_path' => 'delivery-product/index'      ],
            ['menu_name' => 'Products — Create','menu_path' => 'delivery-product/create'     ],
            ['menu_name' => 'Products — Update','menu_path' => 'delivery-product/update'     ],
            ['menu_name' => 'Products — Delete','menu_path' => 'delivery-product/delete'     ],
        ],
    ],
    [
        'main_menu' => 'Inward DC',
        'menus' => [
            ['menu_name' => 'List',        'menu_path' => 'dc/inward-index'  ],
            ['menu_name' => 'Create',      'menu_path' => 'dc/create-inward' ],
            ['menu_name' => 'View',        'menu_path' => 'dc/inward-view'   ],
            ['menu_name' => 'Add Return',  'menu_path' => 'dc/add-return'    ],
            ['menu_name' => 'Edit Return', 'menu_path' => 'dc/update-return' ],
        ],
    ],
    [
        'main_menu' => 'Purchase Order',
        'menus' => [
            ['menu_name' => 'PO Dashboard', 'menu_path' => 'po-dashboard/index' ],
            ['menu_name' => 'Add PO',       'menu_path' => 'po/create'          ],
            ['menu_name' => 'PO Listing',   'menu_path' => 'po/index'           ],
        ],
    ],
    [
        'main_menu' => 'Bank details',
        'menus' => [
            ['menu_name' => '',       'menu_path' => 'bankdetails/admin'  ],
            ['menu_name' => 'Create', 'menu_path' => 'bankdetails/create' ],
            ['menu_name' => 'Update', 'menu_path' => 'bankdetails/update' ],
            ['menu_name' => 'Delete', 'menu_path' => 'bankdetails/delete' ],
            ['menu_name' => 'View',   'menu_path' => 'bankdetails/view'   ],
        ],
    ],
    [
        'main_menu' => 'Proposals',
        'menus' => [
            ['menu_name' => '',       'menu_path' => 'proposals/admin'  ],
            ['menu_name' => 'Create', 'menu_path' => 'proposals/create' ],
            ['menu_name' => 'Update', 'menu_path' => 'proposals/update' ],
            ['menu_name' => 'Delete', 'menu_path' => 'proposals/delete' ],
            ['menu_name' => 'View',   'menu_path' => 'proposals/view'   ],
        ],
    ],
    [
        'main_menu' => 'Payroll Settings',
        'menus' => [
            ['menu_name' => 'Manage Deductions',  'menu_path' => 'salaryallowancededuction/deductions'      ],
            ['menu_name' => 'Earnings Split',     'menu_path' => 'salaryallowancededuction/earningssplit'   ],
            ['menu_name' => 'Create Deduction',   'menu_path' => 'salaryallowancededuction/creatededuction' ],
            ['menu_name' => 'Update',             'menu_path' => 'salaryallowancededuction/update'          ],
            ['menu_name' => 'Delete',             'menu_path' => 'salaryallowancededuction/delete'          ],
        ],
    ],
    [
        'main_menu' => 'Vendors',
        'menus' => [
            ['menu_name' => '',                    'menu_path' => 'vendors/admin'      ],
            ['menu_name' => 'Create',              'menu_path' => 'vendors/create'     ],
            ['menu_name' => 'Update',              'menu_path' => 'vendors/update'     ],
            ['menu_name' => 'Delete',              'menu_path' => 'vendors/delete'     ],
            ['menu_name' => 'Add Inward Invoice',  'menu_path' => 'inwardinvoice/create'],
        ],
    ],
    [
        'main_menu' => 'Work Reports',
        'menus' => [
            ['menu_name' => 'Create',                               'menu_path' => 'workreport/create'                          ],
            ['menu_name' => 'Update',                               'menu_path' => 'workreport/update'                          ],
            ['menu_name' => 'Manage Work Reports of Others',        'menu_path' => 'workreport/admin'                           ],
            ['menu_name' => 'Employee Based Cumulative Report',     'menu_path' => 'workreport/developerbasedcumulativereport'   ],
            ['menu_name' => 'Export Employee Based Cumulative',     'menu_path' => 'workreport/exportdevelopercumulativeworkreport'],
            ['menu_name' => 'Client Based Cumulative Report',       'menu_path' => 'workreport/clientbasedcumulativereport'     ],
            ['menu_name' => 'Export Client Based Cumulative',       'menu_path' => 'workreport/exportclientcumulativeworkreport'],
            ['menu_name' => 'Export Daily Work Report',             'menu_path' => 'workreport/exportdailyworkreport'           ],
            ['menu_name' => 'Employee Yesterdays Work Report',      'menu_path' => 'workreport/lastworkreport'                  ],
            ['menu_name' => 'Employee Monthly Work Report',         'menu_path' => 'workreport/developercurrentmonthreport'     ],
            ['menu_name' => 'Project based monthly work report',    'menu_path' => 'workreport/projectbasedmonthlyreport'       ],
            ['menu_name' => 'Project Detail View',                  'menu_path' => 'workreport/projectdetailsview'              ],
            ['menu_name' => 'Developer-wise Project Report',        'menu_path' => 'workreport/developerwiseprojectcumulativereport'],
            ['menu_name' => 'Overall Report',                       'menu_path' => 'workreport/overallreport'                   ],
            ['menu_name' => 'Export Overall Report',                'menu_path' => 'workreport/exportoverallreport'             ],
            ['menu_name' => 'Export Monthly Developer Report',      'menu_path' => 'workreport/exportmonthlydeveloperreport'    ],
            ['menu_name' => 'Performance Report',                   'menu_path' => 'workreport/performancereport'               ],
            ['menu_name' => 'Download Performance Report',          'menu_path' => 'workreport/performancereportdownload'        ],
        ],
    ],
    [
        'main_menu' => 'Assets',
        'menus' => [
            ['menu_name' => '',                             'menu_path' => 'assets/assetsindex'             ],
            ['menu_name' => 'Create',                       'menu_path' => 'assets/assetsadd'               ],
            ['menu_name' => 'View',                         'menu_path' => 'assets/assetsview'              ],
            ['menu_name' => 'Update',                       'menu_path' => 'assets/assetsupdate'            ],
            ['menu_name' => 'Delete',                       'menu_path' => 'assets/assetsdelete'            ],
            ['menu_name' => 'Bar Code',                     'menu_path' => 'assets/barcode'                 ],
            ['menu_name' => 'Assets Maintenance',           'menu_path' => 'assets/assetsmaintenance'       ],
            ['menu_name' => 'Create Assets Maintenance',    'menu_path' => 'assets/add_assetsmaintenance'   ],
            ['menu_name' => 'View Assets Maintenance',      'menu_path' => 'assets/view_assetsmaintenance'  ],
            ['menu_name' => 'Update Assets Maintenance',    'menu_path' => 'assets/update_assetsmaintenance'],
            ['menu_name' => 'Delete Assets Maintenance',    'menu_path' => 'assets/delete_assetsmaintenance'],
            ['menu_name' => 'Assets Categories',            'menu_path' => 'assets/managedropdown'          ],
            ['menu_name' => 'Add & Edit Categories',        'menu_path' => 'assets/adddropdownvalue'        ],
            ['menu_name' => 'Delete Categories',            'menu_path' => 'assets/removedropdownvalue'     ],
            ['menu_name' => 'Assets Handover',              'menu_path' => 'assets/assetshandover'          ],
            ['menu_name' => 'Create Assets Handover',       'menu_path' => 'assets/add_assets_handover'     ],
            ['menu_name' => 'View Assets Handover',         'menu_path' => 'assets/assetshandoverview'      ],
            ['menu_name' => 'Update Assets Handover',       'menu_path' => 'assets/update_assets_handover'  ],
            ['menu_name' => 'Delete Assets Handover',       'menu_path' => 'assets/delete_assets_handover'  ],
        ],
    ],
    [
        'main_menu' => 'IOT',
        'menus' => [
            ['menu_name' => 'Products',                  'menu_path' => 'iotproducts/admin'                  ],
            ['menu_name' => 'Create',                    'menu_path' => 'iotproducts/create'                 ],
            ['menu_name' => 'View',                      'menu_path' => 'iotproducts/view'                   ],
            ['menu_name' => 'Update',                    'menu_path' => 'iotproducts/update'                 ],
            ['menu_name' => 'Delete',                    'menu_path' => 'iotproducts/delete'                 ],
            ['menu_name' => 'Product History',           'menu_path' => 'iotproducts/productoverview'        ],
            ['menu_name' => 'IOT Masters',               'menu_path' => 'iotproducts/managedropdown'         ],
            ['menu_name' => 'Add & Edit Categories',     'menu_path' => 'iotproducts/adddropdownvalue'       ],
            ['menu_name' => 'Delete Categories',         'menu_path' => 'iotproducts/removedropdownvalue'    ],
            ['menu_name' => 'Print Barcode',             'menu_path' => 'iotproducts/barcode'                ],
            ['menu_name' => 'Print Multiple Barcode',    'menu_path' => 'iotproducts/printmultibarcode'      ],
            ['menu_name' => 'Issued Products',           'menu_path' => 'iotproducts/issuedproduct'          ],
            ['menu_name' => 'Add Issued Products',       'menu_path' => 'iotproducts/createrequest'          ],
            ['menu_name' => 'Update Issued Products',    'menu_path' => 'iotproducts/updaterequest'          ],
            ['menu_name' => 'Change Consumption Status', 'menu_path' => 'iotproducts/changestatus'           ],
            ['menu_name' => 'Issued Products View',      'menu_path' => 'iotproducts/issuedproductview'      ],
            ['menu_name' => 'Non Purchase',              'menu_path' => 'iotproducts/nonpurchaseproduct'     ],
            ['menu_name' => 'Create Non Purchase',       'menu_path' => 'iotproducts/createnonpurchaseproduct'],
            ['menu_name' => 'Update Non Purchase',       'menu_path' => 'iotproducts/nonpurchaseupdate'      ],
            ['menu_name' => 'Delete Non Purchase',       'menu_path' => 'iotproducts/nonpurchasedelete'      ],
            ['menu_name' => 'Non Purchase View',         'menu_path' => 'iotproducts/nonpurchaseproductview' ],
            ['menu_name' => 'Purchase Product',          'menu_path' => 'iotproducts/purchasedproduct'       ],
            ['menu_name' => 'Create Purchase',           'menu_path' => 'iotproducts/createpurchaseinvoice'  ],
            ['menu_name' => 'Update Purchase',           'menu_path' => 'iotproducts/purchaseupdate'         ],
            ['menu_name' => 'Delete Purchase',           'menu_path' => 'iotproducts/purchasedelete'         ],
            ['menu_name' => 'Purchase View',             'menu_path' => 'iotproducts/purchaseview'           ],
            ['menu_name' => 'Change Invoice Role',       'menu_path' => 'iotproducts/converttoactualinvoice' ],
        ],
    ],
];
?>

<div class="developer-access-permissions">
    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <?php
        $flashMsg = Yii::$app->session->getFlash('success');
        $this->registerJs("Swal.fire({toast:true,position:'top-end',icon:'success',title:" . json_encode($flashMsg) . ",showConfirmButton:false,timer:3000,timerProgressBar:true});");
        ?>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Access Permissions</h5>
            <small class="text-muted"><?= Html::encode($developer->developer_name) ?></small>
        </div>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <form method="post" action="<?= Url::to(['/developer/access-permissions', 'id' => $developer->id]) ?>">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>

            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 permission_table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:38px"><input type="checkbox" class="checkAll" title="Toggle all"></th>
                            <th style="width:38px">#</th>
                            <th style="min-width:140px">Menu Name</th>
                            <th>Permissions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($menus as $idx => $group): ?>
                        <tr>
                            <td><input type="checkbox" class="checkAllRow"></td>
                            <td class="text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-semibold small"><?= Html::encode($group['main_menu']) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($group['menus'] as $item):
                                    $path    = $item['menu_path'];
                                    $label   = $item['menu_name'] !== '' ? $item['menu_name'] : 'List';
                                    $checked = in_array($path, $permissions);
                                ?>
                                    <label class="d-flex align-items-center gap-1 small" style="white-space:nowrap">
                                        <input type="checkbox"
                                               name="accessoptions[]"
                                               value="<?= Html::encode($path) ?>"
                                               class="row-check"
                                               <?= $checked ? 'checked' : '' ?>>
                                        <?= Html::encode($label) ?>
                                    </label>
                                <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-transparent py-3">
                <button type="submit" class="btn btn-primary btn-sm">Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<?php $this->registerJs(<<<JS
document.querySelector('.checkAll').addEventListener('change', function () {
    var checked = this.checked;
    document.querySelectorAll('.row-check, .checkAllRow').forEach(function (cb) { cb.checked = checked; });
});

document.querySelectorAll('.checkAllRow').forEach(function (rowCb) {
    rowCb.addEventListener('change', function () {
        var boxes = this.closest('tr').querySelectorAll('.row-check');
        boxes.forEach(function (cb) { cb.checked = rowCb.checked; });
    });
});
JS); ?>
