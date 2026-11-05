<?php
/** @var yii\web\View $this */
/** @var yii\data\ArrayDataProvider $dataProvider  rows are arrays (with client) */
/** @var string $title */
/** @var string $type */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = $title;

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$types      = [1 => ['Returnable', 'warning'], 2 => ['Sale', 'accent'], 3 => ['Service', 'neutral']];
$filters    = ['alldc' => 'All', 'returnable' => 'Returnable', 'overdue' => 'Overdue', 'returned' => 'Returned', 'sale' => 'Sale', 'service' => 'Service', 'cancelled' => 'Cancelled'];
$can = [
    'view'   => UserAccess::checkAccess('quotation-dashboard/view-dc') || UserAccess::checkAccess('dc/view'),
    'pdf'    => UserAccess::checkAccess('dc/challan-pdf'),
    'return' => UserAccess::checkAccess('dc/save-return'),
    'cancel' => UserAccess::checkAccess('dc/cancel'),
];
$date = fn($d) => $d && substr((string)$d, 0, 10) !== '0000-00-00' ? date('d M Y', strtotime($d)) : null;
?>
<div class="ui-page dc-list-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($title) ?></h1>
            <div class="ui-sub"><?= number_format($total) ?> delivery challan<?= $total == 1 ? '' : 's' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Dashboard', ['/quotation-dashboard/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <nav class="ui-tabs" aria-label="DC filters">
        <?php foreach ($filters as $k => $label): ?>
            <?= Html::a($label, ['list-dc', 'type' => $k], ['class' => $k === $type ? 'active' : '', 'aria-current' => $k === $type ? 'page' : null]) ?>
        <?php endforeach; ?>
    </nav>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>DC</th><th>Client</th><th>Type</th><th>Return</th><th>Status</th><th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="7" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'truck', 'title' => 'No ' . strtolower($filters[$type] ?? '') . ' delivery challans', 'small' => true]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $m):
                    $t         = (int)$m['dc_type'];
                    $st        = (int)$m['status'];
                    $cancelled = $st === 2;
                    $returned  = $date($m['dc_returned_date'] ?? null);
                    $due       = $date($m['dc_return_date'] ?? null);
                    $overdue   = $t === 1 && !$returned && !$cancelled && $m['dc_return_date'] && strtotime($m['dc_return_date']) < strtotime('today');
                    $no        = $m['dc_no'] ?: ($st === 3 ? 'Draft' : '—');
                ?>
                    <tr<?= $cancelled ? ' class="is-cancelled"' : '' ?>>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title"><span class="mono"><?= $can['view'] ? Html::a('DC ' . Html::encode($no), ['view-dc', 'id' => $m['id']], ['class' => 'text-reset']) : 'DC ' . Html::encode($no) ?></span>
                            <div class="small text-muted fw-normal"><?= $date($m['dc_date']) ?: '—' ?></div></td>
                        <td data-label="Client"><span class="cell-stack"><?= Html::encode($m['client']['company_name'] ?? '—') ?><span class="d-block small text-muted"><?= Html::encode($m['client']['client_name'] ?? '') ?></span></span></td>
                        <td data-label="Type"><span class="ui-pill tone-<?= $types[$t][1] ?? 'neutral' ?> plain"><?= $types[$t][0] ?? '—' ?></span></td>
                        <td class="text-nowrap<?= $overdue ? ' text-danger fw-semibold' : '' ?>" data-label="Return"><?= $t !== 1 ? '<span class="text-muted">—</span>' : ($returned ? 'Returned ' . $returned : ($due ? 'Due ' . $due : 'Not set')) ?></td>
                        <td data-label="Status"><?= $cancelled ? '<span class="ui-pill tone-neutral" title="' . Html::encode($m['cancel_reason'] ?? '') . '">Cancelled</span>'
                            : ($st === 3 ? '<span class="ui-pill tone-warning plain">Draft</span>' : ($overdue ? '<span class="ui-pill tone-danger">Overdue</span>' : '<span class="ui-pill tone-success">Active</span>')) ?></td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for DC <?= Html::encode($no) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye fa-fw me-1"></i>View', ['view-dc', 'id' => $m['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['pdf'] && in_array($st, [1, 3], true)): ?><li><?= Html::a('<i class="fas fa-file-pdf fa-fw me-1"></i>Download PDF', ['/dc/challan-pdf', 'id' => $m['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['return'] && $st === 1 && $t === 1 && !$returned): ?>
                                        <li><a href="#" class="dropdown-item" data-dc-return data-id="<?= (int)$m['id'] ?>" data-no="<?= Html::encode($no) ?>" data-dc-date="<?= Html::encode($m['dc_date']) ?>"><i class="fas fa-undo fa-fw me-1"></i>Mark returned</a></li>
                                    <?php endif; ?>
                                    <?php if ($can['cancel'] && !$cancelled): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a href="#" class="dropdown-item text-danger" data-dc-cancel data-id="<?= (int)$m['id'] ?>" data-no="<?= Html::encode($no) ?>"><i class="fas fa-ban fa-fw me-1"></i>Cancel DC</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows), 'noun' => 'DCs']) ?>
    </div>

    <?= $this->render('@backend/views/dc/_action-modals') ?>
</div>
