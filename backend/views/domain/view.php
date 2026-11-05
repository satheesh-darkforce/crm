<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Domain $model */
/** @var \backend\models\DomainRenewalHistory[] $history */
/** @var \backend\models\DomainFollowUp[] $followups */
/** @var \backend\models\DomainFollowUp $followupModel */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Domain: ' . $model->domain_name;

$statusLabels = \backend\models\Domain::domainStatusLabels();
$today = date('Y-m-d');
?>
<div class="ui-page domain-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->domain_name) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-sync-alt"></i> Renewal', ['renewal', 'id' => $model->id], ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', Yii::$app->request->referrer ?: \yii\helpers\Url::to(['index']), ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <dl class="row mb-0" style="font-size:.875rem">
                        <dt class="col-sm-3 text-muted">Client</dt>
                        <dd class="col-sm-9 fw-semibold"><?= Html::encode($model->client->client_name ?? '—') ?></dd>

                        <dt class="col-sm-3 text-muted">Domain Name</dt>
                        <dd class="col-sm-9">
                            <a href="<?= Html::encode($model->domain_name) ?>" target="_blank" rel="noreferrer noopener"><?= Html::encode($model->domain_name) ?></a>
                        </dd>

                        <dt class="col-sm-3 text-muted">Service Provider</dt>
                        <dd class="col-sm-9"><?= Html::encode($model->service_provider ?? '—') ?></dd>

                        <dt class="col-sm-3 text-muted">Hosting Server</dt>
                        <dd class="col-sm-9"><?= Html::encode($model->hosting_server ?? '—') ?></dd>

                        <dt class="col-sm-3 text-muted">Domain Username</dt>
                        <dd class="col-sm-9"><?= Html::encode($model->domain_username ?? '—') ?></dd>

                        <dt class="col-sm-3 text-muted">Domain Password</dt>
                        <dd class="col-sm-9">
                            <input type="password" id="pwd-field" class="form-control form-control-sm d-inline-block" style="width:auto;max-width:200px" value="<?= Html::encode($model->domain_password ?? '') ?>" readonly>
                            <button type="button" id="pwd-toggle-btn" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="togglePwd()" title="Click to show/hide password"><i class="fas fa-eye"></i> Show</button>
                            <small class="text-muted ms-1">Click to show/hide</small>
                        </dd>

                        <dt class="col-sm-3 text-muted">Price</dt>
                        <dd class="col-sm-9">₹<?= number_format((float)$model->price, 2) ?></dd>

                        <dt class="col-sm-3 text-muted">Date of Purchase</dt>
                        <dd class="col-sm-9"><?= $model->date_of_purchase ? date('d M Y', strtotime($model->date_of_purchase)) : '—' ?></dd>

                        <dt class="col-sm-3 text-muted">Expiry Date</dt>
                        <dd class="col-sm-9">
                            <?php if ($model->expiry_date): ?>
                                <?php if ($model->expiry_date < $today): ?>
                                    <span class="text-danger fw-semibold"><?= date('d M Y', strtotime($model->expiry_date)) ?></span>
                                    <span class="badge bg-danger ms-1">Expired</span>
                                <?php elseif ($model->expiry_date <= date('Y-m-d', strtotime('+30 days'))): ?>
                                    <span class="text-warning fw-semibold"><?= date('d M Y', strtotime($model->expiry_date)) ?></span>
                                    <span class="badge bg-warning text-dark ms-1">Expiring Soon</span>
                                <?php else: ?>
                                    <?= date('d M Y', strtotime($model->expiry_date)) ?>
                                <?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </dd>

                        <dt class="col-sm-3 text-muted">Status</dt>
                        <dd class="col-sm-9"><?= Html::encode($statusLabels[$model->domain_status] ?? '—') ?></dd>

                        <?php if ($model->description): ?>
                        <dt class="col-sm-3 text-muted">Description</dt>
                        <dd class="col-sm-9"><?= nl2br(Html::encode($model->description)) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Follow-up Logs -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold py-2">Follow-up Logs</div>
        <div class="card-body pb-1">
            <!-- Add follow-up form -->
            <?php $form = ActiveForm::begin([
                'action' => ['add-followup', 'id' => $model->id],
                'method' => 'post',
                'options' => ['class' => 'row g-2 align-items-end mb-3'],
            ]); ?>
            <div class="col-md-2">
                <?= $form->field($followupModel, 'contacted_date')->widget(\kartik\date\DatePicker::class, [
                    'type' => \kartik\date\DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'Contacted date'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                ])->label('Contacted Date') ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($followupModel, 'description')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Notes / outcome'])->label('Description') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($followupModel, 'next_followup')->widget(\kartik\date\DatePicker::class, [
                    'type' => \kartik\date\DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'Next follow-up'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                ])->label('Next Follow-up') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($followupModel, 'followup_status')->dropDownList(
                    \backend\models\DomainFollowUp::statusLabels(),
                    ['class' => 'form-select form-select-sm', 'prompt' => 'Status']
                )->label('Status') ?>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <?= Html::submitButton('+ Log', ['class' => 'btn btn-sm btn-primary w-100']) ?>
            </div>
            <?php ActiveForm::end(); ?>

            <!-- Follow-up history table -->
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Contacted</th>
                        <th>Description</th>
                        <th>Next Follow-up</th>
                        <th>Status</th>
                        <th>Added By</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($followups)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No follow-ups logged yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($followups as $i => $f): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><?= $f->contacted_date ? date('d M Y', strtotime($f->contacted_date)) : '—' ?></td>
                        <td><?= Html::encode($f->description) ?></td>
                        <td><?= $f->next_followup ? date('d M Y', strtotime($f->next_followup)) : '—' ?></td>
                        <td>
                            <span class="badge bg-<?= $f->followup_status == 2 ? 'success' : 'warning text-dark' ?>">
                                <?= $f->followup_status == 2 ? 'Closed' : 'Open' ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= Html::encode($f->createdBy->username ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Renewal History -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold py-2 d-flex justify-content-between align-items-center">
            <span>Renewal History</span>
            <?= Html::a('+ Add Renewal', ['renewal', 'id' => $model->id], ['class' => 'btn btn-outline-success btn-sm']) ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>New Expiry</th>
                        <th>Notes</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="6" class="text-muted text-center py-3">No renewal history.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $i => $h): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td>₹<?= number_format((float)$h->amount_received, 2) ?></td>
                        <td><?= $h->amount_received_date ? date('d M Y', strtotime($h->amount_received_date)) : '—' ?></td>
                        <td><?= $h->expiry_date ? date('d M Y', strtotime($h->expiry_date)) : '—' ?></td>
                        <td><?= Html::encode($h->notes ?? '—') ?></td>
                        <td class="text-muted"><?= $h->created_at ? date('d M Y', strtotime($h->created_at)) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function togglePwd() {
    var field = document.getElementById('pwd-field');
    var btn   = document.getElementById('pwd-toggle-btn');
    if (field.type === 'password') {
        field.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye"></i> Hide';
    } else {
        field.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i> Show';
    }
}
</script>
