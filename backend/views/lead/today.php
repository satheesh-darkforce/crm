<?php
/** @var yii\web\View $this */
/** @var array<string,\backend\models\Lead[]> $sections */
/** @var string $who  user id filter ('' = everyone) */
/** @var bool $isManager */
/** @var array<int,string> $users */

use backend\components\LeadInsight;
use backend\models\Lead;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = "Today's Follow-ups";

$meta = [
    'overdue'   => ['Overdue follow-ups', 'danger', 'Promised calls that were missed — oldest first.', 'meeting_reported_date'],
    'today'     => ['Due today', 'warning', 'Follow-ups scheduled for today.', 'meeting_reported_date'],
    'visits'    => ['Site visits this week', 'accent', 'Booked site visits for the next 7 days.', 'site_visit_date'],
    'upcoming'  => ['Next 7 days', 'neutral', 'Follow-ups coming up this week.', 'meeting_reported_date'],
    'unplanned' => ['Warm / hot leads with no follow-up', 'warning', 'Good leads that nobody has planned to call.', null],
    'stale'     => ['Stale (over 60 days)', 'neutral', 'Long-forgotten follow-ups — reassign, re-engage or drop them.', 'meeting_reported_date'],
];
$when = function (?string $v, bool $withDay = true): string {
    $ts = LeadInsight::ts($v);
    if ($ts === null) {
        return '—';
    }
    $time = date('H:i', $ts) !== '00:00' ? date(' h:i A', $ts) : '';
    return ($withDay ? date(date('Y', $ts) === date('Y') ? 'D, d M' : 'd M Y', $ts) : '') . $time;
};
?>
<div class="ui-page lead-today">
    <div class="ui-head">
        <div>
            <h1>Today's follow-ups</h1>
            <div class="ui-sub"><?= date('l, d M Y') ?><?= $who !== '' && isset($users[(int)$who]) ? ' · ' . Html::encode($users[(int)$who]) : ($isManager ? ' · everyone' : '') ?></div>
        </div>
        <div class="ui-actions">
            <?php if ($isManager): ?>
                <form method="get" action="<?= Url::to(['today']) ?>" class="d-flex gap-2 align-items-center" style="min-width:240px">
                    <label class="visually-hidden" for="today-user">Employee</label>
                    <?= Select2::widget([
                        'name' => 'user', 'value' => $who, 'data' => $users,
                        'options' => ['placeholder' => 'Everyone', 'id' => 'today-user', 'onchange' => 'this.form.submit()'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '220px'],
                        'size' => Select2::SMALL,
                    ]) ?>
                </form>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-list me-1"></i>All leads', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="ui-tiles">
        <?php foreach ($meta as $key => [$label, $tone]): $n = count($sections[$key]); ?>
            <a class="ui-tile text-decoration-none<?= $key === 'overdue' && $n ? ' is-accent' : '' ?>" href="#sec-<?= $key ?>">
                <div class="t-label"><?= Html::encode($label) ?></div>
                <div class="t-value num<?= $key === 'overdue' && $n ? ' text-danger' : '' ?>"><?= $n ?><?= $n >= 100 ? '+' : '' ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php foreach ($meta as $key => [$label, $tone, $hint, $dateCol]): $rows = $sections[$key]; ?>
        <div class="card border-0 shadow-sm mb-3" id="sec-<?= $key ?>">
            <div class="card-header ui-section d-flex justify-content-between align-items-center">
                <span><?= Html::encode($label) ?> <span class="ui-pill tone-<?= $tone ?> ms-1"><?= count($rows) ?></span></span>
                <span class="small text-muted fw-normal text-none d-none d-md-inline"><?= Html::encode($hint) ?></span>
            </div>
            <?php if (!$rows): ?>
                <div class="card-body small text-muted"><i class="fas fa-check-circle text-success me-1"></i>Nothing here.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table list-table ui-table stack mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Client</th>
                        <th><?= $dateCol === 'site_visit_date' ? 'Visit' : ($dateCol ? 'Follow-up' : 'Last activity') ?></th>
                        <th>Interested in</th>
                        <th>Owner</th>
                        <th class="text-end">Score</th>
                        <th class="text-end" style="width:120px"><span class="visually-hidden">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $lead): /** @var Lead $lead */
                        $score = LeadInsight::score($lead);
                        [$band, $bandTone] = LeadInsight::band($score);
                        $dial = $lead->getDialNumber();
                        $idle = LeadInsight::idleDays($lead);
                        ?>
                        <tr>
                            <td class="cell-title wrap">
                                <?= Html::a(Html::encode($lead->client_name ?: 'Lead #' . $lead->id), ['view', 'id' => $lead->id], ['class' => 'fw-semibold text-decoration-none']) ?>
                                <?php if ((int)$lead->priority === Lead::PRIORITY_HIGH): ?> <span class="ui-pill tone-danger">Hot</span>
                                <?php elseif ((int)$lead->priority === Lead::PRIORITY_MEDIUM): ?> <span class="ui-pill tone-warning">Warm</span><?php endif; ?>
                                <div class="sub"><?= Html::encode(implode(' · ', array_filter([$lead->contact_no, $lead->country]))) ?></div>
                            </td>
                            <td class="small nowrap" data-label="<?= $dateCol === 'site_visit_date' ? 'Visit' : ($dateCol ? 'Follow-up' : 'Last activity') ?>">
                                <?php if ($dateCol): ?>
                                    <?= $when($lead->$dateCol) ?>
                                    <?php if ($dateCol === 'site_visit_date' && $lead->siteVisitBy): ?><div class="sub"><?= Html::encode(Lead::userLabel($lead->siteVisitBy)) ?></div><?php endif; ?>
                                    <?php if ($key === 'overdue' || $key === 'stale'): $d = (int)floor((strtotime('today') - strtotime(date('Y-m-d', strtotime($lead->meeting_reported_date)))) / 86400); ?>
                                        <div class="sub text-danger"><?= $d ?> day<?= $d === 1 ? '' : 's' ?> late</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= $idle === null ? '—' : ($idle === 0 ? 'Today' : $idle . ' day' . ($idle === 1 ? '' : 's') . ' ago') ?>
                                <?php endif; ?>
                            </td>
                            <td class="small wrap" data-label="Interested in"><?= Html::encode(implode(', ', array_map(fn($c) => $c->name, $lead->interests)) ?: '—') ?></td>
                            <td class="small" data-label="Owner"><?= Html::encode(Lead::userLabel($lead->assignedTo)) ?></td>
                            <td class="text-end" data-label="Score"><span class="ui-pill tone-<?= $bandTone ?>" title="<?= $band ?> score"><?= $score ?></span></td>
                            <td class="cell-actions text-end nowrap">
                                <?php if ($dial): ?>
                                    <a class="btn btn-outline-secondary act-btn" href="tel:+<?= $dial ?>" title="Call" aria-label="Call <?= Html::encode($lead->client_name) ?>"><i class="fas fa-phone"></i></a>
                                    <a class="btn btn-outline-success act-btn" href="https://wa.me/<?= $dial ?>" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp <?= Html::encode($lead->client_name) ?>"><i class="fab fa-whatsapp"></i></a>
                                <?php endif; ?>
                                <?= Html::a('<i class="fas fa-arrow-right"></i>', ['view', 'id' => $lead->id], ['class' => 'btn btn-outline-primary act-btn', 'encode' => false, 'title' => 'Open lead', 'aria-label' => 'Open lead']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
