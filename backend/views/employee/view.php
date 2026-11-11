<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Employee $model */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$canManagePermissions = UserAccess::checkAccess('employee/permission');

$this->title = $model->employee_name;

$initials = strtoupper(substr($model->employee_name, 0, 1))
          . (strpos($model->employee_name, ' ') !== false
             ? strtoupper(substr(strstr($model->employee_name, ' '), 1, 1)) : '');

// Decode access_menus for display
$permissions = [];
if (!empty($model->access_menus)) {
    $decoded = @unserialize($model->access_menus);
    if (is_array($decoded)) {
        $permissions = $decoded;
    }
}

// Group by module for readable display
$moduleLabels = [
    'employee'    => 'Employee',
    'client'      => 'Clients',
    'project'     => 'Projects',
    'developer'   => 'Developers',
    'task'        => 'Tasks',
    'domain'      => 'Domains',
    'lead'        => 'CRM / Leads',
    'invoice'     => 'Invoices',
    'work-report' => 'Work Reports',
    'payslip'     => 'Payroll',
    'asset'       => 'Assets',
];
$actionLabels = [
    'index' => 'View', 'create' => 'Create', 'update' => 'Edit', 'delete' => 'Delete',
    'permission' => 'Permissions', 'cancel' => 'Cancel',
    'renewal' => 'Renewal', 'renewal-history' => 'Renewal History',
    'last-work-report' => 'Yesterday\'s Report', 'monthly' => 'Monthly Report',
];
$grouped = [];
foreach ($permissions as $route) {
    $parts = explode('/', $route, 2);
    if (count($parts) === 2) {
        $grouped[$parts[0]][] = $actionLabels[$parts[1]] ?? $parts[1];
    }
}
?>
<style>
.emp-avatar-lg{width:56px;height:56px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;color:#fff;flex-shrink:0;background:#4da6ff}
.detail-label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;font-weight:600;margin-bottom:.15rem}
.detail-value{font-size:.9rem;color:#212529}
.perm-badge{font-size:.72rem;padding:.2rem .45rem}
/* Employee Details — themed card with gradient header + grouped info tiles */
.emp-detail-card{border:0;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(15,23,42,.06)}
.emp-detail-banner{
    background:linear-gradient(120deg,#7366ff 0%,#3b82f6 100%);
    color:#fff;padding:22px 24px 64px;position:relative;
}
.emp-detail-banner::after{content:"";position:absolute;right:-40px;top:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.07)}
.emp-detail-banner::before{content:"";position:absolute;left:-30px;bottom:-60px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.06)}
.emp-detail-banner h6{font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;opacity:.85;margin:0 0 6px;font-weight:600}
.emp-detail-banner h3{margin:0;font-weight:700;font-size:1.45rem}
.emp-detail-banner .b-sub{opacity:.92;font-size:.85rem;margin-top:4px}
.emp-detail-banner .b-icon{position:absolute;right:24px;top:22px;font-size:2.5rem;opacity:.18}
.emp-detail-body{padding:0 22px 22px;margin-top:-44px;position:relative;z-index:2}
.emp-section-title{font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;font-weight:700;margin:18px 0 10px;display:flex;align-items:center;gap:6px}
.emp-section-title .line{flex:1;height:1px;background:linear-gradient(to right,#cbd5e1,transparent)}
.emp-tile-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
.emp-tile{
    display:flex;align-items:flex-start;gap:12px;
    background:#fff;border:1px solid #e2e8f0;border-radius:10px;
    padding:12px 14px;transition:all .15s ease;
}
.emp-tile:hover{border-color:#94a3b8;box-shadow:0 2px 8px rgba(15,23,42,.06);transform:translateY(-1px)}
.emp-tile-ic{
    width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;
    flex-shrink:0;font-size:.95rem;
}
.emp-tile-ic.ic-blue{background:#e0f2fe;color:#0369a1}
.emp-tile-ic.ic-teal{background:#efedff;color:#5d4ff0}
.emp-tile-ic.ic-violet{background:#ede9fe;color:#6d28d9}
.emp-tile-ic.ic-amber{background:#fef3c7;color:#b45309}
.emp-tile-ic.ic-rose{background:#ffe4e6;color:#be123c}
.emp-tile-ic.ic-slate{background:#f1f5f9;color:#475569}
.emp-tile-body{flex:1;min-width:0}
.emp-tile-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:600;margin-bottom:2px}
.emp-tile-value{font-size:.92rem;color:#0f172a;font-weight:500;word-break:break-word}
.emp-tile-value a{color:#7366ff;text-decoration:none}
.emp-tile-value a:hover{text-decoration:underline}
.emp-tile.tile-wide{grid-column:1/-1}
</style>

<div class="ui-page employee-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->employee_name) ?></h1>
            <div class="ui-sub"><?= Html::encode($model->designation) ?></div>
        </div>
        <div class="ui-actions">
            </div>
        <div class="d-flex gap-2 flex-wrap">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-warning btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-key"></i> Permissions', ['permission', 'id' => $model->id], ['class' => 'btn btn-info btn-sm', 'encode' => false]) ?>
            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#resetModal"><i class="fas fa-lock"></i> Reset Password</button>
            <?= Html::a('<i class="fas fa-ban"></i> Deactivate', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger btn-sm', 'encode' => false,
                'data-method' => 'post',
                'data-confirm' => 'Deactivate ' . Html::encode($model->employee_name) . '?',
            ]) ?>
        </div>
        </div>
    </div>

    <!-- Employee Details -->
    <div class="card emp-detail-card mb-3">
        <div class="emp-detail-banner">
            <i class="fas fa-id-card-clip b-icon"></i>
            <h6>Employee Profile</h6>
            <h3><?= Html::encode($model->employee_name) ?></h3>
            <div class="b-sub">
                <i class="fas fa-briefcase me-1"></i>
                <?= Html::encode($model->designation ?: 'No designation set') ?>
            </div>
        </div>
        <div class="emp-detail-body">
            <div class="emp-section-title"><i class="fas fa-address-card text-info"></i> Contact <span class="line"></span></div>
            <div class="emp-tile-row">
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-teal"><i class="fas fa-phone"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Mobile</div>
                        <div class="emp-tile-value">
                            <?php if (!empty($model->mobile)): ?>
                                <a href="tel:<?= Html::encode($model->mobile) ?>"><?= Html::encode($model->mobile) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-blue"><i class="fas fa-envelope"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Email</div>
                        <div class="emp-tile-value">
                            <?php if (!empty($model->email_id)): ?>
                                <a href="mailto:<?= Html::encode($model->email_id) ?>"><?= Html::encode($model->email_id) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="emp-tile tile-wide">
                    <div class="emp-tile-ic ic-rose"><i class="fas fa-location-dot"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Address</div>
                        <div class="emp-tile-value"><?= nl2br(Html::encode($model->address ?: '—')) ?></div>
                    </div>
                </div>
            </div>

            <div class="emp-section-title"><i class="fas fa-circle-user text-success"></i> Account <span class="line"></span></div>
            <div class="emp-tile-row">
                <?php if ($model->user): ?>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-violet"><i class="fas fa-user"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Username</div>
                        <div class="emp-tile-value"><?= Html::encode($model->user->username) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-slate"><i class="fas fa-id-badge"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Employee Name</div>
                        <div class="emp-tile-value"><?= Html::encode($model->employee_name) ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-amber"><i class="fas fa-briefcase"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Designation</div>
                        <div class="emp-tile-value"><?= Html::encode($model->designation ?: '—') ?></div>
                    </div>
                </div>
            </div>

            <div class="emp-section-title"><i class="fas fa-clock-rotate-left text-warning"></i> Audit <span class="line"></span></div>
            <div class="emp-tile-row">
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-blue"><i class="fas fa-calendar-plus"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Created At</div>
                        <div class="emp-tile-value"><?= $model->created_at ? date('d M Y, h:i A', strtotime($model->created_at)) : '—' ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-teal"><i class="fas fa-calendar-check"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Updated At</div>
                        <div class="emp-tile-value"><?= $model->updated_at ? date('d M Y, h:i A', strtotime($model->updated_at)) : '—' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Access Permissions -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <strong class="small">Access Permissions</strong>
            <?php if ($canManagePermissions): ?>
                <?= Html::a('Manage Permissions', ['permission', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm']) ?>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($grouped)): ?>
                <p class="text-muted mb-0 small">
                    No permissions assigned<?php if ($canManagePermissions): ?>.
                    <a href="<?= \yii\helpers\Url::to(['permission', 'id' => $model->id]) ?>">Click here to assign</a><?php endif; ?>.
                </p>
            <?php else: ?>
                <?php
                $moduleLabelsMap = [
                    'employee' => 'Employee', 'client' => 'Clients', 'project' => 'Projects',
                    'developer' => 'Developers', 'task' => 'Tasks', 'domain' => 'Domains',
                    'lead' => 'CRM / Leads', 'invoice' => 'Invoices',
                    'work-report' => 'Work Reports', 'payslip' => 'Payroll', 'asset' => 'Assets',
                ];
                ?>
                <div class="row g-2">
                <?php foreach ($grouped as $module => $actions): ?>
                    <div class="col-md-4">
                        <div class="fw-semibold small mb-1"><?= Html::encode($moduleLabelsMap[$module] ?? ucfirst($module)) ?></div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($actions as $action): ?>
                                <span class="badge bg-primary-subtle text-primary border perm-badge"><?= Html::encode($action) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" action="<?= \yii\helpers\Url::to(['reset-password', 'id' => $model->id]) ?>">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Reset Password</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Employee: <strong><?= Html::encode($model->employee_name) ?></strong></p>
                    <input type="password" name="new_password" class="form-control form-control-sm"
                           placeholder="New password" required minlength="6" autocomplete="new-password">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>
