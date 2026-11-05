<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use kartik\select2\Select2;

$this->title = 'RFID Cards';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$q          = Yii::$app->request->get();
?>
<style>
/* Assign / Unassign modal — compact spacing + proper vertical centering. */
#assignModal .modal-content{font-size:.875rem}
#assignModal .modal-header{align-items:center;padding:.55rem .85rem;gap:.5rem}
#assignModal .modal-header .modal-title{font-size:.95rem;line-height:1}
#assignModal .modal-header .btn-close{flex-shrink:0;margin:0;padding:.5rem;background-size:.75em;align-self:center}
#assignModal .modal-body{padding:.75rem .85rem}
#assignModal .modal-body p{margin-bottom:.4rem}
#assignModal .modal-body .mb-2{margin-bottom:.4rem !important}
#assignModal .modal-body small.form-text{font-size:.72rem;display:block;margin-top:.2rem}
#assignModal .modal-footer{padding:.5rem .85rem;gap:.4rem}
/* Select2 single — match form-select-sm height and centre the caret + clear. */
#assignModal .select2-container--default .select2-selection--single{height:calc(1.5em + .5rem + 2px);display:flex;align-items:center;padding-right:1.75rem}
#assignModal .select2-container--default .select2-selection--single .select2-selection__rendered{flex:1;line-height:1.3;padding-left:.5rem;padding-right:.25rem;font-size:.85rem}
#assignModal .select2-container--default .select2-selection--single .select2-selection__arrow{top:0;right:.25rem;height:100%;width:1.25rem;display:flex;align-items:center;justify-content:center}
#assignModal .select2-container--default .select2-selection--single .select2-selection__arrow b{position:static;margin:0}
#assignModal .select2-container--default .select2-selection--single .select2-selection__clear{position:absolute;right:1.5rem;top:50%;transform:translateY(-50%);margin:0;font-size:.9rem;line-height:1;color:#888}
</style>

<div class="ui-page rfid-card-index">
    <div class="ui-head">
        <div>
            <h1>RFID Cards</h1>
            <div class="ui-sub"><?= $total ?> card<?= $total != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('+ Add Card', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="search" value="<?= Html::encode($q['search'] ?? '') ?>"
                           class="form-control form-control-sm" placeholder="Search by RFID code…">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="1" <?= ($q['status'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= ($q['status'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => $q['developer_id'] ?? '',
                        'data'          => ['' => 'All Employees'] + ArrayHelper::map($developers ?? [], 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'All Employees'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (!empty($q['search']) || ($q['status'] ?? '') !== '' || !empty($q['developer_id'])): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>RFID Code</th>
                        <th>Assigned To</th>
                        <th>Assigned From</th>
                        <th>Status</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No RFID cards found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $card): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold font-monospace"><?= Html::encode($card->code) ?></td>
                        <td>
                            <?php if ($card->developer): ?>
                                <span class="badge bg-success"><?= Html::encode($card->developer->developer_name ?? '—') ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= $card->assigned_from ? date('d M Y', strtotime($card->assigned_from)) : '—' ?></td>
                        <td>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input rfid-status-toggle" type="checkbox"
                                       data-id="<?= $card->id ?>"
                                       <?= $card->status ? 'checked' : '' ?>
                                       title="<?= $card->status ? 'Active — click to deactivate' : 'Inactive — click to activate' ?>">
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $card->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $card->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php if ($card->status == 1): ?>
                                    <li><?= Html::a('<i class="fas fa-users"></i> Assign / Unassign', '#', [
                                        'class' => 'dropdown-item assign-trigger', 'encode' => false,
                                        'data-id' => $card->id,
                                        'data-code' => $card->code,
                                        'data-dev' => $card->developer_id ?: '',
                                    ]) ?></li>
                                    <?php endif; ?>
                                    <?php if (!$card->developer_id): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-trash-alt"></i> Delete', '#', [
                                        'class' => 'dropdown-item text-danger rfid-delete-trigger', 'encode' => false,
                                        'data-id' => $card->id,
                                        'data-code' => $card->code,
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
        <div class="card-footer bg-white border-top-0 py-2">
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›']) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Modal (QA-016: inline assign) -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="assignForm" action="">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold">Assign / Unassign Card</h6>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Card: <strong id="assignCardCode"></strong></p>
                    <div class="mb-2">
                        <select name="developer_id" id="assign-dev-select" class="form-select form-select-sm" style="width:100%">
                            <option value="">— Unassign —</option>
                        </select>
                        <small class="form-text text-muted">Leave empty to unassign.</small>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Toggle status via AJAX — SweetAlert confirmation, no native alert/confirm.
document.querySelectorAll('.rfid-status-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        var id = this.dataset.id;
        var self = this;
        var action = self.checked ? 'Activate' : 'Deactivate';

        function revert() { self.checked = !self.checked; }
        function showError(msg) {
            if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: msg });
        }
        function doPost() {
            var csrfParam = '<?= Yii::$app->request->csrfParam ?>';
            var csrfToken = '<?= Yii::$app->request->getCsrfToken() ?>';
            var formData  = new FormData();
            formData.append(csrfParam, csrfToken);

            fetch('<?= Url::to(['toggle-status', 'id' => '']) ?>' + id, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(r) {
                if (r.ok || r.status === 302) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success', title: action + 'd',
                            text: 'Card status updated.',
                            timer: 1200, showConfirmButton: false, allowOutsideClick: false,
                        }).then(function () { location.reload(); });
                    } else {
                        location.reload();
                    }
                } else {
                    revert();
                    showError('Failed to update status');
                }
            }).catch(function() {
                revert();
                showError('Failed to update status');
            });
        }

        if (!window.Swal) {
            // Swal missing — revert and abort silently rather than using native confirm.
            revert();
            return;
        }
        Swal.fire({
            icon: 'question',
            title: action + ' this card?',
            showCancelButton: true,
            confirmButtonText: 'Yes, ' + action.toLowerCase(),
            cancelButtonText:  'Cancel',
            confirmButtonColor: self.checked ? '#198754' : '#d33',
        }).then(function (result) {
            if (result.isConfirmed) {
                doPost();
            } else {
                revert();
            }
        });
    });
});

