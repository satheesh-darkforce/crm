<?php
/** @var yii\web\View $this */
/** @var \backend\models\User $user */
/** @var int $domainExpiryCount */
/** @var int $amcDueCount */
/** @var \yii\data\ArrayDataProvider $ongoingProvider */
/** @var \yii\data\ArrayDataProvider $curMonthProvider */
/** @var int $curMonthTotal */
/** @var string $curMonthType */
/** @var \yii\data\ArrayDataProvider $prevMonthProvider */
/** @var int $prevMonthTotal */
/** @var string $prevProjectType */
/** @var \yii\data\ArrayDataProvider $prevTaskProvider */
/** @var string|null $fromDate */
/** @var string|null $toDate */
/** @var string|null $taskFromDate */
/** @var string|null $taskToDate */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;

$this->title = 'Dashboard';

// Seconds to HH:MM
$secToHR = fn(int $s): string => sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60));

$projectTypes = ['' => 'Select Project Type', 1 => 'Productive', 2 => 'Non Productive'];
?>

<style>
/* Two fixes needed for wide GridViews to stay inside their card on the dashboard:
   1) min-width:0 on the col so the flex item respects its grid width instead
      of growing to fit the table.
   2) Override layouts/main.php's `.table-responsive { overflow:visible !important }`
      (set there to keep action-menu dropdowns from being clipped on list pages —
      not relevant on the dashboard) so the table actually scrolls horizontally
      inside the card. */
.dashboard .row > [class*="col-"] { min-width: 0; }
.dashboard .table-responsive { overflow-x: auto !important; overflow-y: visible !important; }
</style>

