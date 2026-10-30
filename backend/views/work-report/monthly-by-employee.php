<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer[] $developers */
/** @var string[] $dates */
/** @var array $matrix   [dev_id][date] = minutes */
/** @var array $devTotals [dev_id] = total_minutes */
/** @var string $fromDate */
/** @var string $toDate */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use kartik\date\DatePicker;

$this->title = 'Monthly report';

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

// Build export URL preserving the current filter set (Yii1 parity).
$exportUrl = Url::to(array_filter([
    '/work-report/export-monthly-developer-report',
    'from_date' => $fromDate,
    'to_date'   => $toDate,
], fn($v) => $v !== null && $v !== ''));
?>
<style>
.list-table th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.82rem}
.list-table tbody tr:hover{background:#f8f9ff}
.time-cell{font-variant-numeric:tabular-nums;text-align:center;color:#0d6efd;font-weight:600}
.time-cell.empty{color:#ccc;font-weight:400}
.pagination{--bs-pagination-font-size:.825rem}
/* Local override: contain wide matrix inside the card so the page itself
   does not scroll horizontally. Default global CSS forces .table-responsive
   to overflow visible (so action dropdowns aren't clipped on other tables). */
.work-report-monthly-employee .table-responsive{overflow-x:auto!important;max-width:100%}
/* Sticky Total column on the far right — stays visible during horizontal scroll. */
.work-report-monthly-employee .total-col{position:sticky;right:0;z-index:2;background:#f0f4ff;color:#0d6efd;box-shadow:-2px 0 4px rgba(0,0,0,.06)}
.work-report-monthly-employee thead .total-col,
.work-report-monthly-employee tfoot .total-col{z-index:3}
</style>

<div class="work-report-monthly-employee">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-semibold">Monthly by Employee</h5>
            <small class="text-muted"><?= date('d M Y', strtotime($fromDate)) ?> – <?= date('d M Y', strtotime($toDate)) ?></small>
        </div>
        <?php if (UserAccess::checkAccess('work-report/export-monthly-developer-report')): ?>
        <?= Html::a('<i class="fas fa-file-excel me-1"></i> Export to Excel', $exportUrl, ['class' => 'btn btn-success btn-sm', 'encode' => false]) ?>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $fromDate,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-from-date', 'placeholder' => 'From', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $toDate,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-to-date', 'placeholder' => 'To', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                    <div id="to-date-error" class="text-danger" style="font-size:.75rem;display:none">To date cannot be before From date.</div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" id="filter-btn">Apply</button>
                    <?= Html::a('This Month', ['monthly-by-employee'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                </div>
            </form>
<script>
(function(){var $f=$('#filter-from-date'),$t=$('#filter-to-date'),$e=$('#to-date-error'),$b=$('#filter-btn');function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}$f.on('changeDate change',function(){var fv=$f.val();$t.datepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').datepicker('update');}v();});$t.on('changeDate change',v);$f.closest('form').on('submit',function(){return v();});}());
</script>
        </div>
    </div>

    <!-- Matrix Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0" style="min-width:600px">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="min-width:160px">Employee</th>
                        <?php foreach ($dates as $d): ?>
                        <th class="text-center px-1" style="min-width:60px">
                            <div><?= date('d', strtotime($d)) ?></div>
                            <div style="font-size:.65rem;color:#adb5bd"><?= date('D', strtotime($d)) ?></div>
                        </th>
                        <?php endforeach; ?>
                        <th class="text-center total-col" style="min-width:70px">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($developers)): ?>
                    <tr><td colspan="<?= 3 + count($dates) ?>" class="text-center text-muted py-5">No records found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($developers as $i => $dev): ?>
                    <?php $devData = $matrix[$dev->id] ?? []; ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= Html::encode($dev->developer_name) ?></td>
                        <?php foreach ($dates as $d): ?>
                        <td class="<?= isset($devData[$d]) && $devData[$d] > 0 ? 'time-cell' : 'time-cell empty' ?>">
                            <?= isset($devData[$d]) && $devData[$d] > 0 ? $fmt($devData[$d]) : '—' ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="text-center fw-bold total-col">
                            <?= isset($devTotals[$dev->id]) && $devTotals[$dev->id] > 0 ? $fmt($devTotals[$dev->id]) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($developers)): ?>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="2" class="fw-semibold text-end" style="font-size:.78rem">Day Total</td>
                        <?php
                        $grandTotal = 0;
                        foreach ($dates as $d):
                            $dayTotal = 0;
                            foreach ($developers as $dev) {
                                $dayTotal += $matrix[$dev->id][$d] ?? 0;
                            }
                            $grandTotal += $dayTotal;
                        ?>
                        <td class="text-center fw-semibold <?= $dayTotal > 0 ? 'text-primary' : 'text-muted' ?>" style="font-size:.78rem">
                            <?= $dayTotal > 0 ? $fmt($dayTotal) : '' ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="text-center fw-bold total-col"><?= $fmt($grandTotal) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
