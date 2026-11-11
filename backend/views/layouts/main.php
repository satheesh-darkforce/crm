<?php

/** @var \yii\web\View $this */
/** @var string $content */

use backend\assets\AppAsset;
use common\components\UserAccess;
use common\widgets\Alert;
use yii\bootstrap5\Html;

AppAsset::register($this);

$user     = Yii::$app->user->identity;
$appName  = Yii::$app->params['app.name'] ?? 'CRM';
$username = $user ? $user->username : '';

// Role flags — used throughout sidebar for conditional rendering
$isAdmin    = $user && $user->isAdmin();
$isSubAdmin = $user && $user->isSubAdmin();
$isDev      = $user && $user->isDeveloper();
$isClient   = $user && $user->isClient();

// Pending counts for badge indicators.
// Matches Yii1 condition exactly (developer_header.php):
//   admin (usertype=1) → always count
//   developer (usertype=2) → only if has explicit `leaverequests/allleaverequests`
//   anyone else → no badge
$pendingLeaves = 0;
$openLeads     = 0;
$canSeeAllLeaves = $isAdmin || $isSubAdmin;
if (!$canSeeAllLeaves && $user && $user->isDeveloper()) {
    $devModel = $user->developer;
    if ($devModel && !empty($devModel->access_permissions)) {
        $devPerms = array_map('trim', explode(',', $devModel->access_permissions));
        // Accept either the Yii1 permission slug or the Yii2 route form.
        $canSeeAllLeaves = in_array('leaverequests/allleaverequests', $devPerms, true)
                       || in_array('leave/index', $devPerms, true);
    }
}
if ($canSeeAllLeaves) {
    $pendingLeaves = (int) Yii::$app->db
        ->createCommand('SELECT COUNT(*) FROM leave_requests WHERE status=1 AND request_status=0')
        ->queryScalar();
}
// Unread lead follow-ups assigned to me by someone else (Yii1 developer header badge).
if ($user && UserAccess::checkAccess('lead/assigned')) {
    $openLeads = (int) Yii::$app->db->createCommand(
        'SELECT COUNT(*) FROM lead_followers WHERE status=1 AND read_status=1 AND assigned_to=:u AND created_by<>:c',
        [':u' => $user->user_id, ':c' => $user->user_id]
    )->queryScalar();
}

// Shorthand
$ca = fn(string $route): bool => UserAccess::checkAccess($route);

// Yii1 parity (developer_header.php): "Add Work Report" link is grayed out
// while the developer has a task timer running — they must stop the task
// before logging a work report. Mirrors Utility::getUserTaskStartedCount().
$hasRunningTask = false;
if ($user && $isDev) {
    $hasRunningTask = \backend\models\TaskLogs::find()
        ->where(['status' => 1, 'user_id' => $user->user_id, 'end_time' => null])
        ->exists();
}

