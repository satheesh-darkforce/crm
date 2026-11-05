<?php
/**
 * Grouped time totals table (monthly / cumulative reports): one row per group, time on the right,
 * grand total in the footer.
 *
 * @var \yii\web\View $this
 * @var array       $rows      arrays or objects; read with ArrayHelper::getValue
 * @var array       $columns   [key => label]; the first is shown as the row title
 * @var string      $timeKey   key holding seconds
 * @var int         $grandTotal seconds
 * @var array       $empty     params for _ui/_empty
 * @var \yii\data\Pagination|null $pagination
 * @var int|null    $total     total rows (for the pager)
 */

use backend\models\Task;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$pagination = $pagination ?? null;
$offset     = $pagination ? $pagination->getOffset() : 0;
$first      = array_key_first($columns);
$share      = $grandTotal > 0;
?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table list-table ui-table stack mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:40px">#</th>
                <?php foreach ($columns as $label): ?><th><?= Html::encode($label) ?></th><?php endforeach; ?>
                <th class="text-end">Time</th>
                <?php if ($share): ?><th class="text-end" style="width:80px">Share</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr class="ui-empty-row"><td colspan="<?= count($columns) + 3 ?>" class="ui-empty-cell"><?= $this->render('@backend/views/_ui/_empty', $empty) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $r): $secs = (int)ArrayHelper::getValue($r, $timeKey); ?>
                <tr>
                    <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                    <?php foreach ($columns as $key => $label): $v = trim((string)ArrayHelper::getValue($r, $key)); ?>
                        <td<?= $key === $first ? ' class="cell-title"' : ' class="small" data-label="' . Html::encode($label) . '"' ?>><?= $v !== '' ? Html::encode($v) : '<span class="text-muted">—</span>' ?></td>
                    <?php endforeach; ?>
                    <td class="text-end" data-label="Time"><strong class="num"><?= Task::hm($secs) ?></strong></td>
                    <?php if ($share): ?><td class="text-end text-muted num" data-label="Share"><?= number_format($secs * 100 / $grandTotal, 1) ?>%</td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($rows && $share): ?>
            <tfoot class="table-light">
            <tr>
                <td colspan="<?= count($columns) + 1 ?>" class="text-end fw-semibold small">Total<?= $pagination && $pagination->getPageCount() > 1 ? ' (all pages)' : '' ?></td>
                <td class="text-end fw-bold num"><?= Task::hm($grandTotal) ?></td>
                <td></td>
            </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => (int)($total ?? count($rows)), 'count' => count($rows), 'noun' => 'rows']) ?>
</div>
