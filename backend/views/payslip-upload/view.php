<?php
/** @var \yii\web\View $this */
/** @var \backend\models\UploadPayslip $model */
/** @var array $header */
/** @var array $data */
/** @var string|null $fileError */

use yii\bootstrap5\Html;
use common\components\UserAccess;

$fileError = $fileError ?? null;

$monthDisplay = !empty($model->month_year) ? date('M - Y', strtotime($model->month_year)) : '';

$this->title = 'Payslip - ' . $monthDisplay;

// The CSV carries its own "NET PAY" and "ACTION" columns. We pin NET PAY to a
// sticky column (always visible, beside the action buttons) and drop the CSV's
// "ACTION" column entirely — the page's own sticky View/Download buttons replace
// it. Both are pulled OUT of the horizontally-scrolling column list.
$normHead  = static fn($s) => strtoupper(preg_replace('/\s+/', ' ', trim((string)$s, " \t\n\r\0\x0B\xEF\xBB\xBF")));
$netPayIdx = null;
$actionIdx = null;
foreach ($header as $idx => $col) {
    $n = $normHead($col);
    if ($netPayIdx === null && $n === 'NET PAY') { $netPayIdx = $idx; }
    if ($actionIdx === null && $n === 'ACTION')  { $actionIdx = $idx; }
}
// Indices to omit from the scrolling part of the table.
$skipIdx = [];
if ($netPayIdx !== null) { $skipIdx[$netPayIdx] = true; }
if ($actionIdx !== null) { $skipIdx[$actionIdx] = true; }
?>
<style>
/* Scroll container — bound to the page so a wide CSV scrolls inside the card,
   not the whole page. Header stays sticky inside the scroll area, and the
   Action column stays sticky on the right so it's always reachable. */
.payslip-upload-view .csv-scroll {
    max-width: calc(100vw - 308px);     /* sidebar 252 + #main-content padding 56 */
    max-height: 70vh;
    overflow: auto;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #fff;
    scrollbar-width: thin;
}
.payslip-upload-view .csv-table {
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
    width: max-content;          /* let columns size to their content; container scrolls */
    min-width: 100%;
    font-size: .82rem;
}
.payslip-upload-view .csv-table th,
.payslip-upload-view .csv-table td {
    padding: .4rem .6rem;
    border-bottom: 1px solid #f0f1f3;
    white-space: nowrap;
    vertical-align: middle;
}
.payslip-upload-view .csv-table thead th {
    position: sticky; top: 0; z-index: 3;
    background: #f4f6f9;
    color: #495057;
    font-weight: 600;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    border-bottom: 2px solid #dee2e6;
}
.payslip-upload-view .csv-table tbody tr:nth-child(odd)  td { background: #fafbfc; }
.payslip-upload-view .csv-table tbody tr:hover           td { background: #eef4ff; }
.payslip-upload-view .csv-table .col-sno  { width: 48px;  text-align: right; color: #6c757d; }
.payslip-upload-view .csv-table .col-act  {
    position: sticky; right: 0; z-index: 2;
    background: #fff;
    border-left: 1px solid #e5e7eb;
    width: 92px; text-align: center;
}
.payslip-upload-view .csv-table thead .col-act { z-index: 4; background: #f4f6f9; }
.payslip-upload-view .csv-table tbody tr:nth-child(odd) .col-act { background: #fafbfc; }
.payslip-upload-view .csv-table tbody tr:hover           .col-act { background: #eef4ff; }
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
/* Net Pay pinned sticky, immediately to the LEFT of the 92px-wide Action column. */
.payslip-upload-view .csv-table .col-netpay {
    position: sticky; right: 92px; z-index: 2;
    background: #fff;
    border-left: 1px solid #e5e7eb;
    width: 120px; text-align: right; font-weight: 600; color: #198754;
}
.payslip-upload-view .csv-table thead .col-netpay { z-index: 4; background: #f4f6f9; color: #495057; }
.payslip-upload-view .csv-table tbody tr:nth-child(odd) .col-netpay { background: #fafbfc; }
.payslip-upload-view .csv-table tbody tr:hover           .col-netpay { background: #eef4ff; }
</style>

<div class="payslip-upload-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
            <?php if (!$fileError && !empty($data)): ?>
                <small class="text-muted"><?= count($data) ?> employee row<?= count($data) != 1 ? 's' : '' ?></small>
            <?php endif; ?>
        </div>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-2">
            <?php if ($fileError): ?>
                <div class="alert alert-warning small mb-0">
                    <strong>Cannot read uploaded file.</strong> <?= Html::encode($fileError) ?>
                </div>
            <?php elseif (!empty($header) && !empty($data)): ?>
            <div class="csv-scroll">
                <table class="csv-table">
                    <thead>
                        <tr>
                            <th class="col-sno">#</th>
                            <?php foreach ($header as $idx => $column): ?>
                                <?php if (isset($skipIdx[$idx])) { continue; } ?>
                                <th><?= Html::encode($column) ?></th>
                            <?php endforeach; ?>
                            <?php if ($netPayIdx !== null): ?>
                                <th class="col-netpay">Net Pay</th>
                            <?php endif; ?>
                            <th class="col-act">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $i => $row): ?>
                        <tr>
                            <td class="col-sno"><?= $i + 1 ?></td>
                            <?php foreach ($row as $idx => $value): ?>
                                <?php if (isset($skipIdx[$idx])) { continue; } ?>
                                <td><?= Html::encode($value) ?></td>
                            <?php endforeach; ?>
                            <?php if ($netPayIdx !== null): ?>
                                <td class="col-netpay"><?= Html::encode($row[$netPayIdx] ?? '') ?></td>
                            <?php endif; ?>
                            <td class="col-act">
                                <?php
                                // EMP CODE is typically in column index 1 (second column)
                                $empCode = isset($row[1]) ? trim($row[1]) : '';
                                ?>
                                <?php
                                $iconEye = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2.5"/></svg>';
                                $iconDl  = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v8"/><path d="M4.5 6.5L8 10l3.5-3.5"/><path d="M2.5 13h11"/></svg>';
                                ?>
                                <div class="d-flex gap-1 justify-content-center">
                                    <?php if ($empCode && UserAccess::checkAccess('payslip-upload/view-payslip')): ?>
                                        <?= Html::a($iconEye, ['view-payslip', 'id' => $model->id, 'emp_code' => $empCode], [
                                            'class'  => 'btn btn-outline-primary act-btn',
                                            'title'  => 'View Payslip',
                                            'target' => '_blank',
                                            'encode' => false,
                                        ]) ?>
                                    <?php endif; ?>
                                    <?php if ($empCode && UserAccess::checkAccess('payslip-upload/download-payslip')): ?>
                                        <?= Html::a($iconDl, ['download-payslip', 'id' => $model->id, 'emp_code' => $empCode], [
                                            'class'  => 'btn btn-outline-success act-btn',
                                            'title'  => 'Download Payslip',
                                            'encode' => false,
                                        ]) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="text-center text-muted py-5">No records found!</div>
            <?php endif; ?>
        </div>
    </div>
</div>
