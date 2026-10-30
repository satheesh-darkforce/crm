<?php
/** @var \yii\web\View $this */
/** @var array[] $rows       [{project_id, client_id, total_secs, entry_count}] */
/** @var array $projMap      [id => Project] */
/** @var array $cliMap       [id => ClientModel] */
/** @var int   $grandTotal */
/** @var string $fromDate */
/** @var string $toDate */
/** @var int|null $projFilter */
/** @var int|null $cliFilter */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\ClientModel[] $clients */

use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$this->title = 'Project based monthly work report';

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
</style>

<div class="work-report-monthly-project">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Monthly by Project</h5>
            <small class="text-muted"><?= date('d M Y', strtotime($fromDate)) ?> – <?= date('d M Y', strtotime($toDate)) ?></small>
        </div>
        <?php if ($grandTotal > 0): ?>
        <span class="badge bg-primary-subtle text-primary border" style="font-size:.85rem">
            Grand Total: <?= $fmt($grandTotal) ?> hrs
        </span>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'project_id',
                        'value'         => $projFilter,
                        'data'          => ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => 'All Projects'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'client_id',
                        'value'         => $cliFilter,
                        'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => 'All Clients'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
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
                    <button class="btn btn-secondary btn-sm" id="filter-btn">Filter</button>
                    <?= Html::a('Clear', ['monthly-by-project'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                </div>
            </form>
<script>
(function(){var $f=$('#filter-from-date'),$t=$('#filter-to-date'),$e=$('#to-date-error'),$b=$('#filter-btn');function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}$f.on('changeDate change',function(){var fv=$f.val();$t.datepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').datepicker('update');}v();});$t.on('changeDate change',v);$f.closest('form').on('submit',function(){return v();});}());
</script>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th class="text-center">Entries</th>
                        <th class="text-center">Total Hours</th>
                        <th style="width:80px">Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $row): ?>
                    <?php
                        $proj = $projMap[$row['project_id']] ?? null;
                        $cli  = $cliMap[$row['client_id']]  ?? null;
                    ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= Html::encode($proj ? $proj->title : '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($cli ? $cli->client_name : '—') ?></td>
                        <td class="text-center text-muted small"><?= (int)$row['entry_count'] ?></td>
                        <td class="text-center fw-semibold text-primary"><?= $fmt((int)$row['total_secs']) ?></td>
                        <td>
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm js-proj-detail"
                                    style="font-size:.75rem;padding:.2rem .5rem"
                                    data-project-id="<?= $row['project_id'] ?>"
                                    data-from="<?= Html::encode($fromDate) ?>"
                                    data-to="<?= Html::encode($toDate) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#projectDetailModal">
                                Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="text-end fw-semibold" style="font-size:.82rem">Grand Total</td>
                        <td class="text-center fw-bold text-primary"><?= $fmt($grandTotal) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Project Detail Modal -->
<div class="modal fade" id="projectDetailModal" tabindex="-1" aria-labelledby="projectDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="projectDetailModalLabel">Developer Breakdown</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="projectDetailBody">
                <div class="text-center text-muted py-4">Loading&hellip;</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('projectDetailModal');
    modal.addEventListener('show.bs.modal', function (event) {
        var btn       = event.relatedTarget;
        var projectId = btn ? btn.getAttribute('data-project-id') : null;
        var fromDate  = btn ? btn.getAttribute('data-from') : '';
        var toDate    = btn ? btn.getAttribute('data-to') : '';
        var body      = document.getElementById('projectDetailBody');
        body.innerHTML = '<div class="text-center text-muted py-4">Loading&hellip;</div>';
        if (!projectId) return;
        fetch('<?= Url::to(['/work-report/project-detail']) ?>?id=' + projectId + '&from_date=' + fromDate + '&to_date=' + toDate, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.text(); })
        .then(function (html) { body.innerHTML = html; })
        .catch(function () { body.innerHTML = '<p class="text-danger text-center py-3">Failed to load details.</p>'; });
    });
});
</script>
