<?php
/**
 * Manage Payslips — ONE ROW PER DISTINCT MONTH
 *
 * Columns : Sno | Month | Action (<i class="fas fa-eye"></i> View → view_month, ⬇ Download all, <i class="fas fa-trash-alt"></i> Delete month)
 * Filter  : Select Month & Year datepicker
 * Pager   : << First  < Previous  [1][2]  Next >  Last >>  +  "Displaying X-Y of Z"
 *
 * @var \yii\web\View  $this
 * @var array          $months      [['month_year'=>'YYYY-MM-01','emp_count'=>N], ...]
 * @var int            $total       total distinct months
 * @var int            $page        current 0-based page
 * @var int            $limit       page size (10)
 * @var bool           $isAdmin
 * @var string         $filterMonth YYYY-MM or ''
 */

use kartik\date\DatePicker;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Payslips';

$pageCount = $limit > 0 ? (int)ceil($total / $limit) : 1;
$curPage   = $page + 1;
$from      = $total > 0 ? $page * $limit + 1 : 0;
$to        = min(($page + 1) * $limit, $total);
$mkUrl     = fn(int $p) => Url::to(['index', 'page' => $p, 'month_year' => $filterMonth]);
?>
<style>
.ps-table { border-collapse:collapse; width:100%; }
.ps-table th {
    background:#f4f6f9; font-size:.78rem; text-transform:uppercase;
    letter-spacing:.04em; color:#0060b0; font-weight:700;
    padding:10px 14px; border-bottom:1px solid #dee2e6; white-space:nowrap;
}
.ps-table td { padding:9px 14px; font-size:.875rem; vertical-align:middle; border-bottom:1px solid #f0f0f0; }
.ps-table tbody tr:hover { background:#f8fbff; }
.ps-action { display:flex; gap:8px; align-items:center; }
.ps-action a { color:#444; font-size:1.05rem; text-decoration:none; }
.ps-action a:hover { color:#0d6efd; }
.ps-action a.del-btn:hover { color:#dc3545; }
.ps-pager { list-style:none; padding:0; margin:0; display:flex; gap:3px; flex-wrap:wrap; }
.ps-pager li a, .ps-pager li span {
    display:inline-block; padding:3px 11px; font-size:.8rem;
    border:1px solid #dee2e6; border-radius:3px;
    background:#fff; color:#333; text-decoration:none; white-space:nowrap;
}
.ps-pager li.active span { background:#0d6efd; border-color:#0d6efd; color:#fff; }
.ps-pager li.disabled span, .ps-pager li.disabled a { color:#aaa; pointer-events:none; }
.ps-pager li a:hover { background:#e9ecef; }
.result-info { font-size:.8rem; color:#555; }
</style>

<div class="ui-page payslip-index">

    <div class="ui-head">
        <div>
            <h1><strong>Manage</strong> Payslips</h1>
        </div>
        <div class="ui-actions">
            <?php if ($isAdmin): ?>
                <?php $prevMonthLabel = date('M Y', strtotime('first day of last month')); ?>
                <?= Html::beginForm(['payslip/selectmonth'], 'post', [
                    'style'    => 'display:inline',
                    'onsubmit' => "return confirm('Generate / sync payslips for {$prevMonthLabel} from current attendance?');",
                ]) ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        + Create Payslip (<?= $prevMonthLabel ?>)
                    </button>
                <?= Html::endForm() ?>
                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-folder-open"></i> Export
                </button>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-file-alt"></i> Upload', ['payslip-upload/create'], ['class'=>'btn btn-danger btn-sm','encode'=>false]) ?>
        </div>
    </div>

    <?php foreach (['success'=>'success','error'=>'danger'] as $key => $cls): ?>    <?php endforeach; ?>

    <!-- Search -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="d-flex align-items-center gap-2 flex-wrap">
                <?= DatePicker::widget([
                    'name'          => 'month_year',
                    'value'         => $filterMonth,
                    'type'          => DatePicker::TYPE_INPUT,
                    'pluginOptions' => [
                        'format'      => 'yyyy-mm',
                        'startView'   => 'months',
                        'minViewMode' => 'months',
                        'autoclose'   => true,
                    ],
                    'options' => [
                        'placeholder'  => 'Select Month &amp; Year',
                        'class'        => 'form-control form-control-sm',
                        'style'        => 'max-width:200px',
                        'autocomplete' => 'off',
                    ],
                ]) ?>
                <button type="submit" class="btn btn-primary btn-sm px-4">Search</button>
                <a href="<?= Url::to(['index']) ?>" class="btn btn-danger btn-sm px-3">Reset</a>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="ps-table">
                <thead>
                    <tr>
                        <th style="width:55px">Sno</th>
                        <th>Month</th>
                        <?php if ($isAdmin): ?>
                        <th>Employees</th>
                        <th>Generated On</th>
                        <?php endif; ?>
                        <th style="width:110px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($months)): ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? 5 : 3 ?>"
                            class="text-center text-muted py-5">No payslips found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($months as $i => $row): ?>
                    <tr>
                        <td class="text-muted"><?= $from + $i ?></td>
                        <td><?= date('M - Y', strtotime($row['month_year'])) ?></td>
                        <?php if ($isAdmin): ?>
                        <td class="text-muted small"><?= $row['emp_count'] ?> employee<?= $row['emp_count'] != 1 ? 's' : '' ?></td>
                        <td class="text-muted small"><?= !empty($row['generated_on']) ? date('d M Y, h:i A', strtotime($row['generated_on'])) : '—' ?></td>
                        <?php endif; ?>
                        <td>
                            <div class="ps-action">
                                <?= Html::a('<i class="bi bi-eye"></i>',
                                    ['view-month', 'month' => $row['month_year']],
                                    ['title' => 'View', 'encode' => false]) ?>
                                <?php if ($isAdmin): ?>
                                <?= Html::a('<i class="bi bi-trash3"></i>',
                                    ['delete-month', 'month' => $row['month_year']], [
                                        'class'        => 'del-btn',
                                        'title'        => 'Delete',
                                        'encode'       => false,
                                        'data-method'  => 'post',
                                        'data-confirm' => 'Delete ALL payslips for ' . date('M Y', strtotime($row['month_year'])) . '?',
                                    ]) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3">
            <div class="result-info">
                <?= $total > 0 ? "Displaying {$from}-{$to} of {$total} result" . ($total != 1 ? 's' : '') . '.' : 'No results found.' ?>
            </div>
            <?php if ($pageCount > 1): ?>
            <ul class="ps-pager">
                <li class="<?= $curPage <= 1 ? 'disabled' : '' ?>">
                    <?= $curPage > 1 ? '<a href="'.$mkUrl(1).'">&lt;&lt; First</a>' : '<span>&lt;&lt; First</span>' ?>
                </li>
                <li class="<?= $curPage <= 1 ? 'disabled' : '' ?>">
                    <?= $curPage > 1 ? '<a href="'.$mkUrl($curPage-1).'">&lt; Previous</a>' : '<span>&lt; Previous</span>' ?>
                </li>
                <?php for ($p = max(1,$curPage-2); $p <= min($pageCount,$curPage+2); $p++): ?>
                <li class="<?= $p===$curPage ? 'active' : '' ?>">
                    <?= $p===$curPage ? "<span>{$p}</span>" : '<a href="'.$mkUrl($p).'">'.$p.'</a>' ?>
                </li>
                <?php endfor; ?>
                <li class="<?= $curPage >= $pageCount ? 'disabled' : '' ?>">
                    <?= $curPage < $pageCount ? '<a href="'.$mkUrl($curPage+1).'">Next &gt;</a>' : '<span>Next &gt;</span>' ?>
                </li>
                <li class="<?= $curPage >= $pageCount ? 'disabled' : '' ?>">
                    <?= $curPage < $pageCount ? '<a href="'.$mkUrl($pageCount).'">Last &gt;&gt;</a>' : '<span>Last &gt;&gt;</span>' ?>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= Url::to(['payslip/selectmonth']) ?>">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Select Month to Generate</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <?php
                $cy = (int)date('Y');
                // Default the generate modal to the PREVIOUS month (payslips are run after the
                // month closes), not the current month.
                $prevTs    = strtotime('first day of last month');
                $prevMonth = (int)date('n', $prevTs);
                $prevYear  = (int)date('Y', $prevTs);
                $mn = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
                       7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
                ?>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Month</label>
                            <select id="createMonth" class="form-select form-select-sm" disabled>
                                <option value="<?= str_pad($prevMonth, 2, '0', STR_PAD_LEFT) ?>" selected><?= $mn[$prevMonth] ?></option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Year</label>
                            <select id="createYear" class="form-select form-select-sm" disabled>
                                <option value="<?= $prevYear ?>" selected><?= $prevYear ?></option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="month_year" id="createMonthYear"
                           value="<?= $prevYear ?>-<?= str_pad($prevMonth, 2, '0', STR_PAD_LEFT) ?>">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="createSubmitBtn">Proceed</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= Url::to(['payslip/export-payslip']) ?>"
              onsubmit="return validateExportForm()">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Export Payslips</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Month</label>
                            <select name="filter_month" class="form-select form-select-sm">
                                <option value="">All Months</option>
                                <?php foreach ($mn as $n => $name): ?>
                                    <option value="<?= $n ?>"><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Year</label>
                            <select name="filter_year" class="form-select form-select-sm">
                                <option value="">All Years</option>
                                <?php for ($y = $cy; $y >= 2018; $y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <label class="form-label small fw-semibold">Employees <span class="text-danger">*</span></label>
                    <div style="max-height:180px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px">
                        <?php foreach (\backend\models\Developer::find()->where(['status'=>1])->orderBy('developer_name')->all() as $d): ?>
                        <div class="form-check">
                            <input class="form-check-input exp-emp-chk" type="checkbox"
                                   name="employee[]" value="<?= $d->id ?>" id="eexp<?= $d->id ?>">
                            <label class="form-check-label small" for="eexp<?= $d->id ?>">
                                <?= Html::encode($d->developer_name) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-folder-open"></i> Download Excel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$this->registerJs(<<<JS
// Month/year are locked to the previous month; the hidden month_year field is
// pre-filled server-side, so no client-side selection handling is needed.
function validateExportForm() {
    if (!document.querySelectorAll('.exp-emp-chk:checked').length) {
        Swal.fire({icon:'warning', title:'Required', text:'Select at least one employee.'}); return false;
    }
    return true;
}
JS);
?>
<?php endif; ?>
