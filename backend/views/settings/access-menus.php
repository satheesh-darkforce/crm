<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Developer|null $selected */
/** @var array $allRoutes  [group => [route => label]] */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Access Menus';

$currentPermissions = [];
if ($selected) {
    $raw = trim($selected->access_permissions ?? '');
    $currentPermissions = $raw !== '' ? explode(',', $raw) : [];
}
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.route-group-header{background:#f8f9fa;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#495057;padding:.45rem .75rem}
</style>

<div class="settings-access-menus">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">Access Menus</h5>
    </div>

    <div class="row g-3">
        <!-- Developer selector (left panel) -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold py-2 small text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
                    Select Developer
                </div>
                <div class="list-group list-group-flush" style="max-height:540px;overflow-y:auto">
                    <?php foreach ($developers as $dev): ?>
                    <?php $isActive = $selected && $selected->id === $dev->id; ?>
                    <a href="<?= Url::to(['/settings/access-menus', 'dev_id' => $dev->id]) ?>"
                       class="list-group-item list-group-item-action py-2 <?= $isActive ? 'active' : '' ?>"
                       style="font-size:.875rem">
                        <?= Html::encode($dev->developer_name) ?>
                        <?php if ($dev->designation): ?>
                        <br><small class="<?= $isActive ? 'text-white-50' : 'text-muted' ?>"><?= Html::encode($dev->designation) ?></small>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Permission matrix (right panel) -->
        <div class="col-md-9">
            <?php if (!$selected): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <div style="font-size:2rem"><i class="fas fa-lock"></i></div>
                    <div class="mt-2">Select a developer from the left panel to manage their access.</div>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= Html::encode($selected->developer_name) ?></span>
                    <span class="badge bg-secondary"><?= count($currentPermissions) ?> routes granted</span>
                </div>
                <form method="post">
                    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                    <div class="card-body p-0">
                        <table class="table list-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px">
                                        <input type="checkbox" id="check-all" class="form-check-input">
                                    </th>
                                    <th>Route</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($allRoutes as $group => $routes): ?>
                                <tr>
                                    <td colspan="3" class="route-group-header"><?= Html::encode($group) ?></td>
                                </tr>
                                <?php foreach ($routes as $route => $label): ?>
                                <?php $checked = in_array($route, $currentPermissions); ?>
                                <tr>
                                    <td>
                                        <input class="form-check-input route-check"
                                               type="checkbox"
                                               name="routes[]"
                                               value="<?= Html::encode($route) ?>"
                                               <?= $checked ? 'checked' : '' ?>>
                                    </td>
                                    <td><code style="font-size:.78rem"><?= Html::encode($route) ?></code></td>
                                    <td class="small text-muted"><?= Html::encode($label) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white border-top py-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-4">Save Permissions</button>
                        <a href="<?= Url::to(['/settings/access-menus']) ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$js = <<<JS
(function () {
    var checkAll  = document.getElementById('check-all');
    var checks    = document.querySelectorAll('.route-check');
    if (!checkAll) return;
    checkAll.addEventListener('change', function () {
        checks.forEach(function (c) { c.checked = checkAll.checked; });
    });
})();
JS;
$this->registerJs($js);
?>
