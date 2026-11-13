<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer $model */
/** @var array $currentMonth */
/** @var \yii\data\Pagination $cmPagination */
/** @var int $cmTotal */
/** @var array $lastMonth */
/** @var \yii\data\Pagination $lmPagination */
/** @var int $lmTotal */
/** @var array $allTime */
/** @var \yii\data\Pagination $allTimePagination */
/** @var int $allTimeTotal */

use backend\models\Developer;
use backend\models\WorkReport;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use yii\widgets\Pjax;

$this->title = $model->developer_name;
$isExEmployee = (int)$model->status === 0;
?>
<style>
.work-report-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600}
.work-report-table td{vertical-align:middle;font-size:.875rem}
.work-report-table tfoot td{font-weight:600;border-top:2px solid #dee2e6}
/* Employee profile card — gradient banner + grouped info tiles */
.emp-detail-card{border:0;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(15,23,42,.06);background:#fff}
.emp-detail-banner{
    background:linear-gradient(120deg,#7366ff 0%,#3b82f6 100%);
    color:#fff;padding:22px 24px;position:relative;
}
.emp-detail-banner::after{content:"";position:absolute;right:-40px;top:-40px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.07)}
.emp-detail-banner::before{content:"";position:absolute;left:-30px;bottom:-60px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.06)}
.emp-detail-banner .b-flex{display:flex;align-items:center;gap:18px;position:relative;z-index:2}
.emp-detail-banner .b-photo{
    width:84px;height:84px;border-radius:50%;border:3px solid rgba(255,255,255,.6);
    background:rgba(255,255,255,.18);overflow:hidden;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff;
}
.emp-detail-banner .b-photo img{width:100%;height:100%;object-fit:cover}
.emp-detail-banner h6{font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;opacity:.85;margin:0 0 4px;font-weight:600}
.emp-detail-banner h3{margin:0;font-weight:700;font-size:1.45rem}
.emp-detail-banner .b-sub{opacity:.92;font-size:.85rem;margin-top:3px}
.emp-detail-banner .b-pills{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.emp-detail-banner .b-pill{
    background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.3);
    padding:3px 10px;border-radius:999px;font-size:.74rem;font-weight:500;
}
.emp-detail-body{padding:4px 22px 22px;position:relative;z-index:2}
.emp-section-title{font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;font-weight:700;margin:18px 0 10px;display:flex;align-items:center;gap:8px}
.emp-section-title .line{flex:1;height:1px;background:linear-gradient(to right,#cbd5e1,transparent)}
.emp-tile-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
.emp-tile{
    display:flex;align-items:flex-start;gap:12px;
    background:#fff;border:1px solid #e2e8f0;border-radius:10px;
    padding:12px 14px;transition:all .15s ease;
}
.emp-tile:hover{border-color:#94a3b8;box-shadow:0 2px 8px rgba(15,23,42,.06);transform:translateY(-1px)}
.emp-tile-ic{
    width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;
    flex-shrink:0;font-size:.95rem;
}
.emp-tile-ic.ic-blue{background:#e0f2fe;color:#0369a1}
.emp-tile-ic.ic-teal{background:#efedff;color:#5d4ff0}
.emp-tile-ic.ic-violet{background:#ede9fe;color:#6d28d9}
.emp-tile-ic.ic-amber{background:#fef3c7;color:#b45309}
.emp-tile-ic.ic-rose{background:#ffe4e6;color:#be123c}
.emp-tile-ic.ic-slate{background:#f1f5f9;color:#475569}
.emp-tile-ic.ic-green{background:#dcfce7;color:#166534}
.emp-tile-body{flex:1;min-width:0}
.emp-tile-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:600;margin-bottom:2px}
.emp-tile-value{font-size:.92rem;color:#0f172a;font-weight:500;word-break:break-word}
.emp-tile-value a{color:#7366ff;text-decoration:none}
.emp-tile-value a:hover{text-decoration:underline}
.emp-tile.tile-wide{grid-column:1/-1}
</style>

<div class="ui-page developer-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?php if ($isExEmployee): ?>
            <span class="badge bg-secondary fs-6">Ex-Employee</span>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $teamLabel        = match((int)$model->team) { 1 => 'Team A', 2 => 'Team B', default => null };
    $deptLabel        = Developer::$departments[(int)$model->department] ?? null;
    $branchLabel      = match((int)$model->branch) { 1 => 'Coimbatore', 2 => 'Chennai', default => null };
    $employmentLabel  = match((int)$model->employment_type) { 1 => 'Trainee', 2 => 'Employed', default => null };
    $initials = strtoupper(substr($model->developer_name, 0, 1))
              . (strpos($model->developer_name, ' ') !== false
                 ? strtoupper(substr(strstr($model->developer_name, ' '), 1, 1)) : '');
    $photoUrl = $model->profile_photo
        ? Yii::$app->request->baseUrl . '/uploads/profileimages/' . $model->profile_photo
        : null;
    ?>
    <div class="card emp-detail-card mb-3">
        <div class="emp-detail-banner">
            <div class="b-flex">
                <div class="b-photo">
                    <?php if ($photoUrl): ?>
                        <img src="<?= Html::encode($photoUrl) ?>" alt="Photo">
                    <?php else: ?>
                        <?= Html::encode($initials) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h6>Employee Profile</h6>
                    <h3><?= Html::encode($model->developer_name) ?></h3>
                    <div class="b-sub">
                        <i class="fas fa-briefcase me-1"></i>
                        <?= Html::encode($model->designation ?: 'No designation set') ?>
                        <?php if ($model->emp_code): ?>
                            &nbsp;·&nbsp; <i class="fas fa-hashtag"></i> <?= Html::encode($model->emp_code) ?>
                        <?php endif; ?>
                    </div>
                    <div class="b-pills">
                        <?php if ($teamLabel): ?><span class="b-pill"><i class="fas fa-people-group me-1"></i><?= Html::encode($teamLabel) ?></span><?php endif; ?>
                        <?php if ($deptLabel): ?><span class="b-pill"><i class="fas fa-sitemap me-1"></i><?= Html::encode($deptLabel) ?></span><?php endif; ?>
                        <?php if ($branchLabel): ?><span class="b-pill"><i class="fas fa-location-dot me-1"></i><?= Html::encode($branchLabel) ?></span><?php endif; ?>
                        <?php if ($employmentLabel): ?><span class="b-pill"><i class="fas fa-user-tie me-1"></i><?= Html::encode($employmentLabel) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="emp-detail-body">
            <!-- Contact -->
            <div class="emp-section-title"><i class="fas fa-address-card text-info"></i> Contact <span class="line"></span></div>
            <div class="emp-tile-row">
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-teal"><i class="fas fa-phone"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Mobile</div>
                        <div class="emp-tile-value">
                            <?php if (!empty($model->mobile)): ?>
                                <a href="tel:<?= Html::encode($model->mobile) ?>"><?= Html::encode($model->mobile) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-teal"><i class="fas fa-mobile-screen"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Alt Phone</div>
                        <div class="emp-tile-value">
                            <?php if (!empty($model->official_mobile_no)): ?>
                                <a href="tel:<?= Html::encode($model->official_mobile_no) ?>"><?= Html::encode($model->official_mobile_no) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-blue"><i class="fas fa-envelope"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Email</div>
                        <div class="emp-tile-value">
                            <?php if (!empty($model->email_id)): ?>
                                <a href="mailto:<?= Html::encode($model->email_id) ?>"><?= Html::encode($model->email_id) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-blue"><i class="fas fa-at"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Official Email</div>
                        <div class="emp-tile-value">
                            <?php if (!empty($model->official_email_id)): ?>
                                <a href="mailto:<?= Html::encode($model->official_email_id) ?>"><?= Html::encode($model->official_email_id) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account / Identity -->
            <div class="emp-section-title"><i class="fas fa-circle-user text-success"></i> Account <span class="line"></span></div>
            <div class="emp-tile-row">
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-violet"><i class="fas fa-user"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Username</div>
                        <div class="emp-tile-value"><?= Html::encode($model->user ? $model->user->username : '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-slate"><i class="fas fa-id-badge"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Employee Code</div>
                        <div class="emp-tile-value"><?= Html::encode($model->emp_code ?: '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-amber"><i class="fas fa-cake-candles"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Date of Birth</div>
                        <div class="emp-tile-value"><?= $model->dob ? date('d M Y', strtotime($model->dob)) : '—' ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-green"><i class="fas fa-calendar-day"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Date of Joining</div>
                        <div class="emp-tile-value"><?= $model->doj ? date('d M Y', strtotime($model->doj)) : '—' ?></div>
                    </div>
                </div>
                <?php if ($isExEmployee || !empty($model->dor)): ?>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-rose"><i class="fas fa-calendar-xmark"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Date of Relieving</div>
                        <div class="emp-tile-value"><?= $model->dor ? date('d M Y', strtotime($model->dor)) : '—' ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-slate"><i class="fas fa-graduation-cap"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Qualification</div>
                        <div class="emp-tile-value"><?= Html::encode($model->qualification ?: '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-violet"><i class="fas fa-handshake"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Hiring Mode</div>
                        <div class="emp-tile-value"><?= Html::encode($model->hiring_mode ?: '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-amber"><i class="fas fa-key"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">PIN</div>
                        <div class="emp-tile-value">
                            <?php if ($model->emp_pin): ?>
                                <b>••••</b> &nbsp;
                                <a href="#" data-bs-toggle="modal" data-bs-target="#viewPinModal" title="View PIN"><i class="bi bi-eye"></i> View</a>
                                &nbsp;
                                <a href="#" data-bs-toggle="modal" data-bs-target="#updatePinModal" title="Update PIN"><i class="bi bi-pencil"></i> Edit</a>
                            <?php else: ?>
                                <span class="text-muted">(not set)</span> &nbsp;
                                <a href="#" data-bs-toggle="modal" data-bs-target="#updatePinModal" title="Set PIN"><i class="bi bi-pencil"></i> Set PIN</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compensation -->
            <div class="emp-section-title"><i class="fas fa-indian-rupee-sign text-success"></i> Compensation <span class="line"></span></div>
            <div class="emp-tile-row">
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-green"><i class="fas fa-sack-dollar"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Gross Salary</div>
                        <div class="emp-tile-value">₹<?= number_format((float)$model->gross_salary, 2) ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-teal"><i class="fas fa-coins"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Net Salary</div>
                        <div class="emp-tile-value">₹<?= number_format((float)$model->net_salary, 2) ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-green"><i class="fas fa-circle-check"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Salary Applicable</div>
                        <div class="emp-tile-value"><?= ((int)$model->is_salary_applicable === 1) ? 'Yes' : 'No' ?></div>
                    </div>
                </div>
            </div>

            <!-- Salary Management (requirement #1) -->
            <div class="emp-section-title"><i class="fas fa-money-check-dollar text-success"></i> Salary Management <span class="line"></span></div>
            <div class="emp-tile-row">
                <?php
                // PF / ESI / Professional Tax show only Applicable / Not (their amounts
                // come from the Manage Deductions settings). Incentive / Allowance carry
                // a per-employee amount. TDS is hidden for now.
                $toggleOnly = [
                    ['PF', 'pf_status'],
                    ['ESI', 'esi_status'],
                    ['Professional Tax', 'professional_tax_status'],
                ];
                $withAmount = [
                    ['Incentive', 'incentive_status', 'incentive_amount'],
                    ['Allowance', 'allowance_applicable', 'allowance_amount'],
                ];
                ?>
                <?php foreach ($toggleOnly as $sc): ?>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-teal"><i class="fas fa-indian-rupee-sign"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label"><?= Html::encode($sc[0]) ?></div>
                        <div class="emp-tile-value">
                            <?= ((int)$model->{$sc[1]} === 1)
                                ? '<span class="text-success">Applicable</span>'
                                : '<span class="text-muted">Not applicable</span>' ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php foreach ($withAmount as $sc): ?>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-teal"><i class="fas fa-indian-rupee-sign"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label"><?= Html::encode($sc[0]) ?></div>
                        <div class="emp-tile-value">
                            <?= ((int)$model->{$sc[1]} === 1)
                                ? '₹' . number_format((float)$model->{$sc[2]}, 2)
                                : '<span class="text-muted">Not applicable</span>' ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Bank -->
            <div class="emp-section-title"><i class="fas fa-building-columns text-primary"></i> Bank <span class="line"></span></div>
            <div class="emp-tile-row">
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-blue"><i class="fas fa-building-columns"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Bank</div>
                        <div class="emp-tile-value"><?= Html::encode($model->bank ?: '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-blue"><i class="fas fa-code-branch"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Branch</div>
                        <div class="emp-tile-value"><?= Html::encode($model->bank_branch ?: '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-slate"><i class="fas fa-credit-card"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">Account Number</div>
                        <div class="emp-tile-value"><?= Html::encode($model->account_number ?: '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-slate"><i class="fas fa-barcode"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">IFSC</div>
                        <div class="emp-tile-value"><?= Html::encode($model->ifsc_code ?: '—') ?></div>
                    </div>
                </div>
            </div>

            <!-- Statutory IDs -->
            <div class="emp-section-title"><i class="fas fa-file-shield text-warning"></i> Statutory <span class="line"></span></div>
            <div class="emp-tile-row">
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-amber"><i class="fas fa-id-card"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">PAN</div>
                        <div class="emp-tile-value"><?= Html::encode($model->pan ?: '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-rose"><i class="fas fa-file-medical"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">ESI</div>
                        <div class="emp-tile-value"><?= Html::encode($model->esi_details ?: '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-violet"><i class="fas fa-piggy-bank"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">PF</div>
                        <div class="emp-tile-value"><?= Html::encode($model->pf_details ?: '—') ?></div>
                    </div>
                </div>
                <div class="emp-tile">
                    <div class="emp-tile-ic ic-violet"><i class="fas fa-fingerprint"></i></div>
                    <div class="emp-tile-body">
                        <div class="emp-tile-label">UAN</div>
                        <div class="emp-tile-value"><?= Html::encode($model->uan_details ?: '—') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!\backend\components\Crm::simple()): /* Work reports are IT timesheets — hidden in Green Divine mode */ ?>
    <!-- ── Work Reports ───────────────────────────────────────────────── -->
    <hr class="my-4">
    <h5 class="fw-semibold mb-3">Work Reports</h5>

    <div class="row g-3 mb-3">
        <!-- Current Month -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                    <span>Current Month</span>
                    <?php if ($cmPagination->totalCount > 0): ?>
                        <small class="text-muted fw-normal"><?= $cmPagination->totalCount ?> project<?= $cmPagination->totalCount != 1 ? 's' : '' ?></small>
                    <?php endif; ?>
                </div>
                <?php Pjax::begin(['id' => 'pjax-cm', 'enablePushState' => false, 'timeout' => 5000]) ?>
                <div class="table-responsive">
                    <table class="table work-report-table mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Project</th><th>Hours</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($currentMonth)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No entries.</td></tr>
                        <?php else: ?>
                            <?php foreach ($currentMonth as $i => $row): ?>
                            <tr>
                                <td class="text-muted"><?= $cmPagination->offset + $i + 1 ?></td>
                                <td><?= Html::encode($row['project_title'] ?: '—') ?></td>
                                <td><?= WorkReport::secondsToHours((int)$row['total_secs']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                        <?php if ($cmPagination->totalCount > 0): ?>
                        <tfoot>
                            <tr>
                                <td colspan="2">Total Hours</td>
                                <td><?= WorkReport::secondsToHours($cmTotal) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php if ($cmPagination->totalCount > $cmPagination->limit): ?>
                <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3">
                    <small class="text-muted">
                        <?= $cmPagination->offset + 1 ?>–<?= min($cmPagination->offset + $cmPagination->limit, $cmPagination->totalCount) ?>
                        of <?= $cmPagination->totalCount ?>
                    </small>
                    <?= LinkPager::widget([
                        'pagination'     => $cmPagination,
                        'options'        => ['class' => 'pagination pagination-sm mb-0'],
                        'firstPageLabel' => '«', 'lastPageLabel' => '»',
                        'prevPageLabel'  => '‹', 'nextPageLabel' => '›',
                        'maxButtonCount' => 5,
                    ]) ?>
                </div>
                <?php endif; ?>
                <?php Pjax::end() ?>
            </div>
        </div>

        <!-- Last Month -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                    <span>Last Month</span>
                    <?php if ($lmPagination->totalCount > 0): ?>
                        <small class="text-muted fw-normal"><?= $lmPagination->totalCount ?> project<?= $lmPagination->totalCount != 1 ? 's' : '' ?></small>
                    <?php endif; ?>
                </div>
                <?php Pjax::begin(['id' => 'pjax-lm', 'enablePushState' => false, 'timeout' => 5000]) ?>
                <div class="table-responsive">
                    <table class="table work-report-table mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Project</th><th>Hours</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($lastMonth)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No entries.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lastMonth as $i => $row): ?>
                            <tr>
                                <td class="text-muted"><?= $lmPagination->offset + $i + 1 ?></td>
                                <td><?= Html::encode($row['project_title'] ?: '—') ?></td>
                                <td><?= WorkReport::secondsToHours((int)$row['total_secs']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                        <?php if ($lmPagination->totalCount > 0): ?>
                        <tfoot>
                            <tr>
                                <td colspan="2">Total Hours</td>
                                <td><?= WorkReport::secondsToHours($lmTotal) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php if ($lmPagination->totalCount > $lmPagination->limit): ?>
                <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3">
                    <small class="text-muted">
                        <?= $lmPagination->offset + 1 ?>–<?= min($lmPagination->offset + $lmPagination->limit, $lmPagination->totalCount) ?>
                        of <?= $lmPagination->totalCount ?>
                    </small>
                    <?= LinkPager::widget([
                        'pagination'     => $lmPagination,
                        'options'        => ['class' => 'pagination pagination-sm mb-0'],
                        'firstPageLabel' => '«', 'lastPageLabel' => '»',
                        'prevPageLabel'  => '‹', 'nextPageLabel' => '›',
                        'maxButtonCount' => 5,
                    ]) ?>
                </div>
                <?php endif; ?>
                <?php Pjax::end() ?>
            </div>
        </div>
    </div>

    <!-- All Time -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
            <span>Total (All Time)</span>
            <?php if ($allTimePagination->totalCount > 0): ?>
                <small class="text-muted fw-normal"><?= $allTimePagination->totalCount ?> project<?= $allTimePagination->totalCount != 1 ? 's' : '' ?></small>
            <?php endif; ?>
        </div>
        <?php Pjax::begin(['id' => 'pjax-at', 'enablePushState' => false, 'timeout' => 5000]) ?>
        <div class="table-responsive">
            <table class="table work-report-table mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Project</th><th>Hours</th></tr>
                </thead>
                <tbody>
                <?php if (empty($allTime)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No entries.</td></tr>
                <?php else: ?>
                    <?php foreach ($allTime as $i => $row): ?>
                    <tr>
                        <td class="text-muted"><?= $allTimePagination->offset + $i + 1 ?></td>
                        <td><?= Html::encode($row['project_title'] ?: '—') ?></td>
                        <td><?= WorkReport::secondsToHours((int)$row['total_secs']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($allTime)): ?>
                <tfoot>
                    <tr>
                        <td colspan="2">Total Hours (All Time)</td>
                        <td><?= WorkReport::secondsToHours($allTimeTotal) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php if ($allTimePagination->totalCount > $allTimePagination->limit): ?>
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3">
            <small class="text-muted">
                Showing <?= $allTimePagination->offset + 1 ?>–<?= min($allTimePagination->offset + $allTimePagination->limit, $allTimePagination->totalCount) ?>
                of <?= $allTimePagination->totalCount ?>
            </small>
            <?= LinkPager::widget([
                'pagination'     => $allTimePagination,
                'options'        => ['class' => 'pagination pagination-sm mb-0'],
                'linkOptions'    => ['class' => 'page-link'],
                'maxButtonCount' => 5,
            ]) ?>
        </div>
        <?php endif; ?>
        <?php Pjax::end() ?>
    </div>
    <?php endif; /* work reports */ ?>
</div>

<!-- ── Update PIN Modal ───────────────────────────────────────────────── -->
<div class="modal fade" id="updatePinModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold"><i class="fas fa-key"></i> Update PIN</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="pinUpdateMsg"></div>
                <div class="mb-2">
                    <label class="form-label form-label-sm">Password <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="password" id="pinPassword" class="form-control form-control-sm" placeholder="Your password" autocomplete="current-password">
                        <button class="btn btn-outline-secondary pin-toggle-vis" type="button" data-target="pinPassword" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm">New PIN (4 digits) <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="password" id="newPin" class="form-control form-control-sm" maxlength="4" placeholder="4 digits" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,4)">
                        <button class="btn btn-outline-secondary pin-toggle-vis" type="button" data-target="newPin" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm">Confirm PIN <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="password" id="confirmPin" class="form-control form-control-sm" maxlength="4" placeholder="Repeat PIN" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,4)">
                        <button class="btn btn-outline-secondary pin-toggle-vis" type="button" data-target="confirmPin" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                    </div>
                    <div id="pinMatchMsg" class="small mt-1"></div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="pinUpdateBtn">Save PIN</button>
            </div>
        </div>
    </div>
</div>

<!-- ── View PIN Modal ─────────────────────────────────────────────────── -->
<div class="modal fade" id="viewPinModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold"><i class="fas fa-lock"></i> View PIN</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewPinMsg"></div>
                <div id="viewPinPasswordSection">
                    <label class="form-label form-label-sm">Password <span class="text-danger">*</span></label>
                    <input type="password" id="viewPinPassword" class="form-control form-control-sm" placeholder="Your password" autocomplete="current-password">
                </div>
                <div id="viewPinResult" class="d-none text-center">
                    <p class="text-muted mb-1 small">Current PIN</p>
                    <h2 class="fw-bold" id="viewPinValue"></h2>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-secondary" id="viewPinBtn">Show PIN</button>
            </div>
        </div>
    </div>
</div>

<?php
$developerId  = $model->id;
$updatePinUrl = Url::to(['/developer/update-emp-pin']);
$viewPinUrl   = Url::to(['/developer/view-emp-pin']);
$csrf         = Yii::$app->request->csrfToken;
$csrfParam    = Yii::$app->request->csrfParam;

$this->registerJs(<<<JS
// ── Toggle password/pin visibility ───────────────────────────────────────
document.querySelectorAll('.pin-toggle-vis').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = document.getElementById(this.getAttribute('data-target'));
        input.type = input.type === 'password' ? 'text' : 'password';
    });
});

// ── PIN match indicator ─────────────────────────────────────────────────
['newPin','confirmPin'].forEach(function(id) {
    document.getElementById(id).addEventListener('input', function() {
        var pin = document.getElementById('newPin').value;
        var confirm = document.getElementById('confirmPin').value;
        var msg = document.getElementById('pinMatchMsg');
        if (!confirm) { msg.innerHTML = ''; return; }
        if (pin === confirm) {
            msg.innerHTML = '<span class="text-success">PINs match.</span>';
        } else {
            msg.innerHTML = '<span class="text-danger">PINs do not match.</span>';
        }
    });
});

// ── Reset modal state on close ───────────────────────────────────────────
document.getElementById('updatePinModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('pinPassword').value = '';
    document.getElementById('newPin').value      = '';
    document.getElementById('confirmPin').value  = '';
    document.getElementById('pinUpdateMsg').innerHTML = '';
});
document.getElementById('viewPinModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('viewPinPassword').value = '';
    document.getElementById('viewPinMsg').innerHTML  = '';
    document.getElementById('viewPinResult').classList.add('d-none');
    document.getElementById('viewPinPasswordSection').classList.remove('d-none');
    document.getElementById('viewPinBtn').classList.remove('d-none');
});

// ── Blocked PINs ─────────────────────────────────────────────────────────
var blockedPins = ['0000','1111','2222','3333','4444','5555','6666','7777','8888','9999','1234','4321'];

// ── Update PIN ───────────────────────────────────────────────────────────
document.getElementById('pinUpdateBtn').addEventListener('click', function() {
    var password   = document.getElementById('pinPassword').value.trim();
    var pin        = document.getElementById('newPin').value.trim();
    var confirmPin = document.getElementById('confirmPin').value.trim();
    var msg        = document.getElementById('pinUpdateMsg');
    msg.innerHTML  = '';

    if (!password || !pin || !confirmPin) {
        msg.innerHTML = '<div class="alert alert-warning alert-sm py-1 mb-2">All fields are required.</div>';
        return;
    }
    if (!/^\d{4}$/.test(pin)) {
        msg.innerHTML = '<div class="alert alert-warning alert-sm py-1 mb-2">PIN must be exactly 4 digits.</div>';
        return;
    }
    if (blockedPins.indexOf(pin) !== -1) {
        msg.innerHTML = '<div class="alert alert-warning alert-sm py-1 mb-2">This PIN is too simple. Choose another.</div>';
        return;
    }
    if (pin !== confirmPin) {
        msg.innerHTML = '<div class="alert alert-warning alert-sm py-1 mb-2">PINs do not match.</div>';
        return;
    }

    fetch('$updatePinUrl', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '$csrfParam=' + encodeURIComponent('$csrf')
            + '&developer_id=$developerId'
            + '&password=' + encodeURIComponent(password)
            + '&pin=' + encodeURIComponent(pin)
            + '&confirm_pin=' + encodeURIComponent(confirmPin)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 1) {
            msg.innerHTML = '<div class="alert alert-success alert-sm py-1 mb-2">' + data.msg + '</div>';
            setTimeout(function() { location.reload(); }, 1200);
        } else {
            msg.innerHTML = '<div class="alert alert-danger alert-sm py-1 mb-2">' + data.msg + '</div>';
        }
    });
});

// ── View PIN ─────────────────────────────────────────────────────────────
document.getElementById('viewPinBtn').addEventListener('click', function() {
    var password = document.getElementById('viewPinPassword').value.trim();
    var msg      = document.getElementById('viewPinMsg');
    msg.innerHTML = '';

    if (!password) {
        msg.innerHTML = '<div class="alert alert-warning alert-sm py-1 mb-2">Password is required.</div>';
        return;
    }

    fetch('$viewPinUrl', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '$csrfParam=' + encodeURIComponent('$csrf')
            + '&developer_id=$developerId'
            + '&view_password=' + encodeURIComponent(password)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 1) {
            document.getElementById('viewPinPasswordSection').classList.add('d-none');
            document.getElementById('viewPinBtn').classList.add('d-none');
            document.getElementById('viewPinValue').textContent = data.pin;
            document.getElementById('viewPinResult').classList.remove('d-none');
        } else {
            msg.innerHTML = '<div class="alert alert-danger alert-sm py-1 mb-2">' + data.msg + '</div>';
        }
    });
});
JS);
?>
