<?php
/**
 * Work report entries table (daily, mine, client, yesterday).
 * Edit / delete show only for reports the user may change: managers, holders of
 * work-report/index, or the report's own employee (same rule as WorkReportController::findModel).
 *
 * @var \yii\web\View $this
 * @var \yii\data\ActiveDataProvider $dataProvider
 * @var string[]    $columns  any of developer, date, client, project (in display order)
 * @var array       $empty    params for _ui/_empty
 * @var bool|null   $readOnly true hides edit / delete (client view)
 */

use backend\components\TaskAccess;
use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$offset     = $pagination ? $pagination->getOffset() : 0;
$myDevId    = (int)Yii::$app->user->identity->getDeveloperId();
$seesAll    = TaskAccess::isManager() || UserAccess::checkAccess('work-report/index');
$can = [
    'view'   => UserAccess::checkAccess('work-report/view'),
    'update' => empty($readOnly) && UserAccess::checkAccess('work-report/update'),
    'delete' => empty($readOnly) && UserAccess::checkAccess('work-report/delete'),
];
$labels = ['developer' => 'Employee', 'date' => 'Date', 'client' => 'Client', 'project' => 'Project'];
$colspan = count($columns) + 4;
?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table list-table ui-table stack mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:40px">#</th>
                <?php foreach ($columns as $c): ?><th><?= $labels[$c] ?></th><?php endforeach; ?>
                <th>Task</th>
                <th class="text-end">Time</th>
                <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr class="ui-empty-row"><td colspan="<?= $colspan ?>" class="ui-empty-cell"><?= $this->render('@backend/views/_ui/_empty', $empty) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $wr): /** @var \backend\models\WorkReport $wr */
                $mayEdit = $seesAll || ($myDevId && (int)$wr->developer_id === $myDevId);
                $edit    = $can['update'] && $mayEdit;
                $delete  = $can['delete'] && $mayEdit;
                $when    = $wr->date ? date('d M Y', strtotime($wr->date)) : '—';
            ?>
                <tr>
                    <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                    <?php foreach ($columns as $n => $c):
                        $value = match ($c) {
                            'developer' => Html::encode($wr->developer->developer_name ?? '—'),
                            'date'      => $when,
                            'client'    => Html::encode($wr->client->client_name ?? '—'),
                            default     => Html::encode($wr->project->title ?? '—'),
                        };
                    ?>
                        <?php if ($n === 0): // first column is the card title on phones ?>
                            <td class="cell-title text-nowrap"><?= $value ?></td>
                        <?php else: ?>
                            <td class="<?= $c === 'date' ? 'text-nowrap' : 'small' ?>" data-label="<?= $labels[$c] ?>"><?= $value ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <td data-label="Task" style="max-width:320px">
                        <div class="text-truncate" title="<?= Html::encode((string)$wr->task) ?>"><?= trim((string)$wr->task) !== '' ? Html::encode($wr->task) : '<span class="text-muted">—</span>' ?></div>
                    </td>
                    <td class="text-end text-nowrap" data-label="Time">
                        <span><strong class="num"><?= Task::hm((int)$wr->total_time) ?></strong>
                        <?php if ($wr->from_time && $wr->to_time): ?><span class="d-block small text-muted"><?= Html::encode($wr->from_time . ' – ' . $wr->to_time) ?></span><?php endif; ?></span>
                    </td>
                    <td class="cell-actions text-end">
                        <?php if ($can['view'] || $edit || $delete): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                    aria-label="Actions for work report of <?= Html::encode($when) ?>">&#8942;</button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye fa-fw me-1"></i>View', ['view', 'id' => $wr->work_id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                <?php if ($edit): ?><li><?= Html::a('<i class="fas fa-pen fa-fw me-1"></i>Edit', ['update', 'id' => $wr->work_id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                <?php if ($delete): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-trash fa-fw me-1"></i>Delete', ['delete', 'id' => $wr->work_id], ['class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data' => ['method' => 'post', 'confirm' => 'Remove the work report of ' . $when . ' (' . Task::hm((int)$wr->total_time) . ')?']]) ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $dataProvider->getTotalCount(), 'count' => count($rows), 'noun' => 'reports']) ?>
</div>
