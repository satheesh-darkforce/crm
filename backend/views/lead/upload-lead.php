<?php
/** @var \yii\web\View $this */
/** @var string[] $rowErrors */

use yii\bootstrap5\Html;

$this->title = 'Upload Lead';
?>
<div class="ui-page lead-upload" style="max-width:860px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub">Import prospects into Lead Master from a spreadsheet.</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Choose file</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="js-busy-form">
                <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
                <label class="form-label" for="lead-upload-file">Excel / CSV file <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-2">
                    <input type="file" id="lead-upload-file" name="lead_file" class="form-control form-control-sm flex-grow-1" style="min-width:220px;width:auto"
                           accept=".xlsx,.xls,.csv" required aria-describedby="lead-upload-help">
                    <button type="submit" class="btn btn-primary btn-sm px-4" data-busy-text="Uploading…"><i class="fas fa-upload me-1"></i>Upload</button>
                </div>
                <div class="form-text" id="lead-upload-help">.xlsx, .xls or .csv, up to 5 MB. Imported rows are added as active prospects.</div>
            </form>
        </div>
    </div>

    <?php if ($rowErrors): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section text-danger">Skipped rows (<?= count($rowErrors) ?>)</div>
        <ul class="list-group list-group-flush small" style="max-height:320px;overflow:auto">
            <?php foreach ($rowErrors as $err): ?>
                <li class="list-group-item py-1"><?= Html::encode($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">
            <span>File format</span>
            <?= Html::a('<i class="fas fa-download me-1"></i>Download sample', ['download-sample'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
        <div class="card-body small">
            <p class="mb-2">The first row is a header and is skipped. Columns must be exactly <strong>A–H</strong>:</p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2 text-center">
                    <thead class="table-light"><tr><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>F</th><th>G</th><th>H</th></tr></thead>
                    <tbody><tr><td>S.No</td><td>Account Name *</td><td>First Name *</td><td>Last Name</td><td>Designation</td><td>Email *</td><td>Phone Number *</td><td>City</td></tr></tbody>
                </table>
            </div>
            <p class="mb-0 text-muted">Rows with a missing required value, an invalid email or phone number, or an email that already exists are skipped and listed here after the upload.</p>
        </div>
    </div>
</div>
