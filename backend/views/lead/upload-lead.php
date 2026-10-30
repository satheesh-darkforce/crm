<?php
/** @var \yii\web\View $this */

use yii\bootstrap5\Html;

$this->title = 'Upload Leads (CSV)';
?>
<div class="lead-upload" style="max-width:640px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold">Upload Leads via CSV</h5>
        <?= Html::a('Back to Prospects', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-2">Upload CSV File</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">CSV File <span class="text-danger">*</span></label>
                    <input type="file" name="lead_file" class="form-control form-control-sm" accept=".csv" required>
                    <div class="form-text">Only CSV files are accepted. Max 5 MB.</div>
                </div>
                <div class="d-flex gap-2">
                    <?= Html::submitButton('Upload &amp; Import', ['class' => 'btn btn-primary btn-sm']) ?>
                    <?= Html::a('Cancel', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Format guide -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold py-2">CSV Format</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                    <thead class="table-light">
                        <tr>
                            <th>Col A</th><th>Col B</th><th>Col C</th><th>Col D</th>
                            <th>Col E</th><th>Col F</th><th>Col G</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-warning">
                            <td><em>account_name</em></td>
                            <td><em>first_name</em></td>
                            <td><em>last_name</em></td>
                            <td><em>designation</em></td>
                            <td><em>email_id</em></td>
                            <td><em>phone_number</em></td>
                            <td><em>city</em></td>
                        </tr>
                        <tr>
                            <td>Acme Corp</td>
                            <td>John</td>
                            <td>Doe</td>
                            <td>Manager</td>
                            <td>john@acme.com</td>
                            <td>9876543210</td>
                            <td>Mumbai</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-2 text-muted" style="font-size:.78rem">
                Row 1 must be the header row (column names). Data starts from row 2.
                Required fields: Account Name, First Name, Email, Phone.
            </div>
        </div>
    </div>
</div>
