<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */

use backend\models\Project;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'My Projects';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$canView    = UserAccess::checkAccess('project/view');
$types      = Project::typeOptions();
?>
<div class="ui-page project-developer">
    <div class="ui-head">
        <div>
            <h1>My Projects</h1>
            <div class="ui-sub"><?= number_format($total) ?> project<?= $total == 1 ? '' : 's' ?> where you have tasks assigned</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Project</th><th>Type</th><th>Status</th><th>Created</th></tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr class="ui-empty-row"><td colspan="5" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'tasks', 'title' => 'No projects yet',
                            'text' => 'Projects appear here once a task on them is assigned to you.']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($models as $i => $row): ?>
                    <?php $ps = (int)$row['project_status']; $pt = (int)$row['project_type']; ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap">
                            <?= $canView ? Html::a(Html::encode($row['title']), ['/project/view', 'id' => $row['id']], ['class' => 'text-reset text-decoration-none']) : Html::encode($row['title']) ?>
                            <div class="sub"><?= Html::encode($row['client_name'] ?: 'No client') ?></div>
                        </td>
                        <td data-label="Type"><?= isset($types[$pt]) ? '<span class="ui-pill plain tone-' . ($pt === Project::TYPE_PRODUCTIVE ? 'accent' : 'neutral') . '">' . $types[$pt] . '</span>' : '—' ?></td>
                        <td data-label="Status"><span class="ui-pill tone-<?= Project::statusTone($ps) ?>"><?= Html::encode(Project::statusOptions()[$ps] ?? 'Unknown') ?></span></td>
                        <td class="small nowrap" data-label="Created"><?= $row['date'] ? date('d M Y', strtotime($row['date'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($models)]) ?>
    </div>
</div>
