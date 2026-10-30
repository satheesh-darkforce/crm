<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use backend\models\Developer;
use common\components\UserAccess;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Employees';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="developer-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Employees</h5>
            <small class="text-muted"><?= $total ?> employee<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-arrow-down"></i> Export Active', ['export-employees'], ['class' => 'btn btn-sm btn-outline-success', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-down"></i> Export Ex-Employees', ['export-ex-employees'], ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
            <?= Html::a('+ Add Employee', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <?php
            $req = Yii::$app->request;
            $hasFilter = $req->get('name') || $req->get('designation') || $req->get('email') || $req->get('mobile')
                || ($req->get('department') !== null && $req->get('department') !== '');
            ?>
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <input type="text" name="name" value="<?= Html::encode($req->get('name')) ?>"
                           class="form-control form-control-sm" placeholder="Name…">
                </div>
                <div class="col-md-2">
                    <input type="text" name="designation" value="<?= Html::encode($req->get('designation')) ?>"
                           class="form-control form-control-sm" placeholder="Designation…">
                </div>
                <div class="col-md-2">
                    <?= Select2::widget([
                        'name'          => 'department',
                        'value'         => $req->get('department'),
                        'data'          => ['' => 'All Departments'] + Developer::$departments,
                        'options'       => ['placeholder' => 'All Departments'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
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
                <div class="col-auto ms-auto">
                    <?= Html::a('Ex-Employees', ['ex-employees'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Developer</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Emp Code</th>
                        <th>Mobile</th>
                        <th>Joined</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No developers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $dev): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $dev->id]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($dev->developer_name) ?>
                            </a>
                            <?php if ($dev->email_id): ?>
                                <div class="text-muted" style="font-size:.75rem"><?= Html::encode($dev->email_id) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= Html::encode($dev->designation ?? '—') ?></td>
                        <td>
                            <?php $deptName = Developer::$departments[(int)$dev->department] ?? null; ?>
                            <?php if ($deptName): ?>
                                <span class="badge bg-light text-dark border"><?= Html::encode($deptName) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($dev->emp_code): ?>
                                <span class="badge bg-light text-dark border font-monospace"><?= Html::encode($dev->emp_code) ?></span>
                                <?php if (UserAccess::checkAccess('developer/update-emp-code')): ?>
                                    <a href="javascript:void(0)" class="ms-1 text-decoration-none js-edit-emp-code"
                                       title="Update Emp Code"
                                       data-id="<?= $dev->id ?>"
                                       data-code="<?= Html::encode($dev->emp_code) ?>"
                                       data-name="<?= Html::encode($dev->developer_name) ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                <?php endif; ?>
                            <?php elseif (UserAccess::checkAccess('developer/update-emp-code')): ?>
                                <a href="javascript:void(0)" class="btn btn-sm btn-outline-primary py-0 px-2 js-edit-emp-code"
                                   title="Set Emp Code"
                                   data-id="<?= $dev->id ?>"
                                   data-code=""
                                   data-name="<?= Html::encode($dev->developer_name) ?>">
                                    <i class="bi bi-pencil-square"></i> Set
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= Html::encode($dev->mobile ?? '—') ?></td>
                        <td class="text-muted small">
                            <?= $dev->doj ? date('d M Y', strtotime($dev->doj)) : '—' ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:185px">
                                    <?php if (UserAccess::checkAccess('developer/view')): ?>
                                    <li><?= Html::a('View', ['view', 'id' => $dev->id], ['class' => 'dropdown-item']) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('developer/update')): ?>
                                    <li><?= Html::a('Edit', ['update', 'id' => $dev->id], ['class' => 'dropdown-item']) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('salary-allowance/index')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('Allowances &amp; Deductions', ['/salary-allowance/developer', 'id' => $dev->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('developer/access-permissions')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('Access Permissions', ['access-permissions', 'id' => $dev->id], ['class' => 'dropdown-item']) ?></li>
                                    <?php endif; ?>
                                    <?php // Hidden per user request (currently hiding — uncomment only if the user lets you know). ?>
                                    <?php /*
                                    <?php if (UserAccess::checkAccess('developer/access-permissions-new')): ?>
                                    <li><?= Html::a('Access Permissions New', ['access-permissions-new', 'id' => $dev->id], ['class' => 'dropdown-item']) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('developer/apk-access-permissions')): ?>
                                    <li><?= Html::a('APK Access Permissions', ['apk-access-permissions', 'id' => $dev->id], ['class' => 'dropdown-item']) ?></li>
                                    <?php endif; ?>
                                    */ ?>
                                    <?php if (UserAccess::checkAccess('developer/delete')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('Deactivate', ['delete', 'id' => $dev->id], [
                                        'class' => 'dropdown-item text-danger',
                                        'data-method' => 'post', 'data-confirm' => 'Deactivate ' . Html::encode($dev->developer_name) . '?',
                                    ]) ?></li>
                                    <?php endif; ?>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="resetForm" action="">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Reset Password</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Developer: <strong id="resetName"></strong></p>
                    <input type="password" name="new_password" class="form-control form-control-sm"
                           placeholder="New password" required minlength="6" autocomplete="new-password">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('resetModal')?.addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('resetName').textContent = btn.getAttribute('data-name');
    document.getElementById('resetForm').action = '/user/reset-password?id=' + btn.getAttribute('data-id');
});
</script>

<?php if (UserAccess::checkAccess('developer/update-emp-code')): ?>
<!-- Update Emp Code Modal -->
<div class="modal fade" id="empCodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="empCodeForm" method="post" action="<?= Url::to(['update-emp-code']) ?>">
                <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
                <input type="hidden" name="developerid" id="empCodeDeveloperId" value="">
                <div class="modal-header bg-warning-subtle py-2">
                    <h6 class="modal-title mb-0">Update Emp Code</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Developer: <strong id="empCodeName"></strong></p>
                    <label class="form-label small">Emp Code <span class="text-danger">*</span></label>
                    <input type="text" name="emp_code" id="empCodeInput"
                           class="form-control form-control-sm" maxlength="20" required
                           placeholder="e.g. MAE125">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning" id="empCodeSubmit">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    // Event delegation on document so dynamic re-renders (PJAX, table swaps)
    // don't strip the listener. Lazy-instantiate the Bootstrap modal only on
    // first click — avoids running new bootstrap.Modal() before the global
    // is ready.
    var _empCodeModal = null;
    function _getEmpCodeModal() {
        if (_empCodeModal) return _empCodeModal;
        var el = document.getElementById('empCodeModal');
        if (!el || !window.bootstrap || !bootstrap.Modal) return null;
        _empCodeModal = new bootstrap.Modal(el);
        return _empCodeModal;
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.js-edit-emp-code');
        if (!trigger) return;
        e.preventDefault();

        var modal = _getEmpCodeModal();
        if (!modal) {
            console.error('[empCode] Bootstrap Modal not available');
            alert('Modal failed to load. Please reload the page.');
            return;
        }

        var idEl   = document.getElementById('empCodeDeveloperId');
        var codeEl = document.getElementById('empCodeInput');
        var nameEl = document.getElementById('empCodeName');
        if (idEl)   idEl.value         = trigger.getAttribute('data-id')   || '';
        if (codeEl) codeEl.value       = trigger.getAttribute('data-code') || '';
        if (nameEl) nameEl.textContent = trigger.getAttribute('data-name') || '';
        modal.show();
        if (codeEl) setTimeout(function () { codeEl.focus(); codeEl.select(); }, 250);
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.id !== 'empCodeForm') return;
        e.preventDefault();

        var codeEl = document.getElementById('empCodeInput');
        var submit = document.getElementById('empCodeSubmit');
        var code   = ((codeEl && codeEl.value) || '').trim();
        if (!code) { if (codeEl) codeEl.focus(); return; }
        if (/^0+$/.test(code)) {
            var zMsg = 'Emp code cannot be zero.';
            if (window.Swal) { Swal.fire({title:'Validation Error', text: zMsg, icon:'error'}); }
            else { alert(zMsg); }
            if (codeEl) codeEl.focus();
            return;
        }

        if (submit) submit.disabled = true;
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (submit) submit.disabled = false;
            if (resp && resp.status === 1) {
                var m = _getEmpCodeModal(); if (m) m.hide();
                if (window.Swal) {
                    Swal.fire({title:'Saved', text: resp.msg, icon:'success', timer: 1200, showConfirmButton:false})
                        .then(function () { window.location.reload(); });
                } else {
                    window.location.reload();
                }
            } else {
                var msg = (resp && resp.msg) ? resp.msg : 'Save failed.';
                if (window.Swal) { Swal.fire({title:'Error', text: msg, icon:'error'}); }
                else { alert(msg); }
            }
        })
        .catch(function () {
            if (submit) submit.disabled = false;
            var msg = 'Network error.';
            if (window.Swal) { Swal.fire({title:'Error', text: msg, icon:'error'}); }
            else { alert(msg); }
        });
    });
})();
</script>
<?php endif; ?>