<div class="dashboard">

    <!-- ── Stat Widgets ─────────────────────────────────────────────────── -->
    <?php
    $showDomainCard = UserAccess::checkAccess('domain/index');
    $showAmcDueCard = UserAccess::checkAccess('amc/renew-list');
    ?>
    <?php if ($showDomainCard || $showAmcDueCard): ?>
    <div class="row mb-4">
        <?php if ($showDomainCard): ?>
        <div class="col-lg-3 col-md-6">
            <div class="card border-info">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-1 text-info"><i class="fas fa-globe"></i></div>
                    <div>
                        <div class="text-muted small">Domain Expiry in 30 days</div>
                        <div class="fw-bold fs-3"><?= $domainExpiryCount ?></div>
                    </div>
                </div>
                <a href="<?= \yii\helpers\Url::to(['/domain/index', 'q' => 'expiry_domains']) ?>"
                   class="card-footer text-decoration-none small d-flex justify-content-between">
                    <span>View Details</span><span>&rarr;</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($showAmcDueCard): ?>
        <div class="col-lg-3 col-md-6">
            <div class="card border-warning">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-1 text-warning"><i class="fas fa-tools"></i></div>
                    <div>
                        <div class="text-muted small">Current Month AMC Due</div>
                        <div class="fw-bold fs-3"><?= $amcDueCount ?></div>
                    </div>
                </div>
                <a href="<?= \yii\helpers\Url::to(['/amc/renew-list']) ?>"
                   class="card-footer text-decoration-none small d-flex justify-content-between">
                    <span>View Details</span><span>&rarr;</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Ongoing Tasks ─────────────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>On Going Tasks (<?= date('d-m-Y') ?>)</strong>
            <?= Html::a('View all &raquo;', ['/task/index'], ['class' => 'small text-decoration-none']) ?>
        </div>
        <div class="card-body p-0 table-responsive">
            <?php Pjax::begin(['id' => 'pjax-ongoing', 'timeout' => 5000, 'enablePushState' => false, 'clientOptions' => ['scrollTo' => false]]) ?>
            <?= GridView::widget([
                'dataProvider' => $ongoingProvider,
                'tableOptions' => ['class' => 'table table-hover table-sm mb-0'],
                'layout'       => '{items}{pager}',
                'emptyText'    => '<p class="text-center text-muted py-3 mb-0">No tasks currently running.</p>',
                'columns'      => [
                    ['class' => 'yii\grid\SerialColumn'],
                    [
                        'label' => 'Employee',
                        'value' => fn($r) => $r['assigned_to_name'] ?? '-',
                    ],
                    [
                        'label' => 'Project',
                        'value' => fn($r) => $r['project_title'] ?? '-',
                    ],
                    [
                        'label' => 'Task',
                        'value' => fn($r) => $r['task'],
                    ],
                    [
                        'label' => 'Start Time',
                        'value' => fn($r) => $r['start_time']
                            ? date('d-m-Y h:i a', strtotime($r['start_time']))
                            : '-',
                    ],
                    [
                        'label' => 'Expected Time',
                        'value' => fn($r) => $r['expected_time'] ? $secToHR((int)$r['expected_time']) : '-',
                    ],
                    [
                        'label' => 'Assigned By',
                        'value' => fn($r) => $r['assigned_by_name'] ?? '-',
                    ],
                    [
                        'label'  => 'Action',
                        'format' => 'raw',
                        'value'  => function($r) {
                            $base = Yii::$app->request->baseUrl;
                            $view = Html::a('<img src="' . $base . '/images/view.png" alt="View" style="width:16px;height:16px">', ['/task/view', 'id' => $r['id']], ['title' => 'View Task Details']);
                            $logs = Html::a('<img src="' . $base . '/images/alarm.png" alt="Task Logs" style="width:16px;height:16px">', ['/task/task-logs', 'id' => $r['id']], ['title' => 'Task Logs', 'target' => '_blank']);
                            return $view . ' &nbsp; ' . $logs;
                        },
                    ],
                ],
            ]) ?>
            <?php Pjax::end() ?>
        </div>
    </div>

    <!-- ── Current Month Projects ────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong>Current Month Projects</strong>
            <form method="get" action="<?= \yii\helpers\Url::to(['/site/index']) ?>" class="d-flex gap-2 align-items-center mb-0">
                <select name="cur_project_type" class="form-select form-select-sm" style="width:auto">
                    <?php foreach ($projectTypes as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $curMonthType == (string)$k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-info btn-sm"><i class="bi bi-search"></i> Search</button>
            </form>
            <?= Html::a('View all &raquo;', ['/work-report/monthly-by-project'], ['class' => 'small text-decoration-none']) ?>
        </div>
        <div class="card-body p-0 table-responsive">
            <?php Pjax::begin(['id' => 'pjax-cur-month', 'timeout' => 5000, 'enablePushState' => false, 'clientOptions' => ['scrollTo' => false]]) ?>
            <?= GridView::widget([
                'dataProvider' => $curMonthProvider,
                'tableOptions' => ['class' => 'table table-hover table-sm mb-0'],
                'layout'       => '{items}{pager}',
                'emptyText'    => '<p class="text-center text-muted py-3 mb-0">No results found.</p>',
                'columns'      => [
                    ['class' => 'yii\grid\SerialColumn'],
                    ['label' => 'Project',              'value' => fn($r) => $r['project_title'] ?? '--'],
                    ['label' => 'Client',               'value' => fn($r) => $r['client_name'] ?? '--'],
                    ['label' => 'Total Estimation Hours','value' => fn($r) => $r['total_estimation_hours'] ? $secToHR((int)$r['total_estimation_hours']) : '--'],
                    ['label' => 'Total Estimation Cost', 'value' => fn($r) => $r['total_estimation_cost'] ?: '--'],
                    ['label' => 'Project Type',          'value' => fn($r) => $projectTypes[$r['project_type']] ?? '--'],
                    [
                        'label'          => 'Total Time',
                        'format'         => 'raw',
                        'value'          => fn($r) => $secToHR((int)$r['total_seconds']),
                        'contentOptions' => ['class' => 'text-end'],
                        'headerOptions'  => ['class' => 'text-end'],
                    ],
                ],
            ]) ?>
            <?php Pjax::end() ?>
        </div>
        <div class="card-footer text-end fw-bold">
            Total: <?= $secToHR($curMonthTotal) ?>
        </div>
    </div>

    <!-- ── Previous Month Projects + Previous Month Tasks (side by side) ── -->
    <div class="row g-3 mb-4">

        <!-- Previous Month Projects -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Previous Month Projects</strong>
                    <?= Html::a('View all &raquo;', ['/work-report/monthly-by-project', 'prepro' => 1], ['class' => 'small text-decoration-none']) ?>
                </div>
                <div class="card-body border-bottom pb-2">
                    <form method="get" action="<?= \yii\helpers\Url::to(['/site/index']) ?>" class="row g-2">
                        <div class="col-md-3">
                            <?= \kartik\date\DatePicker::widget([
                                'name'          => 'from_date',
                                'value'         => $fromDate,
                                'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                                'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                                'options'       => ['id' => 'filter-from-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'From Date', 'required' => true],
                            ]) ?>
                        </div>
                        <div class="col-md-3">
                            <?= \kartik\date\DatePicker::widget([
                                'name'          => 'to_date',
                                'value'         => $toDate,
                                'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                                'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                                'options'       => ['id' => 'filter-to-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'To Date', 'required' => true],
                            ]) ?>
                            <div id="to-date-error" class="text-danger" style="font-size:.75rem;display:none">To date cannot be before From date.</div>
                        </div>
                        <div class="col-md-3">
                            <select name="prev_project_type" class="form-select form-select-sm">
                                <?php foreach ($projectTypes as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $prevProjectType == (string)$k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" id="filter-btn" class="btn btn-info btn-sm"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </form>
<?php $this->registerJs(<<<'JS'
(function(){var $f=$('#filter-from-date'),$t=$('#filter-to-date'),$e=$('#to-date-error'),$b=$('#filter-btn');function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}$f.on('changeDate change',function(){var fv=$f.val();$t.kvDatepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').kvDatepicker('update');}v();});$t.on('changeDate change',v);$f.closest('form').on('submit',function(){return v();});}());
JS); ?>
                </div>
                <div class="card-body p-0 table-responsive">
                    <?php Pjax::begin(['id' => 'pjax-prev-month', 'timeout' => 5000, 'enablePushState' => false, 'clientOptions' => ['scrollTo' => false]]) ?>
                    <?= GridView::widget([
                        'dataProvider' => $prevMonthProvider,
                        'tableOptions' => ['class' => 'table table-hover table-sm mb-0'],
                        'layout'       => '{items}{pager}',
                        'emptyText'    => '<p class="text-center text-muted py-3 mb-0">No results found.</p>',
                        'columns'      => [
                            ['class' => 'yii\grid\SerialColumn'],
                            ['label' => 'Project',              'value' => fn($r) => $r['project_title']],
                            ['label' => 'Client',               'value' => fn($r) => $r['client_name']],
                            ['label' => 'Total Estimation Hours','value' => fn($r) => $r['total_estimation_hours'] ? $secToHR((int)$r['total_estimation_hours']) : '--'],
                            ['label' => 'Total Estimation Cost', 'value' => fn($r) => $r['total_estimation_cost'] ?: '--'],
                            ['label' => 'Project Type',          'value' => fn($r) => $projectTypes[$r['project_type']] ?? '--'],
                            [
                                'label'          => 'Total Time',
                                'format'         => 'raw',
                                'value'          => fn($r) => $secToHR((int)$r['total_seconds']),
                                'contentOptions' => ['class' => 'text-end'],
                                'headerOptions'  => ['class' => 'text-end'],
                            ],
                        ],
                    ]) ?>
                    <?php Pjax::end() ?>
                </div>
                <div class="card-footer text-end fw-bold">
                    Total: <?= $secToHR($prevMonthTotal) ?>
                </div>
            </div>
        </div>

        <!-- Previous Month Tasks -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Previous Month Tasks</strong>
                    <?= Html::a('View all &raquo;', ['/task/index'], ['class' => 'small text-decoration-none']) ?>
                </div>
                <div class="card-body border-bottom pb-2">
                    <form method="get" action="<?= \yii\helpers\Url::to(['/site/index']) ?>" class="row g-2">
                        <div class="col-md-4">
                            <?= \kartik\date\DatePicker::widget([
                                'name'          => 'task_from_date',
                                'value'         => $taskFromDate,
                                'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                                'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                                'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'From Date', 'required' => true],
                            ]) ?>
                        </div>
                        <div class="col-md-4">
                            <?= \kartik\date\DatePicker::widget([
                                'name'          => 'task_to_date',
                                'value'         => $taskToDate,
                                'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                                'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                                'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'To Date', 'required' => true],
                            ]) ?>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-info btn-sm"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0 table-responsive">
                    <?php Pjax::begin(['id' => 'pjax-prev-task', 'timeout' => 5000, 'enablePushState' => false, 'clientOptions' => ['scrollTo' => false]]) ?>
                    <?= GridView::widget([
                        'dataProvider' => $prevTaskProvider,
                        'tableOptions' => ['class' => 'table table-hover table-sm mb-0'],
                        'layout'       => '{items}{pager}',
                        'emptyText'    => '<p class="text-center text-muted py-3 mb-0">No results found.</p>',
                        'columns'      => [
                            ['class' => 'yii\grid\SerialColumn'],
                            ['label' => 'Task',         'value' => fn($r) => $r['task']],
                            ['label' => 'Created By',   'value' => fn($r) => $r['created_by_name'] ?? '-'],
                            ['label' => 'Created Date', 'value' => fn($r) => $r['created_date'] ? date('d-M-Y', strtotime($r['created_date'])) : '-'],
                            ['label' => 'Total Time',   'value' => fn($r) => $r['total_time'] ? gmdate('H:i:s', (int)$r['total_time']) : '-'],
                        ],
                    ]) ?>
                    <?php Pjax::end() ?>
                </div>
            </div>
        </div>

    </div>

</div>

<?php $this->registerJs(<<<'JS'
// Preserve scroll position across Pjax pagination on the dashboard.
// scrollTo:false alone is not enough — replacing the table changes layout
// height which the browser may re-anchor against, so we snapshot and restore.
(function () {
    var pjaxIds = ['pjax-ongoing', 'pjax-cur-month', 'pjax-prev-month', 'pjax-prev-task'];
    var savedY = null;
    pjaxIds.forEach(function (id) {
        var sel = '#' + id;
        $(document).on('pjax:send', sel, function () {
            savedY = window.scrollY || window.pageYOffset;
        });
        $(document).on('pjax:end', sel, function () {
            if (savedY !== null) {
                window.scrollTo(0, savedY);
                savedY = null;
            }
        });
    });
})();
JS); ?>
