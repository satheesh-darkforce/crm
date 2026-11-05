<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Employee $model */

use yii\bootstrap5\Html;

$this->title = 'Permissions — ' . $model->employee_name;

// Decode stored permissions
$permissions = [];
if (!empty($model->access_menus)) {
    $decoded = @unserialize($model->access_menus);
    if (is_array($decoded)) {
        $permissions = $decoded;
    }
}

$checked = fn(string $route) => in_array($route, $permissions) ? 'checked' : '';

// Menu matrix definition
$modules = [
    'employee' => [
        'label' => 'Employee',
        'actions' => [
            'employee/index'      => 'View',
            'employee/create'     => 'Create',
            'employee/update'     => 'Edit',
            'employee/delete'     => 'Delete',
            'employee/permission' => 'Permissions',
        ],
    ],
    'client' => [
        'label' => 'Clients',
        'actions' => [
            'client/index'  => 'View',
            'client/create' => 'Create',
            'client/update' => 'Edit',
            'client/delete' => 'Delete',
        ],
    ],
    'project' => [
        'label' => 'Projects',
        'actions' => [
            'project/index'  => 'View',
            'project/create' => 'Create',
            'project/update' => 'Edit',
            'project/delete' => 'Delete',
        ],
    ],
    'developer' => [
        'label' => 'Developers',
        'actions' => [
            'developer/index'  => 'View',
            'developer/create' => 'Create',
            'developer/update' => 'Edit',
            'developer/delete' => 'Delete',
        ],
    ],
    'task' => [
        'label' => 'Task Management',
        'actions' => [
            'task/index'  => 'View',
            'task/create' => 'Create',
            'task/update' => 'Edit',
            'task/delete' => 'Delete',
        ],
    ],
    'domain' => [
        'label' => 'Domain Management',
        'actions' => [
            'domain/index'          => 'View',
            'domain/create'         => 'Create',
            'domain/update'         => 'Edit',
            'domain/delete'         => 'Delete',
            'domain/renewal'        => 'Renewal',
            'domain/renewal-history'=> 'Renewal History',
        ],
    ],
    'lead' => [
        'label' => 'CRM / Leads',
        'actions' => [
            'lead/index'                  => 'Manage Leads',
            'lead/create'                 => 'Create',
            'lead/view'                   => 'View',
            'lead/update'                 => 'Edit',
            'lead/delete'                 => 'Delete',
            'lead/change-status'          => 'Change Lead Status',
            'lead/change-lead-status'     => 'Active / Inactive',
            'lead/my-leads'               => 'My Leads',
            'lead/assigned'               => 'Assigned Leads',
            'lead/converted'              => 'Converted Leads',
            'lead/dropped'                => 'Dropped Leads',
            'lead/add-to-client'          => 'Add To Client',
            'lead-followers/index'        => 'Follow Ups',
            'lead-followers/create'       => 'Add Follow Up',
            'lead-followers/update'       => 'Edit Follow Up',
            'lead-followers/view'         => 'View Follow Up',
            'lead-followers/view-attachments' => 'Follow Up Attachments',
            'lead/change-followup-status' => 'Close Follow Up',
            'lead/lead-master'            => 'Lead Master',
            'lead/lead-master-view'       => 'Lead Master — View',
            'lead/create-lead-master'     => 'Lead Master — Create',
            'lead/update-lead-master'     => 'Lead Master — Edit',
            'lead/delete-lead-master'     => 'Lead Master — Delete',
            'lead/upload-lead'            => 'Upload Lead',
            'lead/convert-to-lead'        => 'Convert to Lead',
        ],
    ],
    'invoice' => [
        'label' => 'Invoices',
        'actions' => [
            'invoice/index'        => 'View',
            'invoice/create'       => 'Create',
            'invoice/update'       => 'Edit',
            'invoice/cancel'       => 'Cancel',
            'invoice/print'        => 'Print',
            'invoice/download-pdf' => 'Download PDF',
        ],
    ],
    'work-report' => [
        'label' => 'Work Reports',
        'actions' => [
            'work-report/index'           => 'View Daily Report',
            'work-report/update'          => 'Edit Daily Report',
            'work-report/delete'          => 'Delete Daily Report',
            'work-report/last-work-report'=> 'Yesterday\'s Report',
            'work-report/monthly'         => 'Monthly Report',
        ],
    ],
    'payslip' => [
        'label' => 'Payroll',
        'actions' => [
            'payslip/index'  => 'View Payslips',
            'payslip/create' => 'Create Payslip',
        ],
    ],
    'salary_settings' => [
        'label' => 'Payroll Settings',
        'actions' => [
            'salary-allowance/deductions'     => 'Manage Deductions',
            'salary-allowance/earnings-split' => 'Earnings Split',
        ],
    ],
    'permission' => [
        'label' => 'Permission Requests',
        'actions' => [
            'permission/index'  => 'List',
            'permission/create' => 'Create',
            'permission/decide' => 'Approve / Reject',
        ],
    ],
    'asset' => [
        'label' => 'Assets',
        'actions' => [
            'asset/index'  => 'View',
            'asset/create' => 'Create',
            'asset/update' => 'Edit',
            'asset/delete' => 'Delete',
        ],
    ],
    'quotation' => [
        'label' => 'Quotation',
        'actions' => [
            'quotation-dashboard/index'         => 'Dashboard',
            'client-quotation/index'            => 'List',
            'client-quotation/create'           => 'Create',
            'client-quotation/add-child'        => 'Add Revision',
            'client-quotation/cancel'           => 'Cancel',
        ],
    ],
    'dc' => [
        'label' => 'DC (Delivery Challan)',
        'actions' => [
            'dc/index'                 => 'List',
            'dc/create'                => 'Create',
            'dc/update'                => 'Edit',
            'dc/cancel'                => 'Cancel',
            'dc/save-return'           => 'Mark Returned',
            'delivery-product/index'   => 'Products — List',
            'delivery-product/create'  => 'Products — Create',
            'delivery-product/update'  => 'Products — Update',
            'delivery-product/delete'  => 'Products — Delete',
        ],
    ],
    'inward-dc' => [
        'label' => 'Inward DC',
        'actions' => [
            'dc/inward-index'  => 'List',
            'dc/create-inward' => 'Create',
            'dc/inward-view'   => 'View',
            'dc/add-return'    => 'Add Return',
            'dc/update-return' => 'Edit Return',
        ],
    ],
    'po' => [
        'label' => 'Purchase Order',
        'actions' => [
            'po-dashboard/index' => 'PO Dashboard',
            'po/create'          => 'Add PO',
            'po/index'           => 'PO Listing',
        ],
    ],
];
?>
<style>
.perm-table th{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;font-weight:600;padding:.5rem .75rem}
.perm-table td{padding:.4rem .75rem;vertical-align:middle;font-size:.875rem}
.perm-table tbody tr:hover{background:#f8f9ff}
.module-label{font-weight:600;font-size:.875rem;min-width:160px}
.check-all-btn{cursor:pointer;font-size:.75rem;color:#0d6efd;text-decoration:underline;border:none;background:none;padding:0}
</style>

<div class="ui-page employee-permissions">
    <div class="ui-head">
        <div>
            <h1>Permissions — <?= Html::encode($model->employee_name) ?></h1>
            <div class="ui-sub"><?= Html::encode($model->designation) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <form method="post" action="">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table perm-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:160px">Module</th>
                            <th style="width:80px;text-align:center">All</th>
                            <th>Permissions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($modules as $key => $module): ?>
                    <tr>
                        <td class="module-label"><?= Html::encode($module['label']) ?></td>
                        <td style="text-align:center">
                            <button type="button" class="check-all-btn" onclick="toggleAll('<?= $key ?>')">All</button>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($module['actions'] as $route => $label): ?>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input perm-<?= $key ?>" type="checkbox"
                                           name="access_menus[]"
                                           value="<?= Html::encode($route) ?>"
                                           id="perm_<?= str_replace(['/', '-'], '_', $route) ?>"
                                        <?= $checked($route) ?>>
                                    <label class="form-check-label" for="perm_<?= str_replace(['/', '-'], '_', $route) ?>">
                                        <?= Html::encode($label) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top d-flex gap-2 py-2">
                <button type="submit" class="btn btn-primary btn-sm">Save Permissions</button>
                <?= Html::a('Cancel', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
        </div>
    </form>
</div>

<script>
function toggleAll(module) {
    const boxes = document.querySelectorAll('.perm-' + module);
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
}
</script>
