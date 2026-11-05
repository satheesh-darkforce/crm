<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Holiday $model */
/** @var \backend\models\Developer[] $developers */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\widgets\LinkPager;
use kartik\date\DatePicker;
use kartik\select2\Select2;

$this->title = 'Holiday Settings';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$req = '<span class="text-danger">*</span>';
?>

<div class="ui-page holiday-index">
    <div class="ui-head"><div><h1>Manage Holidays</h1></div></div>

    <!-- Inline Add Holiday Form (matches Yii1 admin.php panel-heading) -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <strong class="small">Add Holiday</strong>
        </div>
        <div class="card-body py-3">
            <p class="text-muted small mb-2">Fields with <span class="text-danger">*</span> are required.</p>

            <?php $form = ActiveForm::begin(['id' => 'holidays-setup-form']); ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Date <?= $req ?></label>
                    <?= DatePicker::widget([
                        'model'         => $model,
                        'attribute'     => 'date',
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm', 'readonly' => true, 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd',
                            'startDate' => date('Y-m-d', strtotime('-30 days')),
                        ],
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Reason <?= $req ?></label>
                    <?= Html::activeTextInput($model, 'reason', ['class' => 'form-control form-control-sm', 'maxlength' => 255]) ?>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label small fw-semibold mb-0">Employee</label>
                        <a href="javascript:void(0)" id="holiday-emp-clear" class="small text-decoration-none">Clear all</a>
                    </div>
                    <?= Select2::widget([
                        'model'         => $model,
                        'attribute'     => 'employee_id',
                        'data'          => ArrayHelper::map($developers, 'user_id', 'developer_name'),
                        'options'       => ['id' => 'holiday-employee-id', 'placeholder' => 'Select Employee', 'multiple' => true],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= Html::submitButton('Save', ['class' => 'btn btn-primary btn-sm']) ?>
                </div>
            </div>

            <?php if ($model->hasErrors()): ?>
            <div class="alert alert-danger py-2 mt-2 mb-0">
                <?php foreach ($model->getFirstErrors() as $err): ?>
                    <div class="small"><?= Html::encode($err) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <!-- Holiday Grid -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th class="text-end" style="width:100px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-5">No holidays found.</td></tr>
                <?php else: ?>
                    <?php
                    $page     = $pagination ? $pagination->getPage() : 0;
                    $pageSize = $pagination ? $pagination->getPageSize() : count($models);
                    foreach ($models as $i => $h):
                    ?>
                    <tr>
                        <td class="text-muted"><?= $page * $pageSize + $i + 1 ?></td>
                        <td class="fw-semibold"><?= Html::encode($h->date) ?></td>
                        <td><?= Html::encode($h->reason) ?></td>
                        <td class="text-end">
                            <a href="javascript:void(0)" class="btn btn-outline-info act-btn view-holiday-btn" data-id="<?= $h->id ?>" title="View"><i class="fa fa-eye"></i></a>
                            &nbsp;
                            <?= Html::a('<i class="fa fa-pencil"></i>', ['update', 'id' => $h->id], [
                                'class'  => 'btn btn-outline-primary act-btn',
                                'title'  => 'Edit',
                                'encode' => false,
                            ]) ?>
                            &nbsp;
                            <?= Html::a('<i class="fa fa-trash"></i>', ['delete', 'id' => $h->id], [
                                'class'        => 'btn btn-outline-danger act-btn',
                                'title'        => 'Delete',
                                'encode'       => false,
                                'data-confirm' => 'Are you sure you want to delete this item?',
                                'data-method'  => 'post',
                            ]) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 py-2">
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination pagination-sm mb-0']]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Modal (matches Yii1 viewModallgBtn) -->
<div class="modal fade" id="viewHolidayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Holiday Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewHolidayBody">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.view-holiday-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.dataset.id;
        var modal = new bootstrap.Modal(document.getElementById('viewHolidayModal'));
        document.getElementById('viewHolidayBody').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';
        modal.show();
        fetch('<?= \yii\helpers\Url::to(['view', 'id' => '']) ?>' + id, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function(r) { return r.text(); })
            .then(function(html) { document.getElementById('viewHolidayBody').innerHTML = html; });
    });
});
</script>
<?php
// Registered via registerJs so it runs at POS_READY — after jQuery & Select2 are loaded.
// Select2 is jQuery-driven: clearing requires .val(null).trigger('change').
$this->registerJs("
    $('#holiday-emp-clear').on('click', function () {
        $('#holiday-employee-id').val(null).trigger('change');
    });
");
?>
