<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer[] $developers */
/** @var string[] $dates */
/** @var array $matrix   [dev_id][date] = seconds */
/** @var array $devTotals [dev_id] = total seconds */
/** @var string $fromDate */
/** @var string $toDate */

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Monthly report';

$dayTotals = [];
foreach ($dates as $d) {
    $dayTotals[$d] = 0;
    foreach ($developers as $dev) {
        $dayTotals[$d] += $matrix[$dev->id][$d] ?? 0;
    }
}
$grandTotal = array_sum($dayTotals);
$weekend    = fn(string $d) => in_array(date('N', strtotime($d)), ['6', '7'], true) ? ' is-weekend' : '';
$custom     = Yii::$app->request->get('from_date') || Yii::$app->request->get('to_date');

$export = ['/work-report/export-monthly-developer-report', 'from_date' => $fromDate, 'to_date' => $toDate];
$actions = $custom ? Html::a('This month', ['monthly-by-employee'], ['class' => 'btn btn-outline-secondary btn-sm']) : '';
if (UserAccess::checkAccess('work-report/export-monthly-developer-report')) {
    $actions .= Html::a('<i class="fas fa-file-excel me-1"></i>Export', $export, ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]);
}
?>
<div class="ui-page work-report-monthly-employee">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Daily hours grid',
        'sub'     => date('d M Y', strtotime($fromDate)) . ' – ' . date('d M Y', strtotime($toDate)) . ' · ' . count($developers) . ' employee' . (count($developers) == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Total <strong>' . Task::hm($grandTotal) . '</strong> hrs</span>',
        'actions' => $actions,
        'route'   => 'monthly-by-employee',
        'filters' => [
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'html', 'col' => 'col-lg-4 col-md-12', 'html' => '<div class="form-text mt-0">Defaults to this month; up to 62 days fit in the grid.</div>'],
        ],
    ]) ?>

    <div class="card border-0 shadow-sm">
        <?php if (!$developers): ?>
            <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'calendar-alt', 'title' => 'No work logged in this period', 'text' => 'Pick another date range above.']) ?>
        <?php else: ?>
        <div class="ui-matrix-wrap">
            <table class="table list-table ui-matrix mb-0">
                <thead class="table-light">
                <tr>
                    <th class="col-name" scope="col">Employee</th>
                    <?php foreach ($dates as $d): ?>
                        <th class="<?= trim($weekend($d)) ?>" scope="col"><?= date('d', strtotime($d)) ?><span class="dow"><?= date('D', strtotime($d)) ?></span></th>
                    <?php endforeach; ?>
                    <th class="col-total" scope="col">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($developers as $dev): $row = $matrix[$dev->id] ?? []; ?>
                    <tr>
                        <th class="col-name fw-semibold" scope="row"><?= Html::encode($dev->developer_name) ?></th>
                        <?php foreach ($dates as $d): $s = $row[$d] ?? 0; ?>
                            <td class="<?= trim(($s > 0 ? '' : 'is-off') . $weekend($d)) ?>"><?= $s > 0 ? Task::hm($s) : '·' ?></td>
                        <?php endforeach; ?>
                        <td class="col-total"><?= Task::hm((int)($devTotals[$dev->id] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th class="col-name small" scope="row">Day total</th>
                    <?php foreach ($dates as $d): ?>
                        <td class="fw-semibold<?= $dayTotals[$d] ? '' : ' is-off' ?><?= $weekend($d) ?>"><?= $dayTotals[$d] ? Task::hm($dayTotals[$d]) : '·' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= Task::hm($grandTotal) ?></td>
                </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
