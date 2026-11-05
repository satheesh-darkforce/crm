<?php
/**
 * Time per employee on one project — loaded into the modal on monthly-by-project.
 *
 * @var \yii\web\View $this
 * @var array[] $rows          [{developer_id, dev_total}]
 * @var array $devMap          [id => Developer]
 * @var \backend\models\Project|null $project
 * @var int $grandTotal        total seconds
 * @var string $fromDate
 * @var string $toDate
 */

use backend\models\Task;
use yii\bootstrap5\Html;
?>
<div class="px-3 py-2 border-bottom d-flex flex-wrap gap-2 align-items-baseline">
    <strong><?= Html::encode($project->title ?? 'No project') ?></strong>
    <span class="small text-muted"><?= date('d M Y', strtotime($fromDate)) ?> – <?= date('d M Y', strtotime($toDate)) ?></span>
    <span class="ms-auto small text-muted">Total <strong class="num text-body"><?= Task::hm((int)$grandTotal) ?></strong> hrs</span>
</div>

<?php if (!$rows): ?>
    <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'users', 'title' => 'No work logged', 'text' => 'Nobody logged time on this project in this period.', 'small' => true]) ?>
<?php else: ?>
<table class="table mb-0 small">
    <thead class="table-light">
    <tr>
        <th style="width:40px">#</th>
        <th>Employee</th>
        <th style="width:35%">Share</th>
        <th class="text-end" style="width:80px">Time</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $i => $row):
        $secs = (int)$row['dev_total'];
        $pct  = $grandTotal > 0 ? $secs * 100 / $grandTotal : 0;
    ?>
        <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td class="fw-semibold"><?= Html::encode(($devMap[$row['developer_id']] ?? null)->developer_name ?? '—') ?></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="ui-bar flex-fill" role="presentation"><span style="width:<?= round(min(100, $pct), 1) ?>%"></span></div>
                    <span class="text-muted num"><?= number_format($pct, 1) ?>%</span>
                </div>
            </td>
            <td class="text-end fw-semibold num"><?= Task::hm($secs) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
