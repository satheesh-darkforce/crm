<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var int $total */
/** @var int $expiring30 */
/** @var int $expired */
/** @var string|null $tab */
/** @var \backend\models\ClientModel[] $clients */

use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$this->title = 'Domains';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$totalCount = $dataProvider->getTotalCount();

$statusLabels = \backend\models\Domain::domainStatusLabels();
$statusBadge  = [1 => 'success', 2 => 'danger', 3 => 'warning', 4 => 'info', 5 => 'secondary'];

$today    = date('Y-m-d');
$in30days = date('Y-m-d', strtotime('+30 days'));
?>
<style>
.dropdown-menu{z-index:9999!important}
tr.row-expired td{background:#fff0f0!important}
tr.row-expiring td{background:#fffbe6!important}
</style>

<div class="ui-page domain-index">
    <div class="ui-head">
        <div>
            <h1>Domains</h1>
            <div class="ui-sub"><?= $totalCount ?> record<?= $totalCount != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('+ Add Domain', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::a('<i class="fas fa-folder-open"></i> Export', ['export-domains',
                'client_id' => Yii::$app->request->get('client_id'),
                'search'    => Yii::$app->request->get('search'),
            ], ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <!-- Stats tabs -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <?= Html::a('All Domains <span class="badge bg-secondary ms-1">'.$total.'</span>',
            ['index'], ['class' => 'btn btn-sm btn-outline-secondary'.($tab === null ? ' active' : ''), 'encode' => false]) ?>
        <?= Html::a('Expiring in 30 days <span class="badge bg-warning text-dark ms-1">'.$expiring30.'</span>',
            ['index', 'tab' => 'expiring'], ['class' => 'btn btn-sm btn-outline-warning'.($tab === 'expiring' ? ' active' : ''), 'encode' => false]) ?>
        <?= Html::a('Expired <span class="badge bg-danger ms-1">'.$expired.'</span>',
            ['index', 'tab' => 'expired'], ['class' => 'btn btn-sm btn-outline-danger'.($tab === 'expired' ? ' active' : ''), 'encode' => false]) ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <?php if ($tab): ?>
                    <input type="hidden" name="tab" value="<?= Html::encode($tab) ?>">
                <?php endif; ?>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Client</label>
                    <?= Select2::widget([
                        'name'          => 'client_id',
                        'value'         => Yii::$app->request->get('client_id'),
                        'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => 'All Clients', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Domain Name</label>
                    <input type="text" name="domain_name" class="form-control form-control-sm"
                           placeholder="e.g. example.com"
                           value="<?= Html::encode(Yii::$app->request->get('domain_name')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Service Provider</label>
                    <input type="text" name="service_provider" class="form-control form-control-sm"
                           placeholder="Provider..."
                           value="<?= Html::encode(Yii::$app->request->get('service_provider')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Hosting Server</label>
                    <input type="text" name="hosting_server" class="form-control form-control-sm"
                           placeholder="Server..."
                           value="<?= Html::encode(Yii::$app->request->get('hosting_server')) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Expiry From</label>
                    <?= DatePicker::widget([
                        'name'          => 'expiry_from',
                        'id'            => 'filter-expiry-from',
                        'value'         => Yii::$app->request->get('expiry_from'),
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'From'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                        'pluginEvents'  => [
                            'changeDate' => 'function() {
                                var fromDate = jQuery(this).kvDatepicker("getDate");
                                var $to = jQuery("#filter-expiry-to");
                                if (fromDate) {
                                    $to.kvDatepicker("setStartDate", fromDate);
                                    var toDate = $to.kvDatepicker("getDate");
                                    if (toDate && toDate < fromDate) {
                                        $to.val("").kvDatepicker("update");
                                    }
                                } else {
                                    $to.kvDatepicker("setStartDate", false);
                                }
                            }'
                        ],
                    ]) ?>
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Expiry To</label>
                    <?= DatePicker::widget([
                        'name'          => 'expiry_to',
                        'id'            => 'filter-expiry-to',
                        'value'         => Yii::$app->request->get('expiry_to'),
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'To'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <?php
                // On page reload: if "from" already has a value, enforce startDate on "to"
                if ($fromVal = Yii::$app->request->get('expiry_from')) {
                    $this->registerJs("
                        var fromDate = jQuery('#filter-expiry-from').kvDatepicker('getDate');
                        if (fromDate) {
                            jQuery('#filter-expiry-to').kvDatepicker('setStartDate', fromDate);
                        }
                    ");
                }
                ?>
                <div class="col-auto d-flex gap-1">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php
                    $hasFilter = Yii::$app->request->get('client_id') || Yii::$app->request->get('domain_name')
                        || Yii::$app->request->get('service_provider') || Yii::$app->request->get('hosting_server')
                        || Yii::$app->request->get('expiry_from') || Yii::$app->request->get('expiry_to');
                    ?>
                    <?php if ($hasFilter): ?>
                        <?= Html::a('Clear', ['index', 'tab' => $tab ?: null], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div style="overflow-x:auto;overflow-y:visible">
            <table class="table list-table mb-0" style="min-width:900px">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Client</th>
                        <th>Domain Name</th>
                        <th>Service Provider</th>
                        <th>Hosting Server</th>
                        <th>Expiry Date</th>
                        <th style="width:160px">Status</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No domain records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $d):
                        $expiry = $d->expiry_date;
                        $rowClass = '';
                        if ($expiry && $expiry < $today) {
                            $rowClass = 'row-expired';
                        } elseif ($expiry && $expiry <= $in30days) {
                            $rowClass = 'row-expiring';
                        }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold small"><?= Html::encode($d->client->client_name ?? '—') ?></td>
                        <td>
                            <a href="http://<?= Html::encode(preg_replace('#^https?://#', '', $d->domain_name)) ?>" target="_blank" rel="noreferrer noopener" class="text-decoration-none small">
                                <?= Html::encode($d->domain_name) ?>
                            </a>
                        </td>
                        <td class="text-muted small"><?= Html::encode($d->service_provider ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($d->hosting_server ?? '—') ?></td>
                        <td class="small">
                            <?php if ($expiry): ?>
                                <?php if ($expiry < $today): ?>
                                    <span class="text-danger fw-semibold"><?= date('d M Y', strtotime($expiry)) ?></span>
                                    <span class="badge bg-danger-subtle text-danger border ms-1" style="font-size:.7rem">Expired</span>
                                <?php elseif ($expiry <= $in30days): ?>
                                    <span class="text-warning fw-semibold"><?= date('d M Y', strtotime($expiry)) ?></span>
                                    <span class="badge bg-warning-subtle text-warning border ms-1" style="font-size:.7rem">Soon</span>
                                <?php else: ?>
                                    <?= date('d M Y', strtotime($expiry)) ?>
                                <?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <select class="form-select form-select-sm domain-status-select py-0"
                                    style="font-size:.78rem;width:100%"
                                    data-id="<?= $d->id ?>"
                                    onchange="changeDomainStatus(<?= $d->id ?>,this.value)">
                                <?php foreach ($statusLabels as $val => $lbl): ?>
                                    <option value="<?= $val ?>" <?= $d->domain_status == $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $d->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $d->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-sync-alt"></i> Renewal', ['renewal', 'id' => $d->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-clock"></i> Renewal History', ['renewal-history', 'id' => $d->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <?= Html::a('<i class="fas fa-trash-alt"></i> Delete', ['delete', 'id' => $d->id], [
                                            'class' => 'dropdown-item text-danger',
                                            'encode' => false,
                                            'data-confirm' => 'Are you sure you want to delete this domain?',
                                            'data-method' => 'post',
                                        ]) ?>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $totalCount) ?> of <?= $totalCount ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function changeDomainStatus(id, status) {
    fetch('<?= Url::to(['/domain/change-status']) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '<?= Yii::$app->request->csrfParam ?>=<?= Yii::$app->request->getCsrfToken() ?>&id=' + id + '&status=' + status
    }).then(r => r.json()).then(data => {
        if (!data.success) Swal.fire({icon:'error', title:'Error', text:'Failed to update status.'});
    });
}

// Force all dropdowns on this page to use fixed positioning via Popper,
// so they always render above/below the row and never get clipped by any
// overflow:auto/hidden ancestor (table-responsive, card, etc.)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
        new bootstrap.Dropdown(el, {
            popperConfig: function (defaultConfig) {
                return Object.assign({}, defaultConfig, { strategy: 'fixed' });
            }
        });
    });
});
</script>
