<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer[] $developers */

use yii\bootstrap5\Html;

$this->title = 'Accounts Portal Access';
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
</style>

<div class="settings-accounts-portal">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">Accounts Portal Access</h5>
    </div>

    <div class="alert alert-info border-0 py-2 small mb-3">
        <strong>Accounts Portal</strong> is an external application (Python/React). Toggling access here sets
        <code>access_permissions_new</code> on each developer record, which is checked by <code>UserAccess::checkaccess('accounts_portal_access')</code>.
    </div>

    <form method="post">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table list-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Developer Name</th>
                            <th>Designation</th>
                            <th>Emp Code</th>
                            <th class="text-center" style="width:140px">Portal Access</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($developers)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-5">No active developers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($developers as $i => $dev): ?>
                        <?php
                            $perms   = $dev->access_permissions_new ? json_decode($dev->access_permissions_new, true) : [];
                            $hasAccess = is_array($perms) && in_array('accounts_portal', $perms);
                        ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td class="fw-semibold small"><?= Html::encode($dev->developer_name) ?></td>
                            <td class="small text-muted"><?= Html::encode($dev->designation ?? '—') ?></td>
                            <td class="small text-muted"><?= Html::encode($dev->emp_code ?? '—') ?></td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="portal_access[]"
                                           value="<?= $dev->id ?>"
                                           <?= $hasAccess ? 'checked' : '' ?>>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($developers)): ?>
            <div class="card-footer bg-white border-top py-2">
                <button type="submit" class="btn btn-primary btn-sm px-4">Save Access Settings</button>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>
