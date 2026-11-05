<?php
/** @var \yii\web\View $this */
/** @var array[] $rows       [{project_id, client_id, total_secs, entry_count}] */
/** @var array $projMap      [id => Project] */
/** @var array $cliMap       [id => ClientModel] */
/** @var int   $grandTotal */
/** @var string $fromDate */
/** @var string $toDate */
/** @var int|null $projFilter */
/** @var int|null $cliFilter */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\ClientModel[] $clients */

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$this->title = 'Project based monthly work report';

$period    = date('d M Y', strtotime($fromDate)) . ' – ' . date('d M Y', strtotime($toDate));
$hasFilter = $projFilter || $cliFilter || Yii::$app->request->get('from_date') || Yii::$app->request->get('to_date');
$canDetail = UserAccess::checkAccess('work-report/project-detail');
?>
<div class="ui-page work-report-monthly-project">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Time by project',
        'sub'     => $period . ' · ' . count($rows) . ' project' . (count($rows) == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Total <strong>' . Task::hm($grandTotal) . '</strong> hrs</span>',
        'route'   => 'monthly-by-project',
        'filters' => [
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'select', 'name' => 'project_id', 'label' => 'Project', 'data' => ArrayHelper::map($projects, 'id', 'title'), 'placeholder' => 'All projects'],
            ['type' => 'select', 'name' => 'client_id', 'label' => 'Client', 'data' => ArrayHelper::map($clients, 'id', 'client_name'), 'placeholder' => 'All clients'],
            ['type' => 'html', 'col' => 'col-12 order-last', 'html' => '<div class="form-text mt-0">Without From / To this page shows the current month.</div>'],
        ],
    ]) ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Project</th>
                    <th>Client</th>
                    <th class="text-end">Entries</th>
                    <th class="text-end">Time</th>
                    <th style="width:140px">Share</th>
                    <?php if ($canDetail): ?><th class="text-end" style="width:56px"><span class="visually-hidden">Details</span></th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="7" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilter
                            ? ['icon' => 'search', 'title' => 'No work matches your search', 'text' => 'Try a wider date range or fewer filters.',
                               'action' => Html::a('Clear search', ['monthly-by-project'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'folder-open', 'title' => 'No work logged this month', 'text' => 'Time per project appears here as reports come in.']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $row):
                    $proj = $projMap[$row['project_id']] ?? null;
                    $cli  = $cliMap[$row['client_id']] ?? null;
                    $secs = (int)$row['total_secs'];
                    $pct  = $grandTotal > 0 ? $secs * 100 / $grandTotal : 0;
                ?>
                    <tr>
                        <td class="cell-num muted"><?= $i + 1 ?></td>
                        <td class="cell-title"><?= Html::encode($proj->title ?? 'No project') ?></td>
                        <td class="small" data-label="Client"><?= Html::encode($cli->client_name ?? '—') ?></td>
                        <td class="text-end num" data-label="Entries"><?= number_format((int)$row['entry_count']) ?></td>
                        <td class="text-end" data-label="Time"><strong class="num"><?= Task::hm($secs) ?></strong></td>
                        <td data-label="Share">
                            <div class="d-flex align-items-center gap-2">
                                <div class="ui-bar flex-fill" role="presentation"><span style="width:<?= round(min(100, $pct), 1) ?>%"></span></div>
                                <span class="small text-muted num"><?= number_format($pct, 1) ?>%</span>
                            </div>
                        </td>
                        <?php if ($canDetail): ?>
                        <td class="cell-actions text-end">
                            <?php if ($row['project_id']): ?>
                            <button type="button" class="btn btn-outline-secondary act-btn" data-ui-modal="#projectDetailModal"
                                    data-url="<?= Url::to(['project-detail', 'id' => (int)$row['project_id'], 'from_date' => $fromDate, 'to_date' => $toDate]) ?>"
                                    title="Time per employee" aria-label="Time per employee on <?= Html::encode($proj->title ?? 'this project') ?>">
                                <i class="fas fa-users"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($rows): ?>
                <tfoot class="table-light">
                <tr>
                    <td colspan="4" class="text-end fw-semibold small">Total</td>
                    <td class="text-end fw-bold num"><?= Task::hm($grandTotal) ?></td>
                    <td colspan="<?= $canDetail ? 2 : 1 ?>"></td>
                </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="modal fade" id="projectDetailModal" tabindex="-1" aria-labelledby="projectDetailModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold" id="projectDetailModalTitle">Time per employee</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0"></div>
            </div>
        </div>
    </div>
</div>
