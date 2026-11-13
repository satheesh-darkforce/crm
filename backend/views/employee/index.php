<?php
/** @var \yii\web\View $this */
/** @var array[] $staff */
/** @var \yii\data\Pagination $pagination */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'All Employees';

$req       = Yii::$app->request;
$hasFilter = $req->get('name') || $req->get('designation') || $req->get('email') || $req->get('mobile');
?>

<div class="ui-page employee-index">
    <div class="ui-head">
        <div>
            <h1>All Employees</h1>
            <div class="ui-sub"><?= $pagination->totalCount ?> staff member<?= $pagination->totalCount != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('+ Add Employee', ['/developer/create'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="name" value="<?= Html::encode($req->get('name')) ?>"
                           class="form-control form-control-sm" placeholder="Name…">
                </div>
                <div class="col-md-2">
                    <input type="text" name="designation" value="<?= Html::encode($req->get('designation')) ?>"
                           class="form-control form-control-sm" placeholder="Designation…">
                </div>
                <div class="col-md-2">
                    <input type="text" name="email" value="<?= Html::encode($req->get('email')) ?>"
                           class="form-control form-control-sm" placeholder="Email…">
                </div>
                <div class="col-md-2">
                    <input type="text" name="mobile" value="<?= Html::encode($req->get('mobile')) ?>"
                           class="form-control form-control-sm" placeholder="Phone…">
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Search</button>
                    <?php if ($hasFilter): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($staff)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No staff found.</td></tr>
                <?php else: ?>
                <?php foreach ($staff as $i => $person):
                    $rowNum   = $pagination->offset + $i + 1;
                    $isDev    = $person['type'] === 'developer';
                ?>
                <tr>
                    <td class="text-muted"><?= $rowNum ?></td>
                    <td>
                        <a href="<?= Url::to($person['view_url']) ?>"
                           class="fw-semibold text-decoration-none text-dark">
                            <?= Html::encode($person['name']) ?>
                        </a>
                        <?php if ($person['email']): ?>
                            <div class="text-muted" style="font-size:.75rem"><?= Html::encode($person['email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= Html::encode($person['designation'] ?: '—') ?></td>
                    <td class="text-muted small"><?= Html::encode($person['mobile'] ?: '—') ?></td>
                    <td class="text-muted small"><?= Html::encode($person['email'] ?: '—') ?></td>
                    <td>
                        <?php if ($isDev): ?>
                            <span class="badge bg-success-subtle text-success border" style="font-size:.72rem">Employee</span>
                        <?php else: ?>
                            <span class="badge bg-primary-subtle text-primary border" style="font-size:.72rem">Employee</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                    type="button" data-bs-toggle="dropdown">&#8942;</button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:185px">

                                <!-- Common: View & Edit -->
                                <li><?= Html::a('<i class="fas fa-eye"></i> View', $person['view_url'], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                <li><?= Html::a('<i class="fas fa-pen"></i> Edit', $person['edit_url'], ['class' => 'dropdown-item', 'encode' => false]) ?></li>

                                <!-- Employee-only: Permissions -->
                                <?php if (!$isDev && $person['permission_url']): ?>
                                    <li><?= Html::a('<i class="fas fa-key"></i> Permissions', $person['permission_url'], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                <?php endif; ?>

                                <!-- Developer-only: Payroll actions -->
                                <?php if ($isDev): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><span class="dropdown-header" style="font-size:.7rem">PAYROLL</span></li>
                                    <li><?= Html::a('<i class="fas fa-chart-line"></i> Allowances &amp; Deductions', ['/salary-allowance/developer', 'id' => $person['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-file-alt"></i> Create Payslip', ['/payslip/create', 'developer_id' => $person['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                <?php endif; ?>

                                <!-- Common: Reset Password -->
                                <?php if ($person['user_id']): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="#"
                                           data-bs-toggle="modal" data-bs-target="#resetModal"
                                           data-user-id="<?= $person['user_id'] ?>"
                                           data-name="<?= Html::encode($person['name']) ?>"
                                           data-type="<?= $person['type'] ?>"
                                           data-id="<?= $person['id'] ?>">
                                            <i class="fas fa-lock"></i> Reset Password
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Deactivate -->
                                <li><hr class="dropdown-divider"></li>
                                <li><?= Html::a('<i class="fas fa-ban"></i> Deactivate', $person['delete_url'], [
                                    'class' => 'dropdown-item text-danger', 'encode' => false,
                                    'data-method' => 'post',
                                    'data-confirm' => 'Deactivate ' . Html::encode($person['name']) . '?',
                                ]) ?></li>

                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination->totalCount > $pagination->limit): ?>
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3">
            <small class="text-muted">
                Showing <?= $pagination->offset + 1 ?>–<?= min($pagination->offset + $pagination->limit, $pagination->totalCount) ?>
                of <?= $pagination->totalCount ?>
            </small>
            <?= LinkPager::widget([
                'pagination'     => $pagination,
                'options'        => ['class' => 'pagination pagination-sm mb-0'],
                'linkOptions'    => ['class' => 'page-link'],
                'maxButtonCount' => 5,
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="resetForm" action="">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <input type="hidden" name="_type" id="resetType" value="">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Reset Password</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Staff: <strong id="resetName"></strong></p>
                    <input type="password" name="new_password" class="form-control form-control-sm"
                           placeholder="New password (min 6 chars)" required minlength="6" autocomplete="new-password">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$empResetUrl = Url::to(['/employee/reset-password', 'id' => 0]);
$devResetUrl = Url::to(['/developer/reset-password', 'id' => 0]);

$this->registerJs(<<<JS
document.getElementById('resetModal').addEventListener('show.bs.modal', function(e) {
    var btn    = e.relatedTarget;
    var id     = btn.getAttribute('data-id');
    var name   = btn.getAttribute('data-name');
    var type   = btn.getAttribute('data-type');

    document.getElementById('resetName').textContent = name;
    document.getElementById('resetType').value = type;

    var baseUrl = type === 'developer' ? '$devResetUrl' : '$empResetUrl';
    document.getElementById('resetForm').action = baseUrl.replace('/0', '/' + id);
});
JS);
?>
