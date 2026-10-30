<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Developer[] $developers */

use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$this->title = 'Attendance Verification';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$verifiedLabel = [0 => ['Pending', 'warning'], 1 => ['Verified', 'success'], 2 => ['Rejected', 'danger']];
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="attendance-verification">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Attendance Verification</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('Publish Attendance', ['publish-attendance'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>
    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => Yii::$app->request->get('developer_id'),
                        'data'          => ['' => 'All Employees'] + ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'All Employees'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'month',
                        'value'         => Yii::$app->request->get('month', ''),
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select Month'],
                        'pluginOptions' => [
                            'autoclose'   => true,
                            'format'      => 'yyyy-mm',
                            'minViewMode' => 'months',
                        ],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('developer_id') || Yii::$app->request->get('month')): ?>
                        <?= Html::a('Clear', ['attendance-verification'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Employee</th>
                        <th>Month</th>
                        <th class="text-center">Total Late</th>
                        <th class="text-center">Late LOP</th>
                        <th class="text-center">Total Leave</th>
                        <th class="text-center">CL Taken</th>
                        <th class="text-center">CL Backup</th>
                        <th class="text-center">Total Absent</th>
                        <th class="text-center">Leave LOP</th>
                        <th class="text-center">Total LOP</th>
                        <th class="text-center">Verified</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="13" class="text-center text-muted py-5">No verification records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $rec):
                        [$vLabel, $vColor] = $verifiedLabel[$rec->verified] ?? ['Unknown', 'secondary'];
                    ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold"><?= Html::encode($rec->developer->developer_name ?? '—') ?></td>
                        <td class="text-muted small"><?= $rec->attendance_date ? date('M Y', strtotime($rec->attendance_date)) : '—' ?></td>
                        <td class="text-center"><?= $rec->total_late ?></td>
                        <td class="text-center"><?= $rec->late_lop ?></td>
                        <td class="text-center"><?= $rec->total_leave ?></td>
                        <td class="text-center"><?= $rec->cl_taken ?></td>
                        <td class="text-center"><?= $rec->cl_backup ?></td>
                        <td class="text-center <?= $rec->total_absent > 0 ? 'text-danger fw-semibold' : '' ?>"><?= $rec->total_absent ?></td>
                        <td class="text-center"><?= $rec->leave_lop ?></td>
                        <td class="text-center fw-semibold <?= $rec->total_lop > 0 ? 'text-danger' : '' ?>"><?= number_format((float)$rec->total_lop, 2) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $vColor ?>" style="font-size:.72rem"><?= $vLabel ?></span>
                        </td>
                        <td class="text-end">
                            <!-- Actions dropdown -->
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    &#8942;
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <?= Html::a('View Monthly', ['monthly-attendance',
                                            'developer_id' => $rec->developer_id,
                                            'from_date'    => date('Y-m-01', strtotime($rec->attendance_date)),
                                            'to_date'      => date('Y-m-t', strtotime($rec->attendance_date)),
                                        ], ['class' => 'dropdown-item']) ?>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
