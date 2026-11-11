<?php
/** @var yii\web\View $this */
/** @var string $from */
/** @var string $to */
/** @var array $totals */
/** @var array $bySource */
/** @var array $byTemp */
/** @var array $byProduct */
/** @var array<int,string> $categories */

use backend\models\Lead;
use yii\bootstrap5\Html;

$this->title = 'Lead Insights';
$pct = fn($a, $b) => $b > 0 ? round($a / $b * 100) : 0;
$total = (int)($totals['total'] ?? 0);
$closed = (int)($totals['converted'] ?? 0) + (int)($totals['dropped'] ?? 0);

/** One breakdown table. $label maps the group key to a name; rows are sorted by volume. */
$table = function (string $title, array $rows, callable $label, string $hint) use ($pct, $total) {
    usort($rows, fn($a, $b) => (int)$b['total'] <=> (int)$a['total']);
    ob_start(); ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section d-flex justify-content-between"><span><?= Html::encode($title) ?></span><span class="small text-muted fw-normal d-none d-md-inline"><?= Html::encode($hint) ?></span></div>
        <?php if (!$rows): ?>
            <div class="card-body small text-muted">No leads in this period.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light"><tr>
                    <th>Group</th><th style="width:30%">Share of leads</th><th class="text-end">Leads</th><th class="text-end">Open</th>
                    <th class="text-end">Hot</th><th class="text-end">Converted</th><th class="text-end">Dropped</th><th class="text-end">Win rate</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): $n = (int)$r['total']; $won = (int)$r['converted']; $lost = (int)$r['dropped']; ?>
                    <tr>
                        <td class="cell-title"><?= Html::encode($label($r['k'])) ?></td>
                        <td data-label="Share">
                            <div class="li-bar" title="<?= $pct($n, $total) ?>% of all leads"><span style="width:<?= $pct($n, $total) ?>%"></span></div>
                        </td>
                        <td class="text-end num" data-label="Leads"><?= $n ?></td>
                        <td class="text-end num" data-label="Open"><?= (int)$r['open'] ?></td>
                        <td class="text-end num" data-label="Hot"><?= (int)$r['hot'] ?></td>
                        <td class="text-end num" data-label="Converted"><?= $won ?></td>
                        <td class="text-end num" data-label="Dropped"><?= $lost ?></td>
                        <td class="text-end num fw-semibold" data-label="Win rate" title="Converted ÷ (converted + dropped)">
                            <?= ($won + $lost) ? $pct($won, $won + $lost) . '%' : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php return ob_get_clean();
};
?>
<div class="ui-page lead-insights">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Lead insights',
        'sub'     => 'Leads created ' . date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to)),
        'actions' => Html::a('<i class="fas fa-calendar-day me-1"></i>Today', ['today'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]),
        'route'   => 'insights',
        'filters' => [
            ['type' => 'date', 'name' => 'from', 'label' => 'Created from', 'col' => 'col-lg-3 col-md-6'],
            ['type' => 'date', 'name' => 'to', 'label' => 'Created to', 'col' => 'col-lg-3 col-md-6'],
        ],
    ]) ?>

    <div class="ui-tiles">
        <div class="ui-tile is-accent"><div class="t-label">Leads</div><div class="t-value num"><?= $total ?></div><div class="t-sub"><?= (int)($totals['open'] ?? 0) ?> still open</div></div>
        <div class="ui-tile"><div class="t-label">Converted</div><div class="t-value num"><?= (int)($totals['converted'] ?? 0) ?></div><div class="t-sub"><?= (int)($totals['dropped'] ?? 0) ?> dropped</div></div>
        <div class="ui-tile"><div class="t-label">Win rate</div><div class="t-value num"><?= $closed ? $pct((int)$totals['converted'], $closed) . '%' : '—' ?></div><div class="t-sub">Of leads that were closed</div></div>
        <div class="ui-tile"><div class="t-label">Hot leads</div><div class="t-value num"><?= (int)($totals['hot'] ?? 0) ?></div><div class="t-sub"><?= $pct((int)($totals['hot'] ?? 0), $total) ?>% of leads</div></div>
    </div>

    <?= $table('By source', $bySource, fn($k) => $k === null ? 'Not recorded' : (Lead::sourceOptions()[(int)$k] ?? 'Unknown'), 'Which channels bring leads, and which ones close.') ?>
    <?= $table('By product of interest', $byProduct, fn($k) => $categories[(int)$k] ?? 'Removed category', 'A lead asking about several products counts in each.') ?>
    <?= $table('By temperature', $byTemp, fn($k) => Lead::priorityOptions()[(int)$k] ?? 'Not set', 'Does the Hot / Warm / Cold call match the result?') ?>
</div>
