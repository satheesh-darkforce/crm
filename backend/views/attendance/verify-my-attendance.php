<?php
/** @var \yii\web\View $this */
/** @var \backend\models\AttendanceVerification $verificationRecord */
/** @var string[] $dates */
/** @var array $dailyRecords [date] => DailyAttendance */
/** @var string $fromDate */
/** @var string $toDate */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Verify My Attendance';

$leaveLabel = [
    1 => ['Present',         'success'],
    2 => ['Present (Half)',  'success'],
    3 => ['Absent (LOP)',    'danger'],
    4 => ['Holiday',         'secondary'],
    5 => ['Late',            'warning'],
    6 => ['Permission',      'info'],
    7 => ['Leave (CL)',      'primary'],
    8 => ['Half Day Leave',  'warning'],
];
?>

<div class="ui-page verify-my-attendance">
    <div class="ui-head">
        <div>
            <h1>Verify My Attendance</h1>
            <div class="ui-sub">Month: <strong><?= date('F Y', strtotime($verificationRecord->attendance_date)) ?></strong></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('&larr; My Attendance', ['my-attendance'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <!-- Summary card -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-2">
                    <div class="text-muted small">Total Late</div>
                    <div class="fw-semibold"><?= $verificationRecord->total_late ?></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="text-muted small">Late LOP</div>
                    <div class="fw-semibold"><?= $verificationRecord->late_lop ?></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="text-muted small">Total Leave</div>
                    <div class="fw-semibold"><?= $verificationRecord->total_leave ?></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="text-muted small">CL Taken</div>
                    <div class="fw-semibold"><?= $verificationRecord->cl_taken ?></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="text-muted small">Total Absent</div>
                    <div class="fw-semibold text-danger"><?= $verificationRecord->total_absent ?></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="text-muted small">Total LOP</div>
                    <div class="fw-semibold text-danger"><?= number_format((float)$verificationRecord->total_lop, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily detail table -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Status</th>
                        <th>In Time</th>
                        <th>Out Time</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($dates as $i => $date):
                    $da = $dailyRecords[$date] ?? null;
                    [$lbl, $color] = $da ? ($leaveLabel[$da->leave_type] ?? ['—', 'secondary']) : ['—', 'secondary'];
                    if ($da && $da->is_holiday) { $lbl = 'Holiday'; $color = 'secondary'; }
                ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td><?= date('d M Y', strtotime($date)) ?></td>
                    <td class="text-muted"><?= date('D', strtotime($date)) ?></td>
                    <td>
                        <?php if ($da): ?>
                            <span class="badge bg-<?= $color ?>" style="font-size:.72rem"><?= $lbl ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-success">
                        <?= ($da && $da->in_time) ? date('h:i A', strtotime($da->in_time)) : '—' ?>
                    </td>
                    <td class="small text-danger">
                        <?= ($da && $da->out_time) ? date('h:i A', strtotime($da->out_time)) : '—' ?>
                    </td>
                    <td class="small text-muted"><?= Html::encode($da->remarks ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Verify / Reject actions -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="fw-semibold mb-3">Confirm Your Attendance</h6>
            <div class="d-flex gap-2 flex-wrap">
                <form method="post" action="<?= Url::to(['verify-attendance', 'id' => $verificationRecord->id]) ?>" style="display:inline">
                    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                    <?= Html::hiddenInput('status', 1) ?>
                    <button type="submit" class="btn btn-success btn-sm"
                            onclick="return confirm('Are you sure the above attendance data is correct?')">
                        Accept &amp; Verify
                    </button>
                </form>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    Raise Dispute
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject/Dispute modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= Url::to(['verify-attendance', 'id' => $verificationRecord->id]) ?>">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <?= Html::hiddenInput('status', 2) ?>
                <div class="modal-header">
                    <h5 class="modal-title">Raise Dispute</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control form-control-sm" rows="4" required
                                  placeholder="Describe the discrepancy you noticed…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger btn-sm">Submit Dispute</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
