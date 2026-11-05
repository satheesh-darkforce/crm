<?php
/** @var \yii\web\View $this */
/** @var array[] $reportData   [{project_id, project_name, client_name, total_secs, developers:[{name,secs,pct}]}] */
/** @var int   $grandTotal */
/** @var string|null $fromDate */
/** @var string|null $toDate */
/** @var array $devIds */
/** @var array $projIds */
/** @var array $cliIds */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[]   $projects */
/** @var \backend\models\ClientModel[] $clients */

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Overall report';

$ready = $fromDate && $toDate;
$top   = $reportData;
usort($top, fn($a, $b) => $b['total_secs'] <=> $a['total_secs']);
$top   = array_slice($top, 0, 12);
$max   = $top ? max(1, (int)$top[0]['total_secs']) : 1;

$actions = '';
if ($ready && $reportData && UserAccess::checkAccess('work-report/export-overall-report')) {
    $actions = Html::a('<i class="fas fa-file-excel me-1"></i>Export', array_filter([
        '/work-report/export-overall-report', 'from_date' => $fromDate, 'to_date' => $toDate, 'developer_id' => $devIds, 'project_id' => $projIds,
    ]), ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]);
}
?>
<div class="ui-page work-report-overall">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Overall report',
        'sub'     => $ready ? date('d M Y', strtotime($fromDate)) . ' – ' . date('d M Y', strtotime($toDate)) . ' · ' . count($reportData) . ' project' . (count($reportData) == 1 ? '' : 's') : 'Pick a date range to build the report',
        'meta'    => $ready ? '<span class="ui-total">Total <strong>' . Task::hm($grandTotal) . '</strong> hrs</span>' : '',
        'actions' => $actions,
        'route'   => 'overall',
        'countKeys' => ['developer_id', 'project_id', 'client_id'],
        'filters' => [
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From *', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To *', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'select', 'name' => 'developer_id[]', 'label' => 'Employees', 'multiple' => true, 'data' => ArrayHelper::map($developers, 'id', 'developer_name'), 'placeholder' => 'All employees', 'col' => 'col-lg-3 col-md-4'],
            ['type' => 'select', 'name' => 'project_id[]', 'label' => 'Projects', 'multiple' => true, 'data' => ArrayHelper::map($projects, 'id', 'title'), 'placeholder' => 'All projects', 'col' => 'col-lg-3 col-md-6'],
            ['type' => 'select', 'name' => 'client_id[]', 'label' => 'Clients', 'multiple' => true, 'data' => ArrayHelper::map($clients, 'id', 'client_name'), 'placeholder' => 'All clients', 'col' => 'col-lg-3 col-md-6'],
        ],
    ]) ?>

    <?php if (!$ready): ?>
        <div class="card border-0 shadow-sm">
            <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'calendar-alt', 'title' => 'Choose a period', 'text' => 'Set both From and To under Search to see time per project and employee.',
                'action' => Html::button('<i class="fas fa-search me-1"></i>Open search', ['class' => 'btn btn-primary btn-sm', 'data-bs-toggle' => 'collapse', 'data-bs-target' => '#wr-search-overall'])]) ?>
        </div>
    <?php elseif (!$reportData): ?>
        <div class="card border-0 shadow-sm">
            <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'search', 'title' => 'No work in this period', 'text' => 'Try a wider date range or fewer filters.',
                'action' => Html::a('Clear search', ['overall'], ['class' => 'btn btn-outline-secondary btn-sm'])]) ?>
        </div>
    <?php else: ?>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header ui-section">Top projects by time<?php if (count($reportData) > 12): ?> <span class="ui-section-meta">12 of <?= count($reportData) ?></span><?php endif; ?></div>
            <div class="card-body d-grid gap-2">
                <?php foreach ($top as $row): ?>
                    <div class="d-flex align-items-center gap-2 small">
                        <div class="text-truncate text-muted" style="width:min(200px,35%);flex-shrink:0" title="<?= Html::encode($row['project_name']) ?>"><?= Html::encode($row['project_name']) ?></div>
                        <div class="ui-bar flex-fill" style="height:10px" role="presentation"><span style="width:<?= max(1, round($row['total_secs'] / $max * 100, 1)) ?>%"></span></div>
                        <strong class="num text-end" style="width:60px;flex-shrink:0"><?= Task::hm((int)$row['total_secs']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php foreach ($reportData as $proj): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header ui-section d-flex flex-wrap gap-2 align-items-baseline">
                    <span><?= Html::encode($proj['project_name']) ?></span>
                    <span class="ui-section-meta"><?= Html::encode($proj['client_name']) ?></span>
                    <span class="ms-auto ui-pill tone-accent"><span class="num"><?= Task::hm((int)$proj['total_secs']) ?></span>&nbsp;hrs</span>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 small">
                        <thead class="table-light">
                        <tr><th class="ps-3">Employee</th><th style="width:40%">Share</th><th class="text-end pe-3" style="width:90px">Time</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($proj['developers'] as $dev): ?>
                            <tr>
                                <td class="ps-3"><?= Html::encode($dev['name']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="ui-bar flex-fill" role="presentation"><span style="width:<?= min(100, $dev['pct']) ?>%"></span></div>
                                        <span class="text-muted num"><?= number_format($dev['pct'], 1) ?>%</span>
                                    </div>
                                </td>
                                <td class="text-end pe-3 fw-semibold num"><?= Task::hm((int)$dev['secs']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>
