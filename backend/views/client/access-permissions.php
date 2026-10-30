<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $client */
/** @var string[] $granted  — array of permitted route strings */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Portal Permissions — ' . $client->client_name;

// Permissions available for the client portal
$menus = [
    [
        'section' => 'Invoices',
        'perms'   => [
            ['name' => 'Invoice List',   'route' => 'invoice/clientinvoice'],
            ['name' => 'Invoice View',   'route' => 'invoice/view'],
        ],
    ],
    [
        'section' => 'Work Reports',
        'perms'   => [
            ['name' => 'Work Report List', 'route' => 'workreport/clientworkreports'],
            ['name' => 'Work Report View', 'route' => 'workreport/clientview'],
        ],
    ],
];
?>
<style>
.perm-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600}
.perm-table td{vertical-align:middle;font-size:.875rem}
</style>

<div class="client-access-permissions">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Portal Permissions</h5>
            <small class="text-muted"><?= Html::encode($client->client_name) ?></small>
        </div>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['view', 'id' => $client->id], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body pb-0 pt-3 px-3">
            <p class="text-muted small mb-3">
                Select which pages this client can access in the client portal. Unchecked routes will be hidden or blocked.
            </p>
        </div>
        <form method="post" action="<?= Url::to(['access-permissions', 'id' => $client->id]) ?>">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
            <div class="table-responsive">
                <table class="table perm-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:32px"><input type="checkbox" id="checkAll" title="Toggle all"></th>
                            <th>#</th>
                            <th>Section</th>
                            <th>Permissions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($menus as $idx => $group): ?>
                        <tr>
                            <td><input type="checkbox" class="check-row"></td>
                            <td class="text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-semibold small"><?= Html::encode($group['section']) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($group['perms'] as $perm): ?>
                                    <label class="d-flex align-items-center gap-1 small" style="cursor:pointer;user-select:none">
                                        <input type="checkbox"
                                               name="accessoptions[]"
                                               value="<?= Html::encode($perm['route']) ?>"
                                               class="row-check"
                                               <?= in_array($perm['route'], $granted) ? 'checked' : '' ?>>
                                        <?= Html::encode($perm['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent text-end py-3">
                <button type="submit" class="btn btn-primary btn-sm px-4">Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<?php $this->registerJs(<<<JS
document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    document.querySelectorAll('.check-row').forEach(cb => cb.checked = this.checked);
});
document.querySelectorAll('.check-row').forEach(function(rowCb) {
    rowCb.addEventListener('change', function() {
        var boxes = this.closest('tr').querySelectorAll('.row-check');
        boxes.forEach(cb => cb.checked = this.checked);
    });
});
JS); ?>
