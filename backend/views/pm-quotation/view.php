<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Quotation $model */
/** @var \backend\models\QuotationDetail $detail */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = $model->unique_id ?: 'PM Quotation #' . $model->id;
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
</style>

<div class="pm-quotation-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0"><?= Html::encode($this->title) ?></h4>
            <small class="text-muted"><?= Html::encode($model->project_name) ?></small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-warning btn-sm', 'encode' => false]) ?>
            <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger btn-sm',
                'data'  => ['confirm' => 'Delete this PM quotation?', 'method' => 'post'],
            ]) ?>
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Client details -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold border-bottom">Client Details</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th class="text-muted" style="width:40%">Client</th>
                            <td><?= Html::encode($model->client_name) ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Email</th>
                            <td><?= Html::encode($model->email) ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Mobile</th>
                            <td><?= Html::encode($model->mobile ?: '—') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Project</th>
                            <td><?= Html::encode($model->project_name) ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Created</th>
                            <td><?= $model->created_date ? date('d M Y', strtotime($model->created_date)) : '—' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quote versions -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold border-bottom">
                    Quote Versions (<?= count($model->details) ?>)
                </div>
                <?php if (!empty($model->details)): ?>
                <div class="table-responsive">
                    <table class="table list-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th class="text-end">Amount</th>
                                <th>Currency</th>
                                <th>Sent Date</th>
                                <th>Attachments</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($model->details as $i => $d): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="text-end fw-semibold">
                                <?= $d->quote_amount ? number_format((float)$d->quote_amount, 2) : '—' ?>
                            </td>
                            <td class="small"><?= Html::encode($d->currency->code ?? '—') ?></td>
                            <td class="small"><?= $d->proposal_send_date ? date('d M Y', strtotime($d->proposal_send_date)) : '—' ?></td>
                            <td class="small">
                                <?php foreach ($d->attachments ?? [] as $att): ?>
                                    <a href="<?= Yii::$app->request->baseUrl . $att->path ?>" target="_blank" rel="noreferrer noopener"
                                       class="d-block text-truncate" style="max-width:150px">
                                        Attachment
                                    </a>
                                <?php endforeach; ?>
                                <?php if (empty($d->attachments)): ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="card-body text-muted text-center py-3">No quote versions yet.</div>
                <?php endif; ?>
            </div>

            <!-- Add sub-quotation -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold border-bottom">Add Quote Version</div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'action' => ['/pm-quotation/add-sub-quotation'],
                        'options' => ['enctype' => 'multipart/form-data'],
                    ]); ?>
                    <input type="hidden" name="QuotationDetail[quotation_id]" value="<?= $model->id ?>">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <?= $form->field($detail, 'quote_amount')->label('Amount')
                                ->input('number', ['step' => '0.01', 'class' => 'form-control form-control-sm']) ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($detail, 'currency_id')->label('Currency')
                                ->dropDownList(
                                    \yii\helpers\ArrayHelper::map(
                                        \backend\models\Currency::find()->where(['status' => 1])->all(),
                                        'id', 'code'
                                    ),
                                    ['class' => 'form-select form-select-sm', 'prompt' => 'Select…']
                                ) ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($detail, 'proposal_send_date')->label('Sent Date')
                                ->input('date', ['class' => 'form-control form-control-sm']) ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Attachments</label>
                            <input type="file" name="QuotationDetail[attachmentFiles][]"
                                   class="form-control form-control-sm" multiple
                                   accept=".pdf,.doc,.docx,.ppt,.pptx">
                        </div>
                    </div>
                    <div class="mt-2">
                        <?= Html::submitButton('Add Version', ['class' => 'btn btn-primary btn-sm']) ?>
                    </div>
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