// All active developers as [{id, name}, ...]
var rfidAllDevs   = <?= \yii\helpers\Json::encode(ArrayHelper::map($developers ?? [], 'id', 'developer_name')) ?>;
// Map: developer_id => card_id for every active assignment.
var rfidDevToCard = <?= \yii\helpers\Json::encode($devToCard ?? []) ?>;

function buildAssignSelect(cardId, preselectedDev) {
    var $sel = jQuery('#assign-dev-select');

    // Destroy previous Select2 instance if initialised.
    if ($sel.hasClass('select2-hidden-accessible')) {
        $sel.select2('destroy');
    }

    // Rebuild <option> list: empty + devs not assigned to another card
    // (the card's own current dev, if any, is always kept).
    $sel.empty();
    $sel.append(new Option('— Unassign —', '', false, false));
    Object.keys(rfidAllDevs).forEach(function (devId) {
        var numericDevId = Number(devId);
        var otherCard    = rfidDevToCard[numericDevId];
        if (otherCard && otherCard !== cardId) return;
        $sel.append(new Option(rfidAllDevs[devId], devId, false, false));
    });

    // Re-init Select2 scoped to the modal so search + focus work.
    $sel.select2({
        placeholder: 'Select employee…',
        allowClear: true,
        width: '100%',
        dropdownParent: jQuery('#assignModal'),
    });

    $sel.val(preselectedDev || '').trigger('change');
}

document.querySelectorAll('.assign-trigger').forEach(function(el) {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        var id   = this.getAttribute('data-id');
        var code = this.getAttribute('data-code');
        var dev  = this.getAttribute('data-dev');
        document.getElementById('assignCardCode').textContent = code;
        document.getElementById('assignForm').action = '<?= Url::to(['assign']) ?>?id=' + id;

        buildAssignSelect(Number(id), dev);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('assignModal')).show();
    });
});
</script>

<?php
$deleteUrlBase = Url::to(['delete', 'id' => '']);
$csrfParam     = Yii::$app->request->csrfParam;
$csrfToken     = Yii::$app->request->getCsrfToken();
$this->registerJs(<<<JS
jQuery(document).on('click', '.rfid-delete-trigger', function (e) {
    e.preventDefault();
    var id   = jQuery(this).data('id');
    var code = jQuery(this).data('code');
    if (!window.Swal) return;

    Swal.fire({
        title: 'Delete RFID Card',
        html:  'Card: <strong>' + jQuery('<div>').text(code).html() + '</strong>',
        input: 'textarea',
        inputLabel: 'Reason for deletion',
        inputPlaceholder: 'Why is this card being deleted?',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#d33',
        cancelButtonText:  'Cancel',
        preConfirm: function (value) {
            if (!value || !value.trim()) {
                Swal.showValidationMessage('Please enter a reason.');
                return false;
            }
            return value.trim();
        }
    }).then(function (result) {
        if (!result.isConfirmed) return;
        var data = { reason: result.value };
        data['$csrfParam'] = '$csrfToken';
        jQuery.ajax({
            url: '$deleteUrlBase' + id,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (r) {
                if (r && r.success) {
                    Swal.fire({ icon: 'success', title: 'Deleted', text: r.message || 'RFID card deleted.',
                        timer: 1500, showConfirmButton: false, allowOutsideClick: false })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: (r && r.message) ? r.message : 'Unable to delete.' });
                }
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Something went wrong.';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });
});
JS, \yii\web\View::POS_READY);
?>
