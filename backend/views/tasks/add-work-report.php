<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Tasks $task */

use kartik\date\DatePicker;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Add Work Report';
?>

<div class="tasks-add-work-report">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><?= $this->title ?></h5>
            <small class="text-muted">Task: <strong><?= Html::encode($task->task) ?></strong>
                (<?= Html::encode($task->project->title ?? '—') ?>)</small>
        </div>
        <?= Html::a('Back', ['view', 'id' => $task->id], ['class' => 'btn btn-sm btn-secondary']) ?>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="work-report-form" method="post"
                  action="<?= Url::to(['add-work-report', 'id' => $task->id]) ?>">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <input type="hidden" name="submitworkreport" value="1">
                <input type="hidden" id="nor" value="1">

                <!-- Date -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Date <span class="text-danger">*</span></label>
                        <?= DatePicker::widget([
                            'name'          => 'date',
                            'value'         => date('Y-m-d'),
                            'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                            'options'       => ['placeholder' => 'yyyy-mm-dd', 'class' => 'form-control form-control-sm'],
                            'pluginOptions' => [
                                'autoclose'  => true,
                                'format'     => 'yyyy-mm-dd',
                                'endDate'    => date('Y-m-d'),
                            ],
                        ]) ?>
                    </div>
                </div>

                <!-- Time entries table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="work-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:5%">#</th>
                                <th style="width:15%">From</th>
                                <th style="width:15%">To</th>
                                <th style="width:15%">Total Time <span class="text-danger">*</span></th>
                                <th>Description</th>
                                <th style="width:6%"></th>
                            </tr>
                        </thead>
                        <tbody id="tblbody">
                            <tr id="row-input">
                                <td></td>
                                <td><input type="text" id="fromtime1" class="form-control form-control-sm" placeholder="HH:MM"></td>
                                <td><input type="text" id="totime1" class="form-control form-control-sm" placeholder="HH:MM"></td>
                                <td><input type="text" id="totaltime1" class="form-control form-control-sm" placeholder="HH:MM" value="00:00"></td>
                                <td><textarea id="description1" class="form-control form-control-sm" rows="1"></textarea></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-success" id="btn-add-row">+</button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end fw-semibold">Total:</td>
                                <td><span id="sumtotaltime" class="fw-bold">00:00</span></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Save Work Report</button>
            </form>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<'JS'
var rowCount = 1;

function toSeconds(time) {
    var parts = time.split(':');
    return (+parts[0]) * 3600 + (+parts[1] || 0) * 60;
}

function toHHMM(sec) {
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
}

function updateTotal() {
    var total = 0;
    jQuery('input[name="totaltime[]"]').each(function() {
        if (jQuery(this).val()) total += toSeconds(jQuery(this).val());
    });
    jQuery('#sumtotaltime').text(toHHMM(total));
    updateSerial();
}

function updateSerial() {
    jQuery('#tblbody tr.data-row').each(function(i) {
        jQuery(this).find('.sno').text(i + 1);
    });
}

// Add row
jQuery('#btn-add-row').on('click', function() {
    var from  = jQuery('#fromtime1').val();
    var to    = jQuery('#totime1').val();
    var total = jQuery('#totaltime1').val();
    var desc  = jQuery('#description1').val();

    if (!total || total === '00:00') {
        Swal.fire({icon: 'error', title: 'Validation Error', text: 'Please enter Total Time'});
        jQuery('#totaltime1').focus();
        return;
    }

    rowCount++;
    var tr = jQuery('<tr>', {'class': 'data-row', id: 'row-' + rowCount});
    tr.html(
        '<td class="sno"></td>' +
        '<td><input type="text" name="fromtime[]" class="form-control form-control-sm" value="' + from + '" readonly></td>' +
        '<td><input type="text" name="totime[]" class="form-control form-control-sm" value="' + to + '" readonly></td>' +
        '<td><input type="text" name="totaltime[]" class="form-control form-control-sm dur" value="' + total + '" readonly></td>' +
        '<td><textarea name="description[]" class="form-control form-control-sm" rows="1" readonly>' + desc + '</textarea></td>' +
        '<td><button type="button" class="btn btn-sm btn-danger btn-remove-row" data-row="' + rowCount + '">-</button></td>'
    );
    jQuery('#tblbody').append(tr);

    jQuery('#fromtime1').val('');
    jQuery('#totime1').val('');
    jQuery('#totaltime1').val('00:00');
    jQuery('#description1').val('');

    updateTotal();
});

// Remove row (delegated)
jQuery('#tblbody').on('click', '.btn-remove-row', function() {
    var rowId = jQuery(this).data('row');
    jQuery('#row-' + rowId).remove();
    updateTotal();
});

// Form submit validation with SweetAlert2
jQuery('#work-report-form').on('submit', function(e) {
    e.preventDefault();
    var form = this;
    var rows = jQuery('input[name="totaltime[]"]');

    if (rows.length === 0) {
        Swal.fire({icon: 'error', title: 'Validation Error', text: 'Please add at least one time entry using the + button.'});
        return false;
    }

    var dateVal = jQuery('input[name="date"]').val();
    if (!dateVal) {
        Swal.fire({icon: 'error', title: 'Validation Error', text: 'Please select a date.'});
        return false;
    }

    Swal.fire({
        icon: 'question',
        title: 'Confirm Submission',
        text: 'Are you sure to submit these work reports for ' + dateVal + '?',
        showCancelButton: true,
        confirmButtonText: 'Yes, submit',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
});
JS, \yii\web\View::POS_READY);
?>
