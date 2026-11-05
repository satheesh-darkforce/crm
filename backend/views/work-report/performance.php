<?php
/** @var \yii\web\View $this */
/** @var array[] $rows          [{developer_id, total_secs, days_worked, entry_count, project_count}] */
/** @var array $devMap          [id => Developer] */
/** @var string $fromDate */
/** @var string $toDate */
/** @var int|null $devFilter */
/** @var \backend\models\Developer[] $developers */
/** @var int $workingDays */
/** @var int $expectedSecsPerDay */

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Performance report';

$expectedTotal = $workingDays * $expectedSecsPerDay;
$hasFilter     = $devFilter || Yii::$app->request->get('from_date') || Yii::$app->request->get('to_date');
// ≥80% good, 50–79% average, below 50% low.
$band = fn(float $pct): array => $pct >= 80 ? ['success', 'Good'] : ($pct >= 50 ? ['warning', 'Average'] : ['danger', 'Low']);

// Export keeps the current filters (Yii1 parity — uses the daily export).
$export = array_filter([
    '/work-report/export-daily-work-report', 'from_date' => $fromDate, 'to_date' => $toDate, 'developer_id' => $devFilter,
], fn($v) => $v !== null && $v !== '');
?>
<div class="ui-page work-report-performance">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Performance',
        'sub'     => date('d M Y', strtotime($fromDate)) . ' – ' . date('d M Y', strtotime($toDate)) . ' · ' . $workingDays . ' working day' . ($workingDays == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Expected <strong>' . Task::hm($expectedTotal) . '</strong> hrs / person</span>',
        'actions' => UserAccess::checkAccess('work-report/export-daily-work-report')
            ? Html::a('<i class="fas fa-file-excel me-1"></i>Export', $export, ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]) : '',
        'route'   => 'performance',
        'filters' => [
            ['type' => 'select', 'name' => 'developer_id', 'label' => 'Employee', 'data' => ArrayHelper::map($developers, 'id', 'developer_name'), 'placeholder' => 'All employees'],
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
        ],
    ]) ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Employee</th>
                    <th class="text-end">Days logged</th>
                    <th class="text-end">Projects</th>
                    <th class="text-end">Entries</th>
                    <th class="text-end">Time</th>
                    <th class="text-end">Avg / day</th>
                    <th style="min-width:180px">Against expected</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="8" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilter
                            ? ['icon' => 'search', 'title' => 'No work matches your search', 'text' => 'Try a wider date range or another employee.',
                               'action' => Html::a('Clear search', ['performance'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'chart-line', 'title' => 'No work logged in this period', 'text' => 'Pick another date range above.']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $row):
                    $secs = (int)$row['total_secs'];
                    $days = (int)$row['days_worked'];
                    $pct  = $expectedTotal > 0 ? round($secs / $expectedTotal * 100, 1) : 0;
                    [$tone, $word] = $band($pct);
                ?>
                    <tr>
                        <td class="cell-num muted"><?= $i + 1 ?></td>
                        <td class="cell-title"><?= Html::encode(($devMap[$row['developer_id']] ?? null)->developer_name ?? '—') ?></td>
                        <td class="text-end num" data-label="Days logged"><span><?= $days ?> <span class="text-muted">/ <?= $workingDays ?></span></span></td>
                        <td class="text-end num" data-label="Projects"><?= (int)$row['project_count'] ?></td>
                        <td class="text-end num" data-label="Entries"><?= number_format((int)$row['entry_count']) ?></td>
                        <td class="text-end" data-label="Time"><strong class="num"><?= Task::hm($secs) ?></strong></td>
                        <td class="text-end num text-muted" data-label="Avg / day"><?= Task::hm($days > 0 ? intdiv($secs, $days) : 0) ?></td>
                        <td data-label="Against expected">
                            <div class="d-flex align-items-center gap-2">
                                <div class="ui-bar flex-fill tone-<?= $tone ?>" role="presentation"><span style="width:<?= min(100, $pct) ?>%"></span></div>
                                <span class="ui-pill tone-<?= $tone ?>" title="<?= $word ?>"><?= number_format($pct, 1) ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($rows): ?>
        <div class="card-footer bg-white small text-muted">
            Logged time ÷ (<?= $workingDays ?> working days × <?= Task::hm($expectedSecsPerDay) ?> hrs).
            <span class="ui-pill tone-success plain">80%+ good</span> <span class="ui-pill tone-warning plain">50–79% average</span> <span class="ui-pill tone-danger plain">below 50% low</span>
        </div>
        <?php endif; ?>
    </div>
</div>
