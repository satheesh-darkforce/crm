<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Proposal $model */
/** @var \backend\models\ClientModel[] $clients */
/** @var \backend\models\Project[] $projects */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use kartik\select2\Select2;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Proposal' : 'Edit Proposal';
?>
<div class="proposal-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= $this->title ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
            <div class="row g-3">

                <div class="col-md-6">
                    <?= $form->field($model, 'title')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'Proposal title']) ?>
                </div>

                <div class="col-md-6">
                    <?= $form->field($model, 'client_id')->widget(Select2::class, [
                        'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => '— Select Client —', 'id' => 'proposal-client_id'],
                        'pluginOptions' => ['allowClear' => true],
                    ]) ?>
                </div>

                <div class="col-md-6">
                    <?= $form->field($model, 'project_id')->widget(Select2::class, [
                        'data'          => ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => '— Select Project —', 'id' => 'proposal-project_id'],
                        'pluginOptions' => ['allowClear' => true],
                    ]) ?>
                </div>

                <?php if (!$isNew): ?>
                <div class="col-md-6">
                    <?= $form->field($model, 'proposal_status')->dropDownList(
                        \backend\models\Proposal::statusLabels(),
                        ['class' => 'form-select form-select-sm']
                    ) ?>
                </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <?php if (!$isNew && $model->attachment): ?>
                        <div class="mb-2">
                            <small class="text-muted">Current attachment:</small><br>
                            <a href="<?= Yii::$app->request->baseUrl . $model->attachment ?>" target="_blank" rel="noreferrer noopener" class="small">
                                <i class="fa fa-file"></i> View File
                            </a>
                        </div>
                    <?php endif; ?>
                    <?= $form->field($model, 'attachmentFile')->fileInput(['accept' => '.pdf,.doc,.docx,.ppt,.pptx'])
                        ->label($isNew ? 'Attachment' : 'Replace Attachment (PDF/DOC/DOCX/PPT, max 20MB)') ?>
                </div>

            </div>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($isNew ? 'Add Proposal' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<script>
$('#proposal-client_id').on('change', function () {
    var clientId = $(this).val();
    var $ps = $('#proposal-project_id');
    $ps.val(null).trigger('change');
    if (!clientId) return;

    $.post('<?= Url::to(['/proposal/get-projects-by-client']) ?>',
        { client_id: clientId, '<?= Yii::$app->request->csrfParam ?>': '<?= Yii::$app->request->getCsrfToken() ?>' },
        function (data) {
            $ps.empty().append('<option value="">— Select Project —</option>');
            $.each(data, function (i, p) { $ps.append(new Option(p.text, p.id)); });
            $ps.trigger('change.select2');
        }
    );
});
</script>
