<?php
/**
 * "Smart" panel on the lead page: score, next best actions, possible duplicates
 * and the value estimate. Data comes from LeadInsight (see LeadController::renderLead()).
 *
 * @var yii\web\View $this
 * @var backend\models\Lead $lead
 * @var array $insight  score, band, actions, duplicates, estimate, idle
 */

use backend\models\Lead;
use yii\bootstrap5\Html;

$score    = (int)$insight['score'];
[$band, $tone] = $insight['band'];
$isClosed = in_array((int)$lead->lead_status, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true);
$money    = fn($v) => '₹' . Yii::$app->formatter->asDecimal((float)$v, 0);
$est      = $insight['estimate'];
$idle     = $insight['idle'];
?>
<div class="card border-0 shadow-sm mb-3 lead-insight">
    <div class="card-header ui-section d-flex justify-content-between align-items-center">
        <span><i class="fas fa-lightbulb me-1"></i>Smart view</span>
        <?php if ($idle !== null): ?><span class="small text-muted fw-normal">Last activity <?= $idle === 0 ? 'today' : $idle . ' day' . ($idle === 1 ? '' : 's') . ' ago' ?></span><?php endif; ?>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-start">
            <div class="col-md-auto text-center">
                <div class="li-score tone-<?= $tone ?>" style="--p:<?= $score ?>" role="img" aria-label="Lead score <?= $score ?> of 100 (<?= $band ?>)">
                    <span class="num"><?= $score ?></span>
                </div>
                <div class="small text-muted mt-1"><?= $isClosed ? $lead->getLeadStatusLabel() : $band . ' score' ?></div>
            </div>

            <div class="col">
                <?php if ($insight['actions']): ?>
                    <div class="li-label">Next best actions</div>
                    <ul class="li-actions">
                        <?php foreach ($insight['actions'] as $a): ?>
                            <li class="tone-<?= Html::encode($a['tone']) ?>"><i class="fas fa-<?= Html::encode($a['icon']) ?> fa-fw"></i><span><?= Html::encode($a['text']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (!$isClosed): ?>
                    <div class="small text-muted"><i class="fas fa-check-circle text-success me-1"></i>Everything is on track for this lead.</div>
                <?php endif; ?>

                <?php if ($insight['duplicates']): ?>
                    <div class="li-label mt-3">Possible duplicates</div>
                    <ul class="li-dups">
                        <?php foreach ($insight['duplicates'] as $d): ?>
                            <li>
                                <i class="fas <?= $d['type'] === 'client' ? 'fa-building' : 'fa-bullseye' ?> fa-fw text-muted"></i>
                                <?= $d['url'] ? Html::a(Html::encode($d['name']), $d['url']) : Html::encode($d['name']) ?>
                                <span class="text-muted small">— same <?= Html::encode($d['match']) ?> · <?= Html::encode($d['detail']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($est): ?>
            <div class="col-lg-5">
                <div class="li-label">Value estimate <span class="text-muted fw-normal">· <?= Html::encode($lead->getAreaLabel()) ?> at catalogue prices</span></div>
                <table class="table table-sm li-est mb-1">
                    <?php foreach ($est['lines'] as $l): ?>
                        <tr>
                            <th scope="row"><?= Html::encode($l['category']) ?></th>
                            <?php if (empty($l['products'])): ?>
                                <td class="text-muted small" colspan="2"><?= Html::encode($l['note']) ?></td>
                            <?php else: ?>
                                <td class="text-end num"><?= $l['low'] === $l['high'] ? $money($l['typical']) : $money($l['low']) . ' – ' . $money($l['high']) ?></td>
                                <td class="text-end small text-muted nowrap"><?= $money($l['rate_min']) ?><?= $l['rate_min'] != $l['rate_max'] ? '–' . $money($l['rate_max']) : '' ?> / <?= Html::encode($est['unit']) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <div class="small text-muted">Before GST and installation. Each product is priced for the full area.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
