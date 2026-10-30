<?php
/** @var \yii\web\View $this */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Publish Attendance';
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
</style>

<div class="publish-attendance">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Publish Attendance</h5>
            <small class="text-muted">Generate monthly attendance verification records for all employees</small>
        </div>
        <?= Html::a('&larr; Back', ['attendance-verification'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:480px">
        <div class="card-body">
            <p class="text-muted small mb-3">
                Selecting a month will compute totals (late, absent, LOP, CL) from daily attendance
                records for every active employee and save them as verification entries.
                This action cannot be repeated for the same month once published.
            </p>

            <form method="post" action="<?= Url::to(['publish-attendance']) ?>">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>

                <div class="mb-3">
                    <label for="month_year" class="form-label fw-semibold">Select Month <span class="text-danger">*</span></label>
                    <?= \kartik\date\DatePicker::widget([
                        'name'          => 'month_year',
                        'id'            => 'month_year',
                        'value'         => date('Y-m', strtotime('-1 month')),
                        'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                        'pluginOptions' => [
                            'format'    => 'yyyy-mm',
                            'autoclose' => true,
                            'minViewMode' => 'months',
                        ],
                        'options' => ['class' => 'form-control form-control-sm', 'required' => true],
                    ]) ?>
                    <div class="form-text">Choose the month whose attendance you want to publish.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"
                            onclick="return confirm('Are you sure you want to publish attendance for the selected month?')">
                        Publish Attendance
                    </button>
                    <?= Html::a('Cancel', ['attendance-verification'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                </div>
            </form>
        </div>
    </div>
</div>
