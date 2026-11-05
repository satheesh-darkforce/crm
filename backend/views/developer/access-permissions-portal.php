<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer $developer */
/** @var array $permissions  ['MainMenu' => ['menu_path', ...]] — already-granted paths */
/** @var string $field  'web' or 'apk' */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$isApk   = ($field === 'apk');
$title   = $isApk ? 'APK Access Permissions' : 'Web Access Permissions';
$saveUrl = $isApk ? Url::to(['/developer/apk-access-permissions',     'id' => $developer->id])
                  : Url::to(['/developer/access-permissions-new', 'id' => $developer->id]);

$this->title = $title . ' — ' . $developer->developer_name;

// Menu definitions ─────────────────────────────────────────────────────────
if ($isApk) {
    $menus = [
        ['main_menu' => 'Lead_management', 'menus' => [
            ['menu_name' => 'Main',               'menu_path' => 'mainmenu'],
            ['menu_name' => 'Lead Main',          'menu_path' => 'lead_mainmenu'],
            ['menu_name' => 'Lead Add',           'menu_path' => 'lead_add'],
            ['menu_name' => 'Lead Edit',          'menu_path' => 'lead_edit'],
            ['menu_name' => 'Lead Delete',        'menu_path' => 'lead_delete'],
            ['menu_name' => 'Lead View',          'menu_path' => 'lead_view'],
            ['menu_name' => 'Followups Main',     'menu_path' => 'followups_mainmenu'],
            ['menu_name' => 'Followups Add',      'menu_path' => 'followups_add'],
            ['menu_name' => 'Followups Edit',     'menu_path' => 'followups_edit'],
            ['menu_name' => 'Followups Delete',   'menu_path' => 'followups_delete'],
            ['menu_name' => 'Followups View',     'menu_path' => 'followups_view'],
            ['menu_name' => 'Lead Master Main',   'menu_path' => 'lead_master_mainmenu'],
            ['menu_name' => 'Lead Master Add',    'menu_path' => 'lead_master_add'],
            ['menu_name' => 'Lead Master Edit',   'menu_path' => 'lead_master_edit'],
            ['menu_name' => 'Lead Master Delete', 'menu_path' => 'lead_master_delete'],
            ['menu_name' => 'Lead Master View',   'menu_path' => 'lead_master_view'],
            ['menu_name' => 'Lead Master Upload', 'menu_path' => 'lead_master_upload'],
            ['menu_name' => 'Lead Convert',       'menu_path' => 'lead_master_converttolead'],
            ['menu_name' => 'Converted Main',     'menu_path' => 'converted_mainmenu'],
            ['menu_name' => 'Converted Add Client','menu_path'=> 'converted_addtoclient'],
            ['menu_name' => 'Dropped Main',       'menu_path' => 'dropped_mainmenu'],
            ['menu_name' => 'My Leads Main',      'menu_path' => 'my_leads_mainmenu'],
            ['menu_name' => 'Assigned Leads Main','menu_path' => 'assigned_leads_mainmenu'],
        ]],
    ];
} else {
    $menus = [
        ['main_menu' => 'Category', 'menus' => [
            ['menu_name' => 'Main',   'menu_path' => 'mainmenu'],
            ['menu_name' => 'Add',    'menu_path' => 'add'],
            ['menu_name' => 'Edit',   'menu_path' => 'edit'],
            ['menu_name' => 'Delete', 'menu_path' => 'delete'],
            ['menu_name' => 'View',   'menu_path' => 'view'],
        ]],
        ['main_menu' => 'Tag', 'menus' => [
            ['menu_name' => 'Main',   'menu_path' => 'mainmenu'],
            ['menu_name' => 'Add',    'menu_path' => 'add'],
            ['menu_name' => 'Edit',   'menu_path' => 'edit'],
            ['menu_name' => 'Delete', 'menu_path' => 'delete'],
            ['menu_name' => 'View',   'menu_path' => 'view'],
        ]],
        ['main_menu' => 'Ledger', 'menus' => [
            ['menu_name' => 'Main',   'menu_path' => 'mainmenu'],
            ['menu_name' => 'Add',    'menu_path' => 'add'],
            ['menu_name' => 'Edit',   'menu_path' => 'edit'],
            ['menu_name' => 'Delete', 'menu_path' => 'delete'],
            ['menu_name' => 'View',   'menu_path' => 'view'],
        ]],
        ['main_menu' => 'Transaction', 'menus' => [
            ['menu_name' => 'Main',   'menu_path' => 'mainmenu'],
            ['menu_name' => 'Add',    'menu_path' => 'add'],
            ['menu_name' => 'Edit',   'menu_path' => 'edit'],
            ['menu_name' => 'Delete', 'menu_path' => 'delete'],
            ['menu_name' => 'View',   'menu_path' => 'view'],
        ]],
    ];
}
?>
<style>
.perm-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600}
.perm-table td{vertical-align:middle;font-size:.875rem}
.perm-badge{cursor:pointer;user-select:none}
</style>

<div class="ui-page developer-access-permissions">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($title) ?></h1>
            <div class="ui-sub"><?= Html::encode($developer->developer_name) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['view', 'id' => $developer->id], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <form method="post" action="<?= $saveUrl ?>">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
            <input type="hidden" name="overall_array" id="overallArray" value="<?= Html::encode(json_encode($menus)) ?>">

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
                    <?php foreach ($menus as $idx => $group):
                        $mainMenu  = $group['main_menu'];
                        $granted   = $permissions[$mainMenu] ?? [];
                    ?>
                        <tr>
                            <td><input type="checkbox" class="check-row"></td>
                            <td class="text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-semibold small"><?= Html::encode(str_replace('_', ' ', $mainMenu)) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($group['menus'] as $item):
                                    $path    = $item['menu_path'];
                                    $checked = in_array($path, $granted);
                                ?>
                                    <label class="d-flex align-items-center gap-1 small perm-badge">
                                        <input type="checkbox"
                                               name="accessoptions[<?= Html::encode($mainMenu) ?>][]"
                                               value="<?= Html::encode($path) ?>"
                                               class="row-check"
                                               <?= $checked ? 'checked' : '' ?>>
                                        <?= Html::encode($item['menu_name'] ?: $path) ?>
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
                <button type="submit" class="btn btn-primary btn-sm">Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<?php $this->registerJs(<<<JS
// Toggle all
document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    document.querySelectorAll('.check-row').forEach(cb => cb.checked = this.checked);
});

// Toggle row
document.querySelectorAll('.check-row').forEach(function(rowCb) {
    rowCb.addEventListener('change', function() {
        var row   = this.closest('tr');
        var boxes = row.querySelectorAll('.row-check');
        boxes.forEach(cb => cb.checked = this.checked);
    });
});
JS); ?>
