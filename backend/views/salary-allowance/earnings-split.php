<?php
/** @var \yii\web\View $this */
/** @var \backend\models\SalaryAllowance[] $rows  keyed by code */

use backend\models\SalaryAllowance;
use yii\bootstrap5\Html;

$this->title = 'Earnings Split';

$fields = [
    SalaryAllowance::CODE_BASIC  => 'Basic',
    SalaryAllowance::CODE_DA     => 'DA',
    SalaryAllowance::CODE_HRA    => 'HRA',
    SalaryAllowance::CODE_OTHERS => 'Others',
];
$val = static fn(string $c): string =>
    isset($rows[$c]) && $rows[$c]->default_value !== null
        ? rtrim(rtrim(number_format((float)$rows[$c]->default_value, 2, '.', ''), '0'), '.')
        : '';
$req = '<span class="text-danger">*</span>';
?>
<div class="earnings-split">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-semibold mb-0">Earnings Split</h5>
            <small class="text-muted">Percentages of <strong>Pay After LOP</strong>. Basic + DA + HRA + Others must total <strong>100%</strong>.</small>
        </div>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['deductions'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm" style="max-width:560px">
        <div class="card-body">
            <?= Html::beginForm(['earnings-split'], 'post', ['id' => 'earnings-split-form']) ?>
            <div class="row g-3" id="split-inputs">
                <?php foreach ($fields as $code => $label): ?>
                <div class="col-6">
                    <label class="form-label small fw-semibold mb-1"><?= Html::encode($label) ?> (%) <?= $req ?></label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="split[<?= $code ?>]" class="form-control form-control-sm split-input"
                               value="<?= Html::encode($val($code)) ?>" min="0" max="100" step="0.001" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex align-items-center justify-content-between mt-3 p-2 rounded" id="total-box" style="background:#f8f9ff">
                <span class="fw-semibold small text-uppercase text-muted" style="letter-spacing:.04em">Total</span>
                <span class="fw-bold" id="split-total">0%</span>
            </div>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton('<i class="fas fa-save"></i> Save Split', ['class' => 'btn btn-primary btn-sm px-4', 'id' => 'split-save', 'encode' => false]) ?>
                <?= Html::a('Cancel', ['deductions'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
<?php
$this->registerJs(<<<JS
function refreshTotal() {
    var total = 0;
    jQuery('.split-input').each(function () {
        var v = parseFloat(this.value);
        if (!isNaN(v)) { total += v; }
    });
    total = Math.round(total * 1000) / 1000;
    var ok = (total === 100);
    jQuery('#split-total').text(total + '%').css('color', ok ? '#198754' : '#dc3545');
    jQuery('#total-box').css('background', ok ? '#e8f5ee' : '#fdeaea');
    jQuery('#split-save').prop('disabled', !ok);
}
jQuery('.split-input').on('input', refreshTotal);
refreshTotal();

// Final guard on submit (in case the button is re-enabled via devtools).
jQuery('#earnings-split-form').on('submit', function (e) {
    var total = 0;
    jQuery('.split-input').each(function () { var v = parseFloat(this.value); if (!isNaN(v)) { total += v; } });
    if (Math.round(total * 1000) / 1000 !== 100) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Invalid split', text: 'Basic + DA + HRA + Others must total 100%. Current total: ' + (Math.round(total * 1000) / 1000) + '%.' });
    }
});
JS, \yii\web\View::POS_READY);
