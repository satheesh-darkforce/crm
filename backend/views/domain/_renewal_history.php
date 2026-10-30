<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Domain $domain */
/** @var \backend\models\DomainRenewalHistory[] $history */

use yii\bootstrap5\Html;

$this->title = 'Renewal History: ' . $domain->domain_name;
?>
<div class="domain-renewal-history">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">Renewal History — <?= Html::encode($domain->domain_name) ?></h5>
        <div class="d-flex gap-2">
            <?= Html::a('+ Add Renewal', ['renewal', 'id' => $domain->id], ['class' => 'btn btn-outline-success btn-sm']) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['view', 'id' => $domain->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Amount Received</th>
                        <th>Payment Date</th>
                        <th>New Expiry Date</th>
                        <th>Notes</th>
                        <th>Recorded On</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No renewal history for this domain.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $i => $h): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-semibold">₹<?= number_format((float)$h->amount_received, 2) ?></td>
                        <td><?= $h->amount_received_date ? date('d M Y', strtotime($h->amount_received_date)) : '—' ?></td>
                        <td><?= $h->expiry_date ? date('d M Y', strtotime($h->expiry_date)) : '—' ?></td>
                        <td class="text-muted small"><?= Html::encode($h->notes ?? '—') ?></td>
                        <td class="text-muted small"><?= $h->created_at ? date('d M Y', strtotime($h->created_at)) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
