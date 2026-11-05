<?php
/** @var \yii\web\View $this */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'Bulk Import Domains';
?>

<div class="ui-page domain-import">
    <div class="ui-head">
        <div>
            <h1>Bulk Import Domains</h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back to List', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title fw-semibold mb-3">Import from Excel/CSV</h6>
                    <p class="text-muted small mb-3">
                        Upload an Excel or CSV file with domain data. The file must have the following columns in order:
                    </p>
                    <ol class="small text-muted mb-3">
                        <li><code>Client ID</code> - Numeric client ID</li>
                        <li><code>Domain Name</code> - e.g., example.com</li>
                        <li><code>Price</code> - Renewal cost (numeric)</li>
                        <li><code>Purchase Date</code> - YYYY-MM-DD format</li>
                        <li><code>Service Provider</code> - e.g., GoDaddy</li>
                        <li><code>Username</code> - Domain control panel username</li>
                        <li><code>Password</code> - Domain control panel password</li>
                        <li><code>Hosting Server</code> - Hosting provider</li>
                        <li><code>Description</code> - Notes about the domain</li>
                        <li><code>Expiry Date</code> - YYYY-MM-DD format</li>
                    </ol>

                    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

                    <div class="mb-3">
                        <label for="import-file" class="form-label">Select File</label>
                        <input type="file" id="import-file" name="import_file" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required>
                        <small class="form-text text-muted">Supported: Excel (.xlsx, .xls) or CSV files</small>
                    </div>

                    <div class="d-flex gap-2">
                        <?= Html::submitButton('Import Domains', ['class' => 'btn btn-primary btn-sm']) ?>
                        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>

            <div class="alert alert-info small" role="alert">
                <strong>ℹ️ Important:</strong>
                <ul class="mb-0 ps-3">
                    <li>Domains must have valid expiry dates</li>
                    <li>Client IDs must exist in the system</li>
                    <li>All domains are created with status "Live"</li>
                    <li>If any row fails validation, the entire import is rolled back</li>
                    <li>Maximum file size: 10MB</li>
                </ul>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <h6 class="card-title fw-semibold mb-3"><i class="fas fa-clipboard"></i> Example Template</h6>
                    <p class="small text-muted mb-2">Download template or see example:</p>
                    <div class="bg-white p-2 rounded small border" style="font-family: monospace; overflow-x: auto;">
                        <div>1,example.com,500,2024-01-15,GoDaddy,user123,pass123,AWS,Main domain,2025-01-15</div>
                        <div>2,test.org,300,2023-06-20,Namecheap,admin,secret,Bluehost,Test site,2024-06-20</div>
                    </div>

                    <hr class="my-3">

                    <h6 class="fw-semibold mb-2"><i class="fas fa-lightbulb"></i> Tips</h6>
                    <ul class="small mb-0">
                        <li>Use Excel for easier editing</li>
                        <li>Test with 1-2 rows first</li>
                        <li>Check client IDs before importing</li>
                        <li>Review error messages carefully</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.domain-import {
    max-width: 1200px;
    margin: 0 auto;
}
</style>
