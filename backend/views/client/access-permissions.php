<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $client */
/** @var string[] $granted  — array of permitted route strings */

use backend\models\ClientModel;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Portal Permissions';
$sections = ClientModel::portalPermissionOptions();
?>
<div class="ui-page client-access-permissions" style="max-width:860px">
    <div class="ui-head">
        <div>
            <h1>Portal Permissions</h1>
            <div class="ui-sub"><?= Html::encode($client->client_name) ?> · pages this client can open after logging in</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['view', 'id' => $client->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <form method="post" action="<?= Url::to(['access-permissions', 'id' => $client->id]) ?>" class="js-busy-form">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header ui-section">
                <label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer">
                    <input type="checkbox" class="form-check-input mt-0" id="checkAll"> Select all
                </label>
                <span class="ui-section-meta" id="perm-count" aria-live="polite"></span>
            </div>
            <div class="table-responsive">
                <table class="table list-table ui-table mb-0">
                    <thead class="table-light">
                    <tr><th style="width:32%">Section</th><th>Pages</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sections as $section => $perms): ?>
                        <tr>
                            <td class="cell-title">
                                <label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer">
                                    <input type="checkbox" class="form-check-input mt-0 js-perm-section" aria-label="All <?= Html::encode($section) ?> pages">
                                    <?= Html::encode($section) ?>
                                </label>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($perms as $route => $label): ?>
                                    <label class="d-flex align-items-center gap-2 mb-0 small" style="cursor:pointer">
                                        <input type="checkbox" class="form-check-input mt-0 js-perm" name="accessoptions[]"
                                               value="<?= Html::encode($route) ?>" <?= in_array($route, $granted, true) ? 'checked' : '' ?>>
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
        </div>
        <p class="ui-req-note mt-2 mb-0">Unticked pages are hidden from the client and blocked if opened directly. With nothing ticked the client has no portal access.</p>
        <div class="ui-form-actions">
            <button type="submit" class="btn btn-primary btn-sm px-4"><i class="fas fa-check me-1"></i>Save Permissions</button>
            <?= Html::a('Cancel', ['view', 'id' => $client->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
    </form>
</div>
