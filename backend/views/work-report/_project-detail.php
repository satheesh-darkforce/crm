<?php
/**
 * Project detail partial — developer breakdown for a single project.
 * Rendered via AJAX by the Project Detail modal in monthly-by-project.php.
 *
 * @var \yii\web\View $this
 * @var array[] $rows          [{developer_id, dev_total}]
 * @var array $devMap          [id => Developer]
 * @var \backend\models\Project|null $project
 * @var int $grandTotal        total minutes
 * @var string $fromDate
 * @var string $toDate
 */

use yii\bootstrap5\Html;

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';
?>
<div class="px-3 pt-2 pb-1 border-bottom">
    <span class="fw-semibold"><?= Html::encode($project->title ?? '—') ?></span>
    <span class="text-muted small ms-2">
        <?= date('d M Y', strtotime($fromDate)) ?> – <?= date('d M Y', strtotime($toDate)) ?>
    </span>
    <span class="badge bg-primary-subtle text-primary border ms-2" style="font-size:.78rem">
        Total: <?= $fmt($grandTotal) ?> hrs
    </span>
</div>

<?php if (empty($rows)): ?>
    <p class="text-muted text-center py-4 mb-0">No work logged for this project in the selected period.</p>
<?php else: ?>
<table class="table table-borderless mb-0" style="font-size:.875rem">
    <thead class="table-light">
        <tr>
            <th style="width:40px;font-size:.75rem;color:#6c757d">#</th>
            <th style="font-size:.75rem;color:#6c757d">Developer</th>
            <th class="text-center" style="width:200px;font-size:.75rem;color:#6c757d">Contribution</th>
            <th class="text-center" style="width:90px;font-size:.75rem;color:#6c757d">Hours</th>
            <th class="text-center" style="width:60px;font-size:.75rem;color:#6c757d">%</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $i => $row):
        $dev  = $devMap[$row['developer_id']] ?? null;
        $mins = (int)$row['dev_total'];
        $pct  = $grandTotal > 0 ? round($mins / $grandTotal * 100, 1) : 0;
        $barColor = $pct >= 50 ? '#0d6efd' : ($pct >= 25 ? '#fd7e14' : '#6c757d');
    ?>
    <tr>
        <td class="text-muted"><?= $i + 1 ?></td>
        <td class="fw-semibold"><?= Html::encode($dev ? $dev->developer_name : '—') ?></td>
        <td>
            <div style="height:6px;border-radius:3px;background:#e9ecef">
                <div style="height:100%;border-radius:3px;width:<?= min(100, $pct) ?>%;background:<?= $barColor ?>"></div>
            </div>
        </td>
        <td class="text-center fw-semibold text-primary"><?= $fmt($mins) ?></td>
        <td class="text-center text-muted"><?= number_format($pct, 1) ?>%</td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot class="table-light">
        <tr>
            <td colspan="3" class="text-end fw-semibold" style="font-size:.78rem">Grand Total</td>
            <td class="text-center fw-bold text-primary"><?= $fmt($grandTotal) ?></td>
            <td class="text-center text-muted">100%</td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>
