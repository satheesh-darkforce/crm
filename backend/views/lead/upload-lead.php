<?php
/** @var \yii\web\View $this */
/** @var string[] $rowErrors */

use yii\bootstrap5\Html;

$this->title = 'Upload Lead';
?>
<div class="lead-upload" style="max-width:820px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
                <label class="form-label">Excel / CSV file <span class="text-danger">*</span></label>
                <div class="d-flex gap-2">
                    <input type="file" name="lead_file" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Upload</button>
                </div>
                <div class="form-text">Imported rows are added to Lead Master as active prospects.</div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold small text-uppercase text-muted">File format</span>
            <?= Html::a('<i class="fas fa-download me-1"></i>Download sample', ['download-sample'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
        <div class="card-body small">
            <p class="mb-2">The first row is a header and is skipped. Columns must be exactly <strong>A–H</strong>:</p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2 text-center">
                    <tr class="table-light"><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>F</th><th>G</th><th>H</th></tr>
                    <tr><td>S.No</td><td>Account Name *</td><td>First Name *</td><td>Last Name</td><td>Designation</td><td>Email *</td><td>Phone Number *</td><td>City</td></tr>
                </table>
            </div>
            <p class="mb-0 text-muted">Rows with a missing required value, an invalid email or an invalid phone number are skipped and listed after the upload.</p>
        </div>
    </div>

    <?php if ($rowErrors): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold small text-uppercase text-danger">Skipped rows (<?= count($rowErrors) ?>)</div>
        <ul class="list-group list-group-flush small" style="max-height:320px;overflow:auto">
            <?php foreach ($rowErrors as $err): ?>
                <li class="list-group-item py-1"><?= Html::encode($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
