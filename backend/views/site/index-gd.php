<?php
/**
 * Green Divine sales dashboard (site/index while params['crm.simple'] is on).
 *
 * @var yii\web\View $this
 * @var string     $period
 * @var array|null $leads
 * @var array|null $quotes
 * @var array|null $invoices
 */

use backend\assets\PageUiAsset;
use backend\components\GdDashboard;
use backend\models\Lead;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

PageUiAsset::register($this);
$this->title = 'Dashboard';

$ca    = fn(string $r): bool => UserAccess::checkAccess($r);
$money = fn(float $v): string => GdDashboard::money($v);
$label = GdDashboard::PERIODS[$period][0];
$when  = $period === 'all' ? 'all time' : ($period === 'today' ? 'today' : 'last ' . strtolower($label));
$hello = (int) date('G') < 12 ? 'Good morning' : ((int) date('G') < 17 ? 'Good afternoon' : 'Good evening');
$name  = $user ? ($user->first_name ?? '') ?: $user->username : '';
?>
<div class="ui-page gd-dash">

    <div class="gd-dash-top">
        <div>
            <div class="gd-hello"><?= Html::encode($hello) ?><?= $name !== '' ? ', ' . Html::encode($name) : '' ?></div>
            <div class="gd-hello-sub">Here is how sales look for <?= Html::encode($when) ?>.</div>
        </div>
        <div class="gd-quick">
            <?php if ($ca('lead/create')): ?><?= Html::a('<i class="fas fa-plus"></i> Add Lead', ['/lead/create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?><?php endif; ?>
            <?php if ($ca('client-quotation/create')): ?><?= Html::a('<i class="fas fa-file-signature"></i> New Quotation', ['/client-quotation/index'], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?><?php endif; ?>
            <?php if ($ca('client/create')): ?><?= Html::a('<i class="fas fa-user-plus"></i> Add Client', ['/client/create'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?><?php endif; ?>
        </div>
    </div>

    <!-- Period chips (old dashboard) -->
    <nav class="gd-periods" aria-label="Period">
        <?php foreach (GdDashboard::PERIODS as $key => [$text]): ?>
            <?= Html::a(Html::encode($text), ['/site/index', 'period' => $key], ['class' => $key === $period ? 'active' : '', 'aria-current' => $key === $period ? 'true' : null]) ?>
        <?php endforeach; ?>
    </nav>

    <!-- Tiles -->
    <div class="gd-stats">
        <?php if ($leads !== null): ?>
        <a class="gd-stat" href="<?= Url::to(['/lead/index']) ?>">
            <span class="gd-stat-ic ic-violet"><i class="fas fa-bolt"></i></span>
            <span class="gd-stat-body"><span class="gd-stat-val"><?= $leads['new'] ?></span><span class="gd-stat-lbl">New leads</span></span>
        </a>
        <a class="gd-stat" href="<?= Url::to(['/lead/today']) ?>">
            <span class="gd-stat-ic ic-amber"><i class="fas fa-phone-volume"></i></span>
            <span class="gd-stat-body"><span class="gd-stat-val"><?= $leads['dueToday'] ?><?php if ($leads['overdue']): ?> <small class="text-danger">+<?= $leads['overdue'] ?> overdue</small><?php endif; ?></span><span class="gd-stat-lbl">Follow-ups today</span></span>
        </a>
        <a class="gd-stat" href="<?= Url::to(['/lead/index', 'priority' => Lead::PRIORITY_HIGH]) ?>">
            <span class="gd-stat-ic ic-red"><i class="fas fa-fire"></i></span>
            <span class="gd-stat-body"><span class="gd-stat-val"><?= $leads['hot'] ?> <small>/ <?= $leads['open'] ?> open</small></span><span class="gd-stat-lbl">Hot leads</span></span>
        </a>
        <a class="gd-stat" href="<?= Url::to(['/lead/converted']) ?>">
            <span class="gd-stat-ic ic-green"><i class="fas fa-handshake"></i></span>
            <span class="gd-stat-body"><span class="gd-stat-val"><?= $leads['converted'] ?></span><span class="gd-stat-lbl">Leads converted</span></span>
        </a>
        <?php endif; ?>
        <?php if ($quotes !== null): ?>
        <a class="gd-stat" href="<?= Url::to(['/client-quotation/index']) ?>">
            <span class="gd-stat-ic ic-blue"><i class="fas fa-file-invoice"></i></span>
            <span class="gd-stat-body"><span class="gd-stat-val"><?= $money($quotes['openValue']) ?></span><span class="gd-stat-lbl"><?= $quotes['open'] ?> open quotation<?= $quotes['open'] === 1 ? '' : 's' ?></span></span>
        </a>
        <a class="gd-stat" href="<?= Url::to(['/client-quotation/index']) ?>">
            <span class="gd-stat-ic ic-teal"><i class="fas fa-circle-check"></i></span>
            <span class="gd-stat-body"><span class="gd-stat-val"><?= $money($quotes['wonValue']) ?></span><span class="gd-stat-lbl"><?= $quotes['won'] ?> quotation<?= $quotes['won'] === 1 ? '' : 's' ?> won</span></span>
        </a>
        <?php endif; ?>
        <?php if ($invoices !== null): ?>
        <a class="gd-stat" href="<?= Url::to(['/invoice/index', 'from_date' => GdDashboard::since($period)]) ?>">
            <span class="gd-stat-ic ic-purple"><i class="fas fa-receipt"></i></span>
            <span class="gd-stat-body"><span class="gd-stat-val"><?= $money($invoices['billed']) ?></span><span class="gd-stat-lbl">Invoiced · <?= $invoices['count'] ?> bill<?= $invoices['count'] === 1 ? '' : 's' ?></span></span>
        </a>
        <a class="gd-stat" href="<?= Url::to(['/invoice/index']) ?>">
            <span class="gd-stat-ic ic-orange"><i class="fas fa-hourglass-half"></i></span>
            <span class="gd-stat-body"><span class="gd-stat-val"><?= $money($invoices['pending']) ?></span><span class="gd-stat-lbl">To collect (all time)</span></span>
        </a>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <?php if ($leads !== null): ?>
        <!-- Follow-ups due -->
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-calendar-check text-primary me-2"></i>Follow-ups due</span>
                    <?= Html::a('Open today\'s board <i class="fas fa-arrow-right ms-1"></i>', ['/lead/today'], ['class' => 'small', 'encode' => false]) ?>
                </div>
                <div class="card-body p-0">
                    <?php if (!$leads['agenda']): ?>
                        <div class="gd-empty"><i class="fas fa-mug-hot"></i>Nothing due — all caught up.</div>
                    <?php else: ?>
                    <ul class="gd-list">
                        <?php foreach ($leads['agenda'] as $l): /** @var Lead $l */
                            $due  = strtotime((string) $l->meeting_reported_date);
                            $late = $due < strtotime('today'); ?>
                        <li>
                            <span class="gd-av"><?= Html::encode(mb_strtoupper(mb_substr((string) $l->client_name, 0, 1))) ?></span>
                            <span class="gd-li-main">
                                <?= Html::a(Html::encode($l->client_name ?: 'Lead #' . $l->id), ['/lead/view', 'id' => $l->id], ['class' => 'gd-li-title']) ?>
                                <span class="gd-li-sub"><?= Html::encode($l->subject ?: $l->getSourceLabel()) ?></span>
                            </span>
                            <span class="gd-li-side">
                                <span class="ui-pill tone-<?= $late ? 'danger' : 'warning' ?>"><?= $late ? 'Overdue · ' . date('d M', $due) : 'Today · ' . date('h:i a', $due) ?></span>
                                <?php if ($l->contact_no): ?><a class="gd-call" href="tel:<?= Html::encode(preg_replace('/[^\d+]/', '', $l->contact_no)) ?>" title="Call <?= Html::encode($l->contact_no) ?>"><i class="fas fa-phone"></i></a><?php endif; ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Leads by source -->
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-chart-bar text-primary me-2"></i>Where leads came from</span>
                    <?php if ($ca('lead/insights')): ?><?= Html::a('Insights <i class="fas fa-arrow-right ms-1"></i>', ['/lead/insights'], ['class' => 'small', 'encode' => false]) ?><?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$leads['sources']): ?>
                        <div class="gd-empty"><i class="fas fa-seedling"></i>No new leads <?= Html::encode($when) ?>.</div>
                    <?php else:
                        $max = max(array_column($leads['sources'], 'n')) ?: 1; ?>
                        <?php foreach ($leads['sources'] as $s): ?>
                        <div class="gd-bar">
                            <div class="gd-bar-head"><span><?= Html::encode($s['label']) ?></span><b><?= $s['n'] ?></b></div>
                            <div class="gd-bar-track"><span style="width: <?= round($s['n'] / $max * 100) ?>%"></span></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($quotes !== null): ?>
        <!-- Recent quotations -->
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-signature text-primary me-2"></i>Recent quotations</span>
                    <?= Html::a('All quotations <i class="fas fa-arrow-right ms-1"></i>', ['/client-quotation/index'], ['class' => 'small', 'encode' => false]) ?>
                </div>
                <div class="card-body p-0">
                    <?php if (!$quotes['recent']): ?>
                        <div class="gd-empty"><i class="fas fa-file"></i>No quotations yet.</div>
                    <?php else: ?>
                    <ul class="gd-list">
                        <?php foreach ($quotes['recent'] as $q): [$pill, $tone] = $q->getQuoteStatusPill(); ?>
                        <li>
                            <span class="gd-av gd-av-blue"><i class="fas fa-file-lines"></i></span>
                            <span class="gd-li-main">
                                <?= Html::a(Html::encode($q->project_name ?: $q->quote_no), ['/client-quotation/view', 'id' => $q->id], ['class' => 'gd-li-title']) ?>
                                <span class="gd-li-sub"><?= Html::encode(trim(($q->client->client_name ?? '') . ' · ' . $q->quote_no, ' ·')) ?></span>
                            </span>
                            <span class="gd-li-side">
                                <b class="gd-amt"><?= '₹' . number_format((float) ($q->grand_total ?: $q->estimation_cost), 0) ?></b>
                                <span class="ui-pill tone-<?= Html::encode($tone) ?>"><?= Html::encode($pill) ?></span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($leads !== null): ?>
        <!-- Newest leads -->
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-user-clock text-primary me-2"></i>Newest leads</span>
                    <?= Html::a('All leads <i class="fas fa-arrow-right ms-1"></i>', ['/lead/index'], ['class' => 'small', 'encode' => false]) ?>
                </div>
                <div class="card-body p-0">
                    <?php if (!$leads['recent']): ?>
                        <div class="gd-empty"><i class="fas fa-seedling"></i>No leads yet.</div>
                    <?php else: ?>
                    <ul class="gd-list">
                        <?php foreach ($leads['recent'] as $l): $temp = Lead::priorityOptions()[(int) $l->priority] ?? ''; ?>
                        <li>
                            <span class="gd-av gd-av-green"><?= Html::encode(mb_strtoupper(mb_substr((string) $l->client_name, 0, 1))) ?></span>
                            <span class="gd-li-main">
                                <?= Html::a(Html::encode($l->client_name ?: 'Lead #' . $l->id), ['/lead/view', 'id' => $l->id], ['class' => 'gd-li-title']) ?>
                                <span class="gd-li-sub"><?= Html::encode($l->getSourceLabel() ?: '—') ?><?= $l->created_date ? ' · ' . date('d M', strtotime($l->created_date)) : '' ?></span>
                            </span>
                            <?php if ($temp !== ''): ?>
                            <span class="gd-li-side"><span class="ui-pill tone-<?= ['Hot' => 'danger', 'Warm' => 'warning'][$temp] ?? 'neutral' ?>"><?= Html::encode($temp) ?></span></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