// Sidebar active-group detection — keeps the relevant submenu open when a
// child page is being viewed (e.g. on /vendor/view?id=… the Vendors group
// should be expanded, not collapsed).
$currentCtrl   = Yii::$app->controller->id ?? '';
$currentAction = Yii::$app->controller->action->id ?? '';
$navGroupMap = [
    // Action-specific overrides (try first; controller-only entries below).
    // The "All Projects" link lives under Work Reports in the sidebar, but its
    // route is project/all-project — without this entry the Projects group
    // would steal focus and collapse Work Reports.
    'project/all-project'   => 'nav-workreports',

    'client'                => 'nav-clients',
    'project'               => 'nav-projects',
    'amc'                   => 'nav-amc',
    'developer'             => 'nav-employees',
    'employee'              => 'nav-employees',
    'user'                  => 'nav-employees',
    'work-report'           => 'nav-workreports',
    'additional-works'      => 'nav-workreports',
    'task'                  => 'nav-tasks',
    'lead'                  => 'nav-leads',
    'lead-followers'        => 'nav-leads',
    'proposal'              => 'nav-leads',
    'domain'                => 'nav-domains',
    'invoice'               => 'nav-invoices',
    'inward-invoice'        => 'nav-invoices',
    'bank-detail'           => 'nav-invoices',
    'vendor'                => 'nav-vendors',
    'attendance'            => 'nav-attendance',
    'leave'                 => 'nav-attendance',
    'permission'            => 'nav-attendance',
    'holiday'               => 'nav-attendance',
    'rfid-card'             => 'nav-attendance',
    'payslip'               => 'nav-payroll',
    'payslip-upload'        => 'nav-payroll',
    'salary-allowance'      => 'nav-payroll',
    'asset'                 => 'nav-assets',
    'asset-maintenance'     => 'nav-assets',
    'asset-handover'        => 'nav-assets',
    'assets-brand'          => 'nav-assets',
    'assets-category'       => 'nav-assets',
    'assets-subcategory'    => 'nav-assets',
    'assets-loc'            => 'nav-assets',
    'iot-product'           => 'nav-iot',
    'iot-consumption'       => 'nav-iot',
    'client-quotation'      => 'nav-quotation',
    'dc'                    => 'nav-quotation',
    'delivery-product'      => 'nav-quotation',
    'product'               => 'nav-products',
    'quotation'             => 'nav-quotation',
    'po'                    => 'nav-po',
    'po-dashboard'          => 'nav-po',
    'pm-task'               => 'nav-pm',
    'pm-quotation'          => 'nav-pm',
    'project-management'    => 'nav-pm',
];
$activeNav = $navGroupMap["$currentCtrl/$currentAction"] ?? ($navGroupMap[$currentCtrl] ?? '');
$navOpen   = fn(string $id): string => $id === $activeNav ? 'show' : '';
$navAria   = fn(string $id): string => $id === $activeNav ? 'true' : 'false';
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?> — <?= Html::encode($appName) ?></title>
    <link rel="icon" type="image/png" href="<?= Yii::$app->request->baseUrl ?>/images/favicon.png">
    <link rel="apple-touch-icon" href="<?= Yii::$app->request->baseUrl ?>/images/favicon.png">
    <link rel="shortcut icon" href="<?= Yii::$app->request->baseUrl ?>/images/favicon.png">
    <?php $this->head() ?>
    <link rel="stylesheet" href="<?= Yii::$app->request->baseUrl ?>/css/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= Yii::$app->request->baseUrl ?>/css/font-awesome/css/font-awesome.min.css">
    <!-- Font Awesome 6 (for modules ported from delivery: fas/fa-solid classes, fa-magnifying-glass, etc.) -->
    <link rel="stylesheet" href="<?= Yii::$app->request->baseUrl ?>/css/all.min.css">
    <script src="<?= Yii::$app->request->baseUrl ?>/js/sweetalert2/sweetalert2.all.min.js"></script>
    <script>
    // ── Polyfill window.alert → SweetAlert2 ─────────────────────────────
    // Any legacy vanilla `alert('…')` call anywhere in the app is routed
    // through SweetAlert2 for a consistent themed popup. Runs synchronously
    // right after sweetalert2 loads so it is in place before any page
    // script (including jQuery) executes.
    (function () {
        if (typeof window.Swal === 'undefined') { return; }
        var nativeAlert = window.alert;
        window.alert = function (message) {
            try {
                var text = (message === undefined || message === null) ? '' : String(message);
                window.Swal.fire({
                    text: text,
                    icon: 'info',
                    confirmButtonText: 'OK',
                    heightAuto: false
                });
            } catch (e) {
                nativeAlert.call(window, message);
            }
        };
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap">
    <?php $gdTheme = Yii::getAlias('@webroot/css/gd-theme.css'); ?>
    <!-- Green Divine theme: every colour / input / picker style lives here (reusable, loaded last) -->
    <link rel="stylesheet" href="<?= Yii::$app->request->baseUrl ?>/css/gd-theme.css?v=<?= is_file($gdTheme) ? filemtime($gdTheme) : 1 ?>">
</head>
<body>
<?php $this->beginBody() ?>

<!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
<nav id="sidebar">
    <div class="brand">
        <a href="<?= \yii\helpers\Url::to(['/site/index']) ?>" class="d-flex align-items-center gap-2 text-decoration-none">
            <span class="brand-mark"><i class="fas fa-leaf"></i></span>
            <span class="brand-name"><?= Html::encode($appName) ?></span>
        </a>
        <button type="button" class="sidebar-close" id="sidebar-close" aria-label="Close menu">&times;</button>
    </div>
    <div class="nav-body">

<?php if (!Yii::$app->user->isGuest): ?>

    <?php
    // Sidebar groups are captured into $navBlocks and printed in the order of
    // params['sidebar.groups']; links whose route is in params['sidebar.hiddenRoutes']
    // are left out. Nothing is deleted — see HIDDEN_MODULES.md.
    $navBlocks       = [];
    $navHiddenRoutes = Yii::$app->params['sidebar.hiddenRoutes'] ?? [];
    $nav = fn(string $route): bool => !in_array($route, $navHiddenRoutes, true) && $ca($route);
    ?>
    <?php ob_start(); ?>
    <!-- 1. Dashboard -->
    <?php if ($nav('site/index')): ?>
    <a class="nav-link" href="<?= \yii\helpers\Url::to(['/site/index']) ?>">
        <span class="sidebar-icon"><i class="fas fa-bars"></i></span> Dashboard
    </a>
    <?php endif; ?>
    <?php $navBlocks['dashboard'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 2. Clients -->
    <?php if ($nav('client/index') || $nav('client/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-clients" aria-expanded="<?= $navAria('nav-clients') ?>">
        <span class="sidebar-icon"><i class="fas fa-building"></i></span> Clients
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-clients') ?>" id="nav-clients">
        <?php if ($nav('client/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/client/index']) ?>">Clients</a>
        <?php endif; ?>
        <?php if ($nav('client/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/client/create']) ?>">Add New Client</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['clients'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 2b. Products & services catalogue -->
    <?php if ($nav('product/index') || $nav('product/create') || $nav('product/categories')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-products" aria-expanded="<?= $navAria('nav-products') ?>">
        <span class="sidebar-icon"><i class="fas fa-boxes"></i></span> Products
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-products') ?>" id="nav-products">
        <?php if ($nav('product/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/product/index']) ?>">Products &amp; Services</a>
        <?php endif; ?>
        <?php if ($nav('product/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/product/create']) ?>">Add Product</a>
        <?php endif; ?>
        <?php if ($nav('product/categories')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/product/categories']) ?>">Categories</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['products'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 3. Projects -->
    <?php if ($nav('project/index') || $nav('project/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-projects" aria-expanded="<?= $navAria('nav-projects') ?>">
        <span class="sidebar-icon"><i class="fas fa-folder"></i></span> Projects
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-projects') ?>" id="nav-projects">
        <?php if ($nav('project/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/project/index']) ?>">Projects</a>
        <?php endif; ?>
        <?php if ($nav('project/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/project/create']) ?>">Add New Project</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['projects'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 3b. AMC -->
    <?php if ($nav('amc/projects') || $nav('amc/active') || $nav('amc/renew-list') || $nav('amc/ended') || $nav('amc/completed-projects')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-amc" aria-expanded="<?= $navAria('nav-amc') ?>">
        <span class="sidebar-icon"><i class="fas fa-tools"></i></span> AMC
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-amc') ?>" id="nav-amc">
        <?php if ($nav('amc/projects')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/amc/projects']) ?>">AMC Projects</a>
        <?php endif; ?>
        <?php if ($nav('amc/active')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/amc/active']) ?>">Active AMC</a>
        <?php endif; ?>
        <?php if ($nav('amc/renew-list')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/amc/renew-list']) ?>">Current Month Renew</a>
        <?php endif; ?>
        <?php if ($nav('amc/ended')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/amc/ended']) ?>">Ended AMC</a>
        <?php endif; ?>
        <?php /* Completed Projects menu hidden per request — the amc/completed-projects
                action + view still exist; re-add this link to expose it again. */ ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['amc'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 4. Employees -->
    <?php if ($nav('developer/index') || $nav('developer/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-employees" aria-expanded="<?= $navAria('nav-employees') ?>">
        <span class="sidebar-icon"><i class="fas fa-user"></i></span> Employees
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-employees') ?>" id="nav-employees">
        <?php if ($nav('developer/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/developer/index']) ?>">Employees</a>
        <?php endif; ?>
        <?php if ($nav('developer/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/developer/create']) ?>">Add New Employee</a>
        <?php endif; ?>
        <?php if ($nav('developer/ex-employees')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/developer/ex-employees']) ?>">Ex-Employees</a>
        <?php endif; ?>
        <?php if ($nav('developer/create-payslip')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/developer/create-payslip']) ?>">Create Payslip</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['employees'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 5. Work Reports -->
    <?php if ($nav('work-report/index') || $nav('work-report/create') || $nav('work-report/my-work-reports')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-workreports" aria-expanded="<?= $navAria('nav-workreports') ?>">
        <span class="sidebar-icon"><i class="fas fa-chart-bar"></i></span> Work Reports
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-workreports') ?>" id="nav-workreports">
        <?php // "Add Work Report" is shown only when the user has the work-report/create
               // permission. Admin / sub-admin headers do not show this link at all (Yii1
               // parity with maestro_erp/protected/components/admin_header.php).
               // Yii1 parity: grayed out while a task timer is running. ?>
        <?php if (!$isAdmin && !$isSubAdmin && $nav('work-report/create')): ?>
        <a class="nav-link<?= $hasRunningTask ? ' wr-disabled' : '' ?>"
           href="<?= \yii\helpers\Url::to(['/work-report/create']) ?>"
           <?php if ($hasRunningTask): ?>title="Stop your running task before adding a work report" aria-disabled="true"<?php endif; ?>>Add Work Report</a>
        <?php endif; ?>
        <?php if ($nav('work-report/my-work-reports')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/my-work-reports']) ?>">My Work Reports</a>
        <?php endif; ?>
        <?php if ($nav('project/all-project')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/project/all-project']) ?>">All Projects</a>
        <?php endif; ?>
        <?php if ($nav('work-report/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/index']) ?>">Daily Work Report</a>
        <?php endif; ?>
        <?php if ($nav('work-report/yesterday')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/yesterday']) ?>">Employee yesterday's work report</a>
        <?php endif; ?>
        <?php if ($nav('work-report/developer-current-month')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/developer-current-month']) ?>">Employee monthly work report</a>
        <?php endif; ?>
        <?php if ($nav('work-report/cumulative-employee')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/cumulative-employee']) ?>">Employee based cumulative report</a>
        <?php endif; ?>
        <?php if ($nav('work-report/monthly-by-project')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/monthly-by-project']) ?>">Project based monthly work report</a>
        <?php endif; ?>
        <?php if ($nav('work-report/cumulative-client')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/cumulative-client']) ?>">Client based cumulative report</a>
        <?php endif; ?>
        <?php if ($nav('work-report/monthly-by-employee')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/monthly-by-employee']) ?>">Monthly report</a>
        <?php endif; ?>
        <?php if ($nav('work-report/overall')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/overall']) ?>">Overall report</a>
        <?php endif; ?>
        <?php if ($nav('work-report/performance')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/performance']) ?>">Performance report</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['workreports'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 6. Tasks -->
    <?php if ($nav('task/index') || $nav('task/create') || $nav('task/my-new-task-list') || $nav('task/completed-task-list') || $nav('task/assigned-tasks')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-tasks" aria-expanded="<?= $navAria('nav-tasks') ?>">
        <span class="sidebar-icon"><i class="fas fa-check"></i></span> Tasks
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-tasks') ?>" id="nav-tasks">
        <?php if ($nav('task/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/index']) ?>">My Tasks</a>
        <?php endif; ?>
        <?php if ($nav('task/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/create']) ?>">Add Task</a>
        <?php endif; ?>
        <?php if ($nav('task/my-new-task-list')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/my-new-task-list']) ?>">My New Tasks</a>
        <?php endif; ?>
        <?php if ($nav('task/completed-task-list')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/completed-task-list']) ?>">Completed Task List</a>
        <?php endif; ?>
        <?php if ($nav('task/assigned-tasks')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/assigned-tasks']) ?>">Assigned Task List</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['tasks'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 7. Leads -->
    <?php if ($nav('lead/index') || $nav('lead/my-leads') || $nav('lead/create') || $nav('lead/assigned') || $nav('lead/converted') || $nav('lead/dropped') || $nav('lead/lead-master') || $nav('lead/upload-lead')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-leads" aria-expanded="<?= $navAria('nav-leads') ?>">
        <span class="sidebar-icon"><i class="fas fa-bullseye"></i></span> Leads
        <?= $openLeads > 0 ? '<span class="badge bg-danger ms-1">' . $openLeads . '</span>' : '' ?>
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-leads') ?>" id="nav-leads">
        <?php if ($nav('lead/today')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/today']) ?>">Today's Follow-ups</a>
        <?php endif; ?>
        <?php if ($nav('lead/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/index']) ?>">Manage Leads</a>
        <?php endif; ?>
        <?php if ($nav('lead/insights')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/insights']) ?>">Lead Insights</a>
        <?php endif; ?>
        <?php if ($nav('lead/my-leads')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/my-leads']) ?>">My Leads</a>
        <?php endif; ?>
        <?php if ($nav('lead/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/create']) ?>">Add New Lead</a>
        <?php endif; ?>
        <?php if ($nav('lead/assigned')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/assigned']) ?>">
            Assigned Leads <?= $openLeads > 0 ? '<span class="badge bg-danger">' . $openLeads . '</span>' : '' ?>
        </a>
        <?php endif; ?>
        <?php if ($nav('lead/converted')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/converted']) ?>">Converted Leads</a>
        <?php endif; ?>
        <?php if ($nav('lead/dropped')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/dropped']) ?>">Dropped Leads</a>
        <?php endif; ?>
        <?php /* Proposals stays hidden until that module is rolled out.
        <?php if ($nav('proposal/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/proposal/index']) ?>">Proposals</a>
        <?php endif; ?>
        */ ?>
        <?php if ($nav('lead/lead-master')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/lead-master']) ?>">Lead Master</a>
        <?php endif; ?>
        <?php if ($nav('lead/upload-lead')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/upload-lead']) ?>">Upload Lead</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['leads'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 8. Domains -->
    <?php if ($nav('domain/index') || $nav('domain/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-domains" aria-expanded="<?= $navAria('nav-domains') ?>">
        <span class="sidebar-icon"><i class="fas fa-globe"></i></span> Domains
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-domains') ?>" id="nav-domains">
        <?php if ($nav('domain/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/domain/index']) ?>">Domains</a>
        <?php endif; ?>
        <?php if ($nav('domain/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/domain/create']) ?>">Add Domain</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['domains'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 9. Invoices (matches Yii1 admin_header / developer_header submenu order) -->
    <?php if ($nav('invoice/index') || $nav('invoice/create') || $nav('invoice/proforma') || $nav('inward-invoice/index') || $nav('inward-invoice/create') || $nav('invoice/monthly-report') || $nav('bank-detail/index') || $nav('bank-detail/create') || $nav('settings/invoice') || $nav('invoice/client-invoice')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-invoices" aria-expanded="<?= $navAria('nav-invoices') ?>">
        <span class="sidebar-icon"><i class="fas fa-file-alt"></i></span> <?= $isClient ? 'My Invoices' : 'Invoices' ?>
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-invoices') ?>" id="nav-invoices">
        <?php if ($isClient): ?>
            <?php if ($nav('invoice/client-invoice')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/invoice/client-invoice']) ?>">My Invoices</a>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($nav('invoice/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/invoice/index']) ?>">Invoices</a>
            <?php endif; ?>
            <?php if ($nav('invoice/create')): ?>
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#addInvoiceModal"
               onclick="openInvoiceModal('Add Invoice', '1')">Add Invoice</a>
            <?php endif; ?>
            <?php if ($nav('invoice/proforma')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/invoice/proforma']) ?>">Proforma Invoices</a>
            <?php endif; ?>
            <?php if ($nav('invoice/create')): ?>
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#addInvoiceModal"
               onclick="openInvoiceModal('Add Proforma Invoice', '2')">Add Proforma</a>
            <?php endif; ?>
            <?php if ($nav('inward-invoice/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/inward-invoice/index']) ?>">Inward Invoices</a>
            <?php endif; ?>
            <?php if ($nav('inward-invoice/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/inward-invoice/create']) ?>">Add Inward Invoices</a>
            <?php endif; ?>
            <?php if ($nav('invoice/monthly-report')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/invoice/monthly-report']) ?>">Monthly Report</a>
            <?php endif; ?>
            <?php if ($nav('bank-detail/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/bank-detail/index']) ?>">Payment Bank Details</a>
            <?php endif; ?>
            <?php if ($nav('bank-detail/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/bank-detail/create']) ?>">Add Bank Detail</a>
            <?php endif; ?>
            <?php /* TODO: Uncomment when Quotation module is ready for use
            <?php if ($nav('quotation/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/quotation/index']) ?>">Quotations</a>
            <?php endif; ?>
            */ ?>
            <?php if ($nav('settings/invoice')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/settings/invoice']) ?>">Invoice Settings</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['invoices'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 10. Vendors -->
    <?php if ($nav('vendor/index') || $nav('vendor/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-vendors" aria-expanded="<?= $navAria('nav-vendors') ?>">
        <span class="sidebar-icon"><i class="fas fa-box"></i></span> Vendors
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-vendors') ?>" id="nav-vendors">
        <?php if ($nav('vendor/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/vendor/index']) ?>">Vendors</a>
        <?php endif; ?>
        <?php if ($nav('vendor/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/vendor/create']) ?>">Add Vendor</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['vendors'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 11. Attendance -->
    <?php
    // Yii1 parity: sub-admin only sees the toggle when at least one child item
    // is permitted (admin always sees it). Without this, sub-admins without any
    // attendance grants saw an empty Attendance group.
    $showAttendanceMenu = $isAdmin
        || $nav('attendance/index') || $nav('attendance/my-attendance') || $nav('leave/my-requests')
        || $nav('leave/create') || $nav('leave/index') || $nav('attendance/monthly-attendance')
        || $nav('permission/index') || $nav('permission/create')
        || $nav('attendance/leave-settings') || $nav('holiday/index') || $nav('rfid-card/index');
    ?>
    <?php if ($showAttendanceMenu): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-attendance" aria-expanded="<?= $navAria('nav-attendance') ?>">
        <span class="sidebar-icon"><i class="fas fa-user-clock"></i></span> <?= ($isAdmin || $isSubAdmin) ? 'HR' : 'My Attendance' ?>
        <?= $pendingLeaves > 0 ? '<span class="badge bg-danger">' . $pendingLeaves . '</span>' : '' ?>
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-attendance') ?>" id="nav-attendance">
        <?php if ($isAdmin || $isSubAdmin): ?>
            <!-- Admin / Sub-admin: Yii1 admin_header layout with access checks for sub-admin -->
            <?php if ($isAdmin || $nav('attendance/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/index']) ?>">Attendance</a>
            <?php endif; ?>
            <?php if ($nav('attendance/live')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/live']) ?>">Live Attendance</a>
            <?php endif; ?>
            <?php if ($isAdmin || $nav('leave/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/create']) ?>">Add Leave Request</a>
            <?php endif; ?>
            <?php if ($isAdmin || $nav('leave/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/index']) ?>">
                Leave Requests <?= $pendingLeaves > 0 ? '<span class="badge bg-danger">' . $pendingLeaves . '</span>' : '' ?>
            </a>
            <?php endif; ?>
            <?php if ($isAdmin || $nav('permission/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/permission/index']) ?>">Permission Requests</a>
            <?php endif; ?>
            <?php if ($isAdmin || $nav('attendance/monthly-attendance')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/monthly-attendance']) ?>">Monthly Attendance</a>
            <?php endif; ?>
            <?php if ($isAdmin || $nav('attendance/leave-settings')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/leave-settings']) ?>">Leave Settings</a>
            <?php endif; ?>
            <?php if ($isAdmin || $nav('holiday/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/holiday/index']) ?>">Holiday's Settings</a>
            <?php endif; ?>
            <?php if ($isAdmin || $nav('rfid-card/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/rfid-card/index']) ?>">Manage RFID Cards</a>
            <?php endif; ?>
        <?php else: ?>
            <!-- Developer / Client: Yii1 developer_header layout with access checks -->
            <?php if ($nav('attendance/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/index']) ?>">All Attendance</a>
            <?php endif; ?>
            <?php if ($nav('attendance/my-attendance')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/my-attendance']) ?>">My Attendance</a>
            <?php endif; ?>
            <?php if ($nav('leave/my-requests')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/my-requests']) ?>">My Leave Requests</a>
            <?php endif; ?>
            <?php if ($nav('leave/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/create']) ?>">Send Leave Requests</a>
            <?php endif; ?>
            <?php if ($nav('permission/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/permission/index']) ?>">
                <?= \common\components\UserAccess::checkSpecificMenuAccess('permission/index') ? 'Permission Requests' : 'My Permissions' ?>
            </a>
            <?php endif; ?>
            <?php if ($nav('permission/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/permission/create']) ?>">Request Permission</a>
            <?php endif; ?>
            <?php if ($nav('leave/index') && ($isAdmin || ($isDev && \common\components\UserAccess::checkSpecificMenuAccess('leave/index')))): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/index']) ?>">
                Leave Requests <?= $pendingLeaves > 0 ? '<span class="badge bg-danger">' . $pendingLeaves . '</span>' : '' ?>
            </a>
            <?php endif; ?>
            <?php if ($nav('attendance/monthly-attendance')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/monthly-attendance']) ?>">Monthly Attendance</a>
            <?php endif; ?>
            <?php if ($nav('attendance/leave-settings')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/leave-settings']) ?>">Leave Settings</a>
            <?php endif; ?>
            <?php if ($nav('holiday/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/holiday/index']) ?>">Holiday's Settings</a>
            <?php endif; ?>
            <?php if ($nav('rfid-card/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/rfid-card/index']) ?>">Manage RFID Cards</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['hr'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 12. Pay Slip -->
    <?php if ($nav('payslip/index') || $nav('salary-allowance/index') || $nav('payslip-upload/index') || $nav('payslip-upload/developer')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-payroll" aria-expanded="<?= $navAria('nav-payroll') ?>">
        <span class="sidebar-icon">&#x1F4B0;</span> Pay Slip
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-payroll') ?>" id="nav-payroll">
        <?php if ($nav('payslip-upload/developer') && $user && !$user->isAdmin() && !$user->isSubAdmin()): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/payslip-upload/developer']) ?>">My Payslips</a>
        <?php endif; ?>
        <?php if ($nav('salary-allowance/deductions')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/salary-allowance/deductions']) ?>">Manage Deductions</a>
        <?php endif; ?>
        <?php if ($nav('salary-allowance/earnings-split')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/salary-allowance/earnings-split']) ?>">Earnings Split</a>
        <?php endif; ?>
        <?php if ($nav('payslip-upload/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/payslip-upload/index']) ?>">Manage Payslips</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['payslip'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 13. Assets -->
    <?php if ($nav('asset/index') || $nav('asset/maintenance') || $nav('asset/categories') || $nav('asset/handover')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-assets" aria-expanded="<?= $navAria('nav-assets') ?>">
        <span class="sidebar-icon"><i class="fas fa-box"></i></span> Assets
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-assets') ?>" id="nav-assets">
        <?php if ($nav('asset/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/asset/index']) ?>">Assets</a>
        <?php endif; ?>
        <?php if ($nav('asset/maintenance')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/asset/maintenance']) ?>">Assets Maintenance</a>
        <?php endif; ?>
        <?php if ($nav('asset/categories')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/asset/categories']) ?>">Assets Categories</a>
        <?php endif; ?>
        <?php if ($nav('asset/handover')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/asset/handover']) ?>">Assets Handover</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['assets'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- 14. IOT -->
    <?php if ($nav('iot-product/index') || $nav('iot-product/masters') || $nav('iot-product/pick-vendor')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-iot" aria-expanded="<?= $navAria('nav-iot') ?>">
        <span class="sidebar-icon"><i class="fas fa-plug"></i></span> IOT
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-iot') ?>" id="nav-iot">
        <?php if ($nav('iot-product/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/index']) ?>">Products</a>
        <?php endif; ?>
        <?php if ($nav('iot-product/masters')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/masters']) ?>">IOT Masters</a>
        <?php endif; ?>
        <?php if ($nav('iot-product/issued')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/issued']) ?>">Issued Products</a>
        <?php endif; ?>
        <?php if ($nav('iot-product/non-purchased')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/non-purchased']) ?>">Non Purchased Products</a>
        <?php endif; ?>
        <?php if ($nav('iot-product/purchased')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/purchased']) ?>">Purchased Products</a>
        <?php endif; ?>
        <?php if ($nav('iot-product/pick-vendor')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/pick-vendor']) ?>">Add Purchased Products</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['iot'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- Quotation / Delivery (merged from delivery app, 2026-04-17) -->
    <?php if ($nav('quotation-dashboard/index') || $nav('client-quotation/index') || $nav('dc/index') || $nav('dc/inward-index') || $nav('delivery-product/index')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-quotation" aria-expanded="<?= $navAria('nav-quotation') ?>">
        <span class="sidebar-icon"><i class="fas fa-file-signature"></i></span> Quotations
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-quotation') ?>" id="nav-quotation">
        <?php if ($nav('quotation-dashboard/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/quotation-dashboard/index']) ?>">Dashboard</a>
        <?php endif; ?>
        <?php if ($nav('client-quotation/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/client-quotation/index']) ?>">Quotations</a>
        <?php endif; ?>
        <?php if ($nav('client-quotation/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/client-quotation/create']) ?>">Add Quotation</a>
        <?php endif; ?>
        <?php if ($nav('dc/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/dc/index']) ?>">Delivery Challans</a>
        <?php endif; ?>
        <?php if ($nav('dc/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/dc/create']) ?>">Add Delivery Challan</a>
        <?php endif; ?>
        <?php if ($nav('dc/inward-index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/dc/inward-index']) ?>">Inward Delivery Challans</a>
        <?php endif; ?>
        <?php if ($nav('delivery-product/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/delivery-product/index']) ?>">Products</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['quotations'] = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <!-- Purchase Order module -->
    <?php if ($nav('po-dashboard/index') || $nav('po/create') || $nav('po/index')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-po" aria-expanded="<?= $navAria('nav-po') ?>">
        <span class="sidebar-icon"><i class="fas fa-file-invoice-dollar"></i></span> Purchase Order
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-po') ?>" id="nav-po">
        <?php if ($nav('po-dashboard/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/po-dashboard/index']) ?>">PO Dashboard</a>
        <?php endif; ?>
        <?php if ($nav('po/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/po/create']) ?>">Add PO</a>
        <?php endif; ?>
        <?php if ($nav('po/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/po/index']) ?>">PO Listing</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $navBlocks['po'] = ob_get_clean(); ?>

    <?php
    // Printed by section (params['sidebar.sections']: label => group keys, '' = no label);
    // falls back to the flat params['sidebar.groups'] order. Empty groups are skipped.
    $navSections = Yii::$app->params['sidebar.sections'] ?? ['' => Yii::$app->params['sidebar.groups'] ?? array_keys($navBlocks)];
    foreach ($navSections as $navLabel => $navKeys) {
        $navHtml = '';
        foreach ($navKeys as $navKey) {
            $navHtml .= $navBlocks[$navKey] ?? '';
        }
        if (strpos($navHtml, 'class="nav-') === false) {
            continue;
        }
        if ($navLabel !== '') {
            echo '<div class="nav-section">' . Html::encode($navLabel) . '</div>';
        }
        echo $navHtml;
    }
    ?>
    <?php /* 15. PM Tool — hidden for now, use later
    <?php if ($ca('pm-task/index') || $ca('pm-quotation/index') || $ca('project-management/index')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-pm" aria-expanded="false">
        <span class="sidebar-icon"><i class="fas fa-clipboard"></i></span> PM Tool
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist" id="nav-pm">
        <?php if ($ca('pm-task/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/pm-task/index']) ?>">PM Tasks</a>
        <?php endif; ?>
        <?php if ($ca('pm-quotation/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/pm-quotation/index']) ?>">PM Quotations</a>
        <?php endif; ?>
        <?php if ($ca('project-management/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/project-management/index']) ?>">Project Management</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    */ ?>

    <?php /* 16. User Management — hidden for now, use later
    <?php if ($isAdmin || $isSubAdmin || $ca('user/index')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-users" aria-expanded="false">
        <span class="sidebar-icon"><i class="fas fa-users"></i></span> User Management
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist" id="nav-users">
        <?php if ($ca('user/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/user/index']) ?>">Users</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    */ ?>

    <?php /* 17. Settings — hidden for now, use later
    <?php if ($isAdmin || $isSubAdmin || $ca('settings/index') || $ca('settings/accounts-portal') || $ca('settings/access-menus')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-settings" aria-expanded="false">
        <span class="sidebar-icon"><i class="fas fa-cog"></i></span> Settings
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist" id="nav-settings">
        <?php if ($ca('settings/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/settings/index']) ?>">Master Settings</a>
        <?php endif; ?>
        <?php if ($ca('settings/accounts-portal')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/settings/accounts-portal']) ?>">Accounts Portal</a>
        <?php endif; ?>
        <?php if ($ca('settings/access-menus')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/settings/access-menus']) ?>">Access Menus</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    */ ?>

    <?php /* 18. Additional Works — hidden for now, use later
    <?php if ($ca('additional-works/index') || $ca('additional-works/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-addl-works" aria-expanded="false">
        <span class="sidebar-icon"><i class="fas fa-pen-to-square"></i></span> Additional Works
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist" id="nav-addl-works">
        <?php if ($ca('additional-works/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/additional-works/index']) ?>">Additional Works</a>
        <?php endif; ?>
        <?php if ($ca('additional-works/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/additional-works/create']) ?>">Add Work</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    */ ?>

<?php endif; /* end !isGuest */ ?>
    </div><!-- /.nav-body -->
</nav>

<!-- ── Mobile sidebar backdrop ─────────────────────────────────────────── -->
<div id="sidebar-backdrop"></div>

<!-- ── Top bar ─────────────────────────────────────────────────────────── -->
<div id="topbar">
    <button type="button" id="sidebar-toggle" aria-label="Open menu"><i class="fas fa-bars"></i></button>

    <?php if (!Yii::$app->user->isGuest && $ca('lead/index')): ?>
    <!-- Quick search: a phone number searches by contact, anything else by client name -->
    <form class="gd-search" method="get" action="<?= \yii\helpers\Url::to(['/lead/index']) ?>" role="search" id="gd-search-form">
        <i class="fas fa-search"></i>
        <input type="search" name="client_name" id="gd-search-input" placeholder="Search leads by name or phone…" aria-label="Search leads" autocomplete="off"
               value="<?= Html::encode((string) (Yii::$app->request->get('client_name') ?: Yii::$app->request->get('contact_no'))) ?>">
    </form>
    <?php endif; ?>

    <div class="ms-auto">
        <?php if (!Yii::$app->user->isGuest): ?>
        <?php if ($ca('attendance/live')): ?>
        <a href="<?= \yii\helpers\Url::to(['/attendance/live']) ?>" class="gd-icon-btn" title="Live attendance" aria-label="Live attendance">
            <span class="live-hdr-dot"></span> <span class="tb-label">Live</span>
        </a>
        <?php endif; ?>
        <?php if ($pendingLeaves > 0): ?>
        <a href="<?= \yii\helpers\Url::to(['/leave/index']) ?>" class="gd-icon-btn" title="Pending leave requests" aria-label="Pending leave requests">
            <i class="fas fa-plane-departure"></i><span class="tb-label">Leaves</span> <span class="badge bg-danger"><?= $pendingLeaves ?></span>
        </a>
        <?php endif; ?>
        <?php if ($openLeads > 0): ?>
        <a href="<?= \yii\helpers\Url::to(['/lead/assigned']) ?>" class="gd-icon-btn" title="Unread lead follow-ups assigned to you" aria-label="Unread lead follow-ups assigned to you">
            <i class="fas fa-bell"></i><span class="tb-label">Follow-ups</span> <span class="badge bg-danger"><?= $openLeads ?></span>
        </a>
        <?php endif; ?>

        <?php
        // My Profile — Yii1 parity: developers open their developer record, others their user.
        $profileDevId = $user ? $user->getDeveloperId() : null;
        $profileUrl   = $profileDevId ? ['/developer/view', 'id' => $profileDevId] : ['/user/view', 'id' => Yii::$app->user->id];
        ?>
        <div class="dropdown">
            <button class="gd-user" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= Html::encode($username) ?>">
                <span class="gd-avatar"><?= Html::encode(mb_substr($username, 0, 1)) ?></span>
                <span class="tb-user"><?= Html::encode($username) ?></span>
                <i class="fas fa-chevron-down small text-muted d-none d-md-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-header"><?= Html::encode($username) ?></span></li>
                <li><?= Html::a('<i class="fas fa-user me-2"></i>My Profile', $profileUrl, ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                <li><?= Html::a('<i class="fas fa-key me-2"></i>Change Password', ['/site/change-password'], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
            </ul>
        </div>
        <?= Html::beginForm(['/site/logout'], 'post', ['id' => 'logout-form', 'class' => 'm-0']) ?>
        <button type="submit" class="gd-logout" id="logout-btn" title="Logout"><i class="fas fa-sign-out-alt"></i><span class="gd-logout-label">Logout</span></button>
        <?= Html::endForm() ?>
        <?php else: ?>
        <?= Html::a('Login', ['/site/login'], ['class' => 'btn btn-sm btn-primary']) ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── Main content ────────────────────────────────────────────────────── -->
<div id="main-content">
    <?php
    // Title strip. Breadcrumbs: $this->params['breadcrumbs'] when a view sets them,
    // otherwise Home / <module> / <page title>.
    $gdModules = [
        'lead' => ['Leads', '/lead/index'], 'lead-followers' => ['Leads', '/lead/index'],
        'client' => ['Clients', '/client/index'], 'client-quotation' => ['Quotations', '/client-quotation/index'],
        'invoice' => ['Invoices', '/invoice/index'], 'product' => ['Products', '/product/index'],
        'developer' => ['Employees', '/developer/index'], 'employee' => ['Employees', '/developer/index'], 'user' => ['Users', '/user/index'],
        'attendance' => ['HR', '/attendance/index'], 'leave' => ['HR', '/leave/index'], 'holiday' => ['HR', '/holiday/index'],
        'permission' => ['HR', '/permission/index'], 'rfid-card' => ['HR', '/rfid-card/index'],
    ];
    $gdTitle  = (string) ($this->title ?? '');
    $gdCrumbs = $this->params['breadcrumbs'] ?? null;
    if ($gdCrumbs === null) {
        $gdCrumbs = [];
        if (isset($gdModules[$currentCtrl]) && $gdModules[$currentCtrl][0] !== $gdTitle) {
            $gdCrumbs[] = ['label' => $gdModules[$currentCtrl][0], 'url' => [$gdModules[$currentCtrl][1]]];
        }
        if ($gdTitle !== '') {
            $gdCrumbs[] = $gdTitle;
        }
    }
    // The page's own <h1> with the same text moves into the strip (kept for screen readers).
    if ($gdTitle !== '') {
        $gdH1    = preg_quote(Html::encode($gdTitle), '/');
        $content = preg_replace('/(<div class="ui-head">\s*<div>\s*)<h1>(' . $gdH1 . ')<\/h1>/iu', '$1<h1 class="gd-dup">$2</h1>', $content, 1) ?? $content;
    }
    ?>
    <?php if (!Yii::$app->user->isGuest && $gdTitle !== ''): ?>
    <div class="gd-titlebar">
        <h1><?= Html::encode($gdTitle) ?></h1>
        <ol class="gd-crumbs" aria-label="Breadcrumb">
            <li><a href="<?= \yii\helpers\Url::to(['/site/index']) ?>" aria-label="Home"><i class="fas fa-house"></i></a></li>
            <?php foreach ($gdCrumbs as $gdCrumb): ?>
            <li><?= is_array($gdCrumb) && isset($gdCrumb['url']) ? Html::a(Html::encode($gdCrumb['label']), $gdCrumb['url']) : Html::encode(is_array($gdCrumb) ? $gdCrumb['label'] : $gdCrumb) ?></li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>
    <?= Alert::widget() ?>
    <?= $content ?>
</div>

<!-- Add Invoice / Add Proforma Modal (matches Yii1 addInvoiceModal) -->
<?php if (!Yii::$app->user->isGuest && !($user && $user->isClient())): ?>
<?php
    $_modalClients    = \backend\models\ClientModel::find()
        ->select(['id', 'client_name', 'company_name'])
        ->where(['status' => 1])
        ->orderBy('client_name')
        ->all();
    $_modalClientData  = \yii\helpers\ArrayHelper::map($_modalClients, 'id', 'client_name');
    $_modalCompanyData = \yii\helpers\ArrayHelper::map($_modalClients, 'id', 'company_name');
?>
<div id="addInvoiceModal" class="modal fade" aria-hidden="true" data-bs-focus="false" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal_invoice_role_type" value="">
                <?php $_gdSimple = \backend\components\Crm::simple(); /* Green Divine mode: just pick the client */ ?>
                <div class="mb-3<?= $_gdSimple ? ' d-none' : '' ?>">
                    <label class="form-label">Invoice Category <span class="text-danger">*</span></label>
                    <?= \kartik\select2\Select2::widget([
                        'name'          => 'modal_invoice_category',
                        'data'          => [
                            \backend\models\Invoice::CATEGORY_SERVICE => 'Service Invoice',
                            \backend\models\Invoice::CATEGORY_PRODUCT => 'Sales Invoice',
                        ],
                        'value'         => $_gdSimple ? \backend\models\Invoice::CATEGORY_SERVICE : null,
                        'options'       => ['id' => 'modal_invoice_category', 'placeholder' => 'Select Invoice Category'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="mb-3<?= $_gdSimple ? ' d-none' : '' ?>">
                    <label class="form-label">Select Company <span class="text-danger">*</span></label>
                    <?= \kartik\select2\Select2::widget([
                        'name'          => 'modal_company_id',
                        'data'          => $_modalCompanyData,
                        'options'       => ['id' => 'modal_company_id', 'placeholder' => 'Select Company'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= $_gdSimple ? 'Client' : 'Select Client' ?> <span class="text-danger">*</span></label>
                    <?= \kartik\select2\Select2::widget([
                        'name'          => 'modal_client_id',
                        'data'          => $_modalClientData,
                        'options'       => ['id' => 'modal_client_id', 'placeholder' => 'Select Client'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                    <span class="text-danger small" id="modal_invoice_error"></span>
                </div>
                <div class="<?= $_gdSimple ? 'd-none' : '' ?>">
                <div class="mb-3">
                    <label class="form-label">Select Project <span class="text-danger">*</span></label>
                    <select id="modal_project_id" class="form-select form-select-sm">
                        <option value="">Select Project</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select Phase</label>
                    <select id="modal_phase_value" class="form-select form-select-sm">
                        <option value="">Select Phase</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select Milestone</label>
                    <select id="modal_milestone_value" class="form-select form-select-sm">
                        <option value="">Select Milestone</option>
                    </select>
                </div>
                </div><!-- /project fields -->
                <?php if ($_gdSimple): ?><p class="small text-muted mb-0">Next you pick the products; price, unit and GST fill in from the catalogue.</p><?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="modal-invoice-submit">Submit</button>
            </div>
        </div>
    </div>
</div>
<?php $this->registerJs(
    'var _invoiceCreateUrl = ' . \yii\helpers\Json::encode(\yii\helpers\Url::to(['/invoice/create'])) . ';'
    . 'var _ajaxProjectsByClient = ' . \yii\helpers\Json::encode(\yii\helpers\Url::to(['/project/get-projects-by-client'])) . ';'
    . 'var _ajaxPhaseValue = ' . \yii\helpers\Json::encode(\yii\helpers\Url::to(['/project/get-phase-value'])) . ';'
    . 'var _ajaxMilestoneValue = ' . \yii\helpers\Json::encode(\yii\helpers\Url::to(['/project/get-milestone-value'])) . ';'
    . 'var _invoiceModalSimple = ' . (\backend\components\Crm::simple() ? 'true' : 'false') . ';',
    \yii\web\View::POS_END
); ?>
<?php endif; ?>

<script>
// Top-bar quick search: a phone number searches the contact field.
(function () {
    var form = document.getElementById('gd-search-form');
    if (!form) return;
    form.addEventListener('submit', function () {
        var input = document.getElementById('gd-search-input');
        var v = input.value.trim();
        input.value = v;
        input.name = /^[+\d][\d\s-]{3,}$/.test(v) ? 'contact_no' : 'client_name';
    });
})();

// Mobile sidebar toggle / close / outside-click
(function () {
    var sidebar  = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebar-backdrop');
    var btnOpen  = document.getElementById('sidebar-toggle');
    var btnClose = document.getElementById('sidebar-close');
    if (!sidebar) return;

    function openSidebar()  {
        sidebar.classList.add('open');
        if (backdrop) backdrop.classList.add('show');
        document.body.classList.add('sidebar-locked');
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('show');
        document.body.classList.remove('sidebar-locked');
    }

    btnOpen && btnOpen.addEventListener('click', function (e) {
        e.stopPropagation();
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    btnClose && btnClose.addEventListener('click', closeSidebar);
    backdrop && backdrop.addEventListener('click', closeSidebar);

    // Close when a sidebar link (not a group toggle) is clicked on mobile.
    sidebar.addEventListener('click', function (e) {
        if (window.innerWidth >= 768) return;
        var link = e.target.closest('a.nav-link');
        if (link && !link.classList.contains('nav-group-toggle')) {
            closeSidebar();
        }
    });

    // Reset state when resizing back to desktop.
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768) closeSidebar();
    });
})();

// Mobile auto search-toggle: hide filter cards behind a search icon on small screens.
(function () {
    function isMobile() { return window.innerWidth < 768; }

    function applySearchToggle() {
        if (!isMobile()) return;

        // A "filter card" = a .card containing a GET form with at least one named text/select input.
        document.querySelectorAll('.card').forEach(function (card) {
            if (card.dataset.searchToggleApplied === '1') return;
            if (card.closest('.ui-page')) return; // redesigned pages have their own Search toggle
            var form = card.querySelector('form[method="get" i], form[method="GET"]');
            if (!form) return;
            var meaningful = form.querySelector('input[name]:not([type="hidden"]):not([type="submit"]), select[name], textarea[name]');
            if (!meaningful) return;

            card.dataset.searchToggleApplied = '1';

            // Build toggle button
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary btn-sm search-toggle-btn';
            btn.innerHTML = '<i class="fa fa-search"></i> Search';
            btn.setAttribute('aria-expanded', 'false');

            card.parentNode.insertBefore(btn, card);

            // If any visible form field has a non-empty value, keep it open.
            var hasFilter = false;
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el.name) return;
                if (el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return;
                if (el.value && el.value.trim() !== '') hasFilter = true;
            });

            if (!hasFilter) {
                card.style.display = 'none';
            } else {
                btn.setAttribute('aria-expanded', 'true');
                btn.innerHTML = '<i class="fa fa-search"></i> Hide Search';
            }

            btn.addEventListener('click', function () {
                var open = card.style.display !== 'none';
                card.style.display = open ? 'none' : '';
                btn.setAttribute('aria-expanded', open ? 'false' : 'true');
                btn.innerHTML = open
                    ? '<i class="fa fa-search"></i> Search'
                    : '<i class="fa fa-search"></i> Hide Search';
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applySearchToggle);
    } else {
        applySearchToggle();
    }
    // Re-evaluate when orientation/size changes (in case toggles were not applied on desktop load)
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applySearchToggle, 250);
    });
})();

// Auto-highlight active sidebar link. The parent group is already expanded
// server-side via the $activeNav/$navGroupMap PHP logic — this block only
// adds the `.active` class to the best-matching link. It must NEVER expand a
// different group, otherwise pages whose controller is cross-linked from
// another group (e.g. "Add Inward Invoices" → /vendor/index) would open two
// groups at once.
(function() {
    var path = window.location.pathname;

    // Prefer the group the server already marked as open (PHP $navGroupMap).
    // If one exists, only search for the best active-link inside that group.
    var serverOpen = document.querySelector('#sidebar .nav-sublist.show');
    var scope      = serverOpen || document;

    var bestLink = null;
    var bestLen  = 0;
    scope.querySelectorAll('.nav-link').forEach(function(link) {
        var href = link.getAttribute('href');
        if (href && path.indexOf(href) === 0 && href.length > bestLen) {
            bestLen  = href.length;
            bestLink = link;
        }
    });

    // Fallback: no server-marked group AND no exact link match (e.g. a
    // controller that isn't in $navGroupMap). Walk the sidebar globally and
    // expand whichever group owns the best prefix match.
    if (!bestLink && !serverOpen) {
        var lastSlash = path.lastIndexOf('/');
        if (lastSlash > 0) {
            var controllerPrefix = path.substring(0, lastSlash + 1);
            document.querySelectorAll('#sidebar .nav-sublist .nav-link').forEach(function(link) {
                var href = link.getAttribute('href');
                if (href && href.indexOf(controllerPrefix) === 0 && !bestLink) {
                    bestLink = link;
                }
            });
        }
    }

    if (bestLink) {
        bestLink.classList.add('active');
        // Only expand the containing group if PHP didn't already pick one.
        if (!serverOpen) {
            var collapse = bestLink.closest('.nav-sublist');
            if (collapse) {
                collapse.classList.add('show');
                var toggle = document.querySelector('[href="#' + collapse.id + '"]');
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
            }
        }
    }
})();
</script>

<?php
/* ── Override yii.confirm → SweetAlert2 ───────────────────────────────
   Yii2 wires every data-confirm link to yii.confirm(), which by default
   delegates to native window.confirm. Patch it here so every data-confirm
   (delete buttons in grid dropdowns, etc.) renders a themed Swal. */
$this->registerJs(<<<'JS'
(function () {
    if (typeof window.Swal === 'undefined') return;
    if (window.yii && typeof yii.confirm === 'function') {
        yii.confirm = function (message, ok, cancel) {
            Swal.fire({
                text: message || 'Are you sure?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                heightAuto: false
            }).then(function (r) {
                if (r.isConfirmed) { if (typeof ok === 'function') ok(); }
                else               { if (typeof cancel === 'function') cancel(); }
            });
            return false; // wait for async ok/cancel
        };
    }
})();
JS, \yii\web\View::POS_READY);

/* ── Bootstrap tooltips on action buttons — runs at POS_READY so
   bootstrap.Tooltip is guaranteed loaded. ─────────────────────────── */
$this->registerJs(<<<'JS'
(function () {
    var attempts = 0;
    function initTooltips(root) {
        if (!window.bootstrap || !bootstrap.Tooltip) {
            if (attempts++ < 10) { setTimeout(function () { initTooltips(root); }, 120); return; }
            console.warn('[tooltips] bootstrap.Tooltip unavailable after retries');
            return;
        }
        var nodes = (root || document).querySelectorAll('.act-btn[title], [data-bs-toggle="tooltip"], [title].btn');
        var bound = 0;
        nodes.forEach(function (el) {
            if (el.dataset.tooltipInit === '1') return;
            el.dataset.tooltipInit = '1';
            try {
                new bootstrap.Tooltip(el, {
                    container: 'body',
                    trigger: 'hover focus',
                    animation: false,
                    delay: { show: 100, hide: 50 }
                });
                bound++;
            } catch (e) { console.warn('[tooltips] init failed on', el, e); }
        });
    }
    initTooltips();
    document.addEventListener('pjax:end',     function (e) { initTooltips(e.target); });
    document.addEventListener('pjax:success', function (e) { initTooltips(e.target); });
    window.initActionTooltips = initTooltips;
})();
JS, \yii\web\View::POS_READY);

/* ── Logout confirmation — SweetAlert2 prompt before submitting the logout form ── */
$this->registerJs(<<<'JS'
(function () {
    var form = document.getElementById('logout-form');
    if (!form) return;
    var confirmed = false;
    form.addEventListener('submit', function (e) {
        if (confirmed) return;
        e.preventDefault();
        Swal.fire({
            icon: 'question',
            title: 'Log out?',
            text: 'You will be signed out of Maestro ERP.',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, log out',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (result.isConfirmed) {
                confirmed = true;
                form.submit();
            }
        });
    });
})();
JS, \yii\web\View::POS_READY);

/* ── Single-open dropdowns ────────────────────────────────────────────
   Defensive guard: when one Bootstrap dropdown toggle is clicked,
   force-close all other open dropdowns by directly stripping the
   .show class and resetting aria-expanded. Listing pages with many
   ⋮ action menus were ending up with multiple menus visible at once;
   this handler runs in the capture phase before Bootstrap toggles the
   new menu, so the others are guaranteed closed first. */
$this->registerJs(<<<'JS'
(function () {
    function closeAllExcept(keepToggle) {
        document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) {
            var parent = menu.parentElement;
            if (!parent) return;
            var toggle = parent.querySelector('[data-bs-toggle="dropdown"]');
            if (toggle && toggle === keepToggle) return;
            menu.classList.remove('show');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
                toggle.classList.remove('show');
            }
            parent.classList.remove('show');
        });
    }

    document.addEventListener('click', function (e) {
        var clickedToggle = e.target.closest('[data-bs-toggle="dropdown"]');
        if (clickedToggle) {
            closeAllExcept(clickedToggle);
            return;
        }
        // Click outside any toggle and outside any open menu → close all.
        if (!e.target.closest('.dropdown-menu')) {
            closeAllExcept(null);
        }
    }, true);
})();
JS, \yii\web\View::POS_READY);
?>

<?php
/* ── Disable the browser's native "previously typed" autocomplete dropdown on all
   Kartik date-picker inputs (TYPE_INPUT text fields). Covers inputs present at load
   plus any added later via AJAX/modals, and re-applies just before the field gets
   focus so the browser never shows its own history suggestions over the calendar. ── */
$this->registerJs(<<<'JS'
(function () {
    function killAutofill(el) {
        if (!el) return;
        el.setAttribute('autocomplete', 'off');
        el.setAttribute('autocorrect', 'off');
        el.setAttribute('autocapitalize', 'off');
        el.setAttribute('spellcheck', 'false');
    }
    document.querySelectorAll('input.krajee-datepicker').forEach(killAutofill);
    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.matches && e.target.matches('input.krajee-datepicker')) {
            killAutofill(e.target);
        }
    }, true);
})();
JS, \yii\web\View::POS_READY);
?>

<?php
/* ── Add Invoice Modal JS — registered via Yii so it runs AFTER jQuery loads ── */
if (!Yii::$app->user->isGuest && !$isClient) {
    $this->registerJs(<<<'JS'

// ── Fix Select2 search focus inside Bootstrap 5 modal ──
jQuery(document).on('select2:open', function() {
    setTimeout(function() {
        var field = document.querySelector('.select2-container--open .select2-search__field');
        if (field) field.focus();
    }, 100);
});

// ── openInvoiceModal — called by sidebar links ──
window.openInvoiceModal = function(title, roleType) {
    document.querySelector('#addInvoiceModal .modal-title').textContent = title;
    document.getElementById('modal_invoice_role_type').value = roleType;
    _modalSyncing = true;
    jQuery('#modal_invoice_category').val(window._invoiceModalSimple ? '1' : null).trigger('change');
    jQuery('#modal_company_id').val(null).trigger('change');
    jQuery('#modal_client_id').val(null).trigger('change');
    _modalSyncing = false;
    document.getElementById('modal_project_id').innerHTML = '<option value="">Select Project</option>';
    document.getElementById('modal_phase_value').innerHTML = '<option value="">Select Phase</option>';
    document.getElementById('modal_milestone_value').innerHTML = '<option value="">Select Milestone</option>';
    document.getElementById('modal_invoice_error').textContent = '';
};

// Company ↔ Client bidirectional autofill (both Select2s use client.id as the key)
var _modalSyncing = false;
jQuery('#modal_company_id').on('change', function() {
    if (_modalSyncing) return;
    _modalSyncing = true;
    jQuery('#modal_client_id').val(jQuery(this).val()).trigger('change');
    _modalSyncing = false;
});
jQuery('#modal_client_id').on('change', function() {
    if (_modalSyncing) return;
    _modalSyncing = true;
    jQuery('#modal_company_id').val(jQuery(this).val()).trigger('change');
    _modalSyncing = false;
});

// Client → Project AJAX cascade (matches Yii1)
jQuery('#modal_client_id').on('change', function() {
    var clientId = jQuery(this).val();
    var projSel  = document.getElementById('modal_project_id');
    projSel.innerHTML = '<option value="">Select Project</option>';
    document.getElementById('modal_phase_value').innerHTML = '<option value="">Select Phase</option>';
    document.getElementById('modal_milestone_value').innerHTML = '<option value="">Select Milestone</option>';
    if (clientId) {
        jQuery.ajax({
            url: _ajaxProjectsByClient,
            type: 'POST',
            data: { client_id: clientId, _csrf: yii.getCsrfToken() },
            dataType: 'json',
            success: function(data) {
                jQuery.each(data, function(key, value) {
                    projSel.appendChild(new Option(value, key));
                });
            }
        });
    }
});

// Project → Phase & Milestone AJAX cascade (matches Yii1)
jQuery('#modal_project_id').on('change', function() {
    var projectId = jQuery(this).val();
    var phaseSel     = document.getElementById('modal_phase_value');
    var milestoneSel = document.getElementById('modal_milestone_value');
    phaseSel.innerHTML     = '<option value="">Select Phase</option>';
    milestoneSel.innerHTML = '<option value="">Select Milestone</option>';
    if (projectId) {
        jQuery.ajax({
            url: _ajaxPhaseValue,
            type: 'POST',
            data: { project_id: projectId, _csrf: yii.getCsrfToken() },
            dataType: 'json',
            success: function(data) {
                jQuery.each(data, function(key, value) {
                    phaseSel.appendChild(new Option(value, key));
                });
            }
        });
        jQuery.ajax({
            url: _ajaxMilestoneValue,
            type: 'POST',
            data: { project_id: projectId, _csrf: yii.getCsrfToken() },
            dataType: 'json',
            success: function(data) {
                jQuery.each(data, function(key, value) {
                    milestoneSel.appendChild(new Option(value, key));
                });
            }
        });
    }
});

// Modal submit — validates & redirects (matches Yii1 proforma-submit)
jQuery('#modal-invoice-submit').on('click', function() {
    var errEl = document.getElementById('modal_invoice_error');
    errEl.textContent = '';
    var category  = document.getElementById('modal_invoice_category').value || '';
    var client    = document.getElementById('modal_client_id').value || '';
    var project   = document.getElementById('modal_project_id').value || '';
    var roleType  = document.getElementById('modal_invoice_role_type').value || '1';
    var phase     = document.getElementById('modal_phase_value').value || '';
    var milestone = document.getElementById('modal_milestone_value').value || '';

    if (!category) { Swal.fire({icon:'warning', title:'Required', text:'Please Select Invoice Category!'}); return; }
    if (!client)   { Swal.fire({icon:'warning', title:'Required', text:'Please Select Client!'}); return; }
    if (!project && !window._invoiceModalSimple) { Swal.fire({icon:'warning', title:'Required', text:'Please Select Project!'}); return; }

    var url = _invoiceCreateUrl
        + '?invoice_category=' + encodeURIComponent(category)
        + '&client_id=' + encodeURIComponent(client)
        + '&project_id=' + encodeURIComponent(project)
        + '&invoice_role=' + encodeURIComponent(roleType)
        + '&phase_value=' + encodeURIComponent(phase)
        + '&milestone_value=' + encodeURIComponent(milestone);
    window.location.href = url;
});

JS, \yii\web\View::POS_READY);
}
?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage();
