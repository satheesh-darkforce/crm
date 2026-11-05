<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Ex-Employees';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>

<div class="ui-page ex-employees-index">
    <div class="ui-head">
        <div>
            <h1>Ex-Employees</h1>
            <div class="ui-sub"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-inbox"></i> Export', ['export-ex-employees'], ['class' => 'btn btn-danger btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Active Developers', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <?php
            $req = Yii::$app->request;
            $hasFilter = $req->get('name') || $req->get('designation') || $req->get('email') || $req->get('mobile');
            ?>
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="name" value="<?= Html::encode($req->get('name')) ?>"
                           class="form-control form-control-sm" placeholder="Name…">
                </div>
                <div class="col-md-2">
                    <input type="text" name="designation" value="<?= Html::encode($req->get('designation')) ?>"
                           class="form-control form-control-sm" placeholder="Designation…">
                </div>
                <div class="col-md-2">
                    <input type="text" name="email" value="<?= Html::encode($req->get('email')) ?>"
                           class="form-control form-control-sm" placeholder="Email…">
                </div>
                <div class="col-md-2">
                    <input type="text" name="mobile" value="<?= Html::encode($req->get('mobile')) ?>"
                           class="form-control form-control-sm" placeholder="Phone…">
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Search</button>
                    <?php if ($hasFilter): ?>
                        <?= Html::a('Clear', ['ex-employees'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Developer</th>
                        <th>Designation</th>
                        <th>Emp Code</th>
                        <th>Mobile</th>
                        <th>Joined</th>
                        <th>Relieved</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No ex-employees found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $dev): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $dev->id]) ?>"
                               class="fw-semibold text-decoration-none text-secondary">
                                <?= Html::encode($dev->developer_name) ?>
                            </a>
                            <?php if ($dev->email_id): ?>
                                <div class="text-muted" style="font-size:.75rem"><?= Html::encode($dev->email_id) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= Html::encode($dev->designation ?? '—') ?></td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace">
                                <?= Html::encode($dev->emp_code ?: '—') ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= Html::encode($dev->mobile ?? '—') ?></td>
                        <td class="text-muted small">
                            <?= $dev->doj ? date('d M Y', strtotime($dev->doj)) : '—' ?>
                        </td>
                        <td class="text-muted small">
                            <?= $dev->dor ? date('d M Y', strtotime($dev->dor)) : '—' ?>
                        </td>
                        <td class="text-end">
                            <?= Html::a('<i class="fas fa-eye"></i>', ['view', 'id' => $dev->id], [
                                'class' => 'btn btn-outline-secondary act-btn', 'encode' => false,
                                'title' => 'View',
                            ]) ?>
                            <?= Html::a('<i class="fas fa-pen"></i>', ['update', 'id' => $dev->id], [
                                'class' => 'btn btn-outline-warning act-btn', 'encode' => false,
                                'title' => 'Edit',
                            ]) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
