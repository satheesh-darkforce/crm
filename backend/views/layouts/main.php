<?php

/** @var \yii\web\View $this */
/** @var string $content */

use backend\assets\AppAsset;
use common\components\UserAccess;
use common\widgets\Alert;
use yii\bootstrap5\Html;

AppAsset::register($this);

$user     = Yii::$app->user->identity;
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
    <title><?= Html::encode($this->title) ?> — Maestro ERP</title>
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
    <style>
        /* ── Sidebar layout (light theme, redesigned) ────────── */
        :root {
            --sb-width: 252px;
            --sb-bg: #eef2f7;
            --sb-text: #475569;
            --sb-text-muted: #94a3b8;
            --sb-text-strong: #0f172a;
            --sb-accent: #0d9488;
            --sb-accent-bg: #ffffff;
            --sb-border: #dde3ec;
            --sb-hover: #e2e8f0;
            --sb-sub-text: #64748b;
            --sb-body-bg: #ffffff;
            --sb-topbar-bg: #ffffff;
        }

        body {
            display: flex;
            min-height: 100vh;
            background: var(--sb-body-bg);
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        #sidebar {
            width: var(--sb-width);
            min-width: var(--sb-width);
            background: var(--sb-bg);
            color: var(--sb-text);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1040;
            transition: width .25s ease;
            border-right: 1px solid var(--sb-border);
        }

        /* slim scrollbar */
        #sidebar::-webkit-scrollbar { width: 6px; }
        #sidebar::-webkit-scrollbar-track { background: transparent; }
        #sidebar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        #sidebar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* ── Brand ── */
        #sidebar .brand {
            padding: 1.15rem 1.35rem 1rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--sb-text-strong);
            letter-spacing: .2px;
            border-bottom: 1px solid var(--sb-border);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: .65rem;
            position: sticky;
            top: 0;
            background: var(--sb-bg);
            z-index: 2;
            text-transform: none;
        }
        #sidebar .brand::before {
            content: "M";
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #1d4ed8;
            color: #fff;
            font-size: .85rem;
            font-weight: 700;
            letter-spacing: 0;
        }
        #sidebar .brand span { color: #1d4ed8; font-weight: 700; }

        /* ── Section heading ── */
        #sidebar .nav-section {
            padding: 1.05rem 1.35rem .35rem;
            font-size: .67rem;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: var(--sb-text-muted);
            font-weight: 600;
        }

        /* ── Nav link (leaf) ── */
        #sidebar .nav-link {
            color: var(--sb-text);
            padding: .55rem .9rem;
            margin: 1px .75rem;
            font-size: .85rem;
            font-weight: 500;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: .7rem;
            transition: background .15s ease, color .15s ease;
            position: relative;
        }
        #sidebar .nav-link:hover {
            background: var(--sb-hover);
            color: var(--sb-text-strong);
        }
        #sidebar .nav-link.active {
            background: var(--sb-accent-bg);
            color: var(--sb-accent);
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
        }
        #sidebar .nav-link .badge {
            margin-left: auto;
            font-size: .65rem;
            font-weight: 600;
            padding: .22em .55em;
            border-radius: 999px;
        }
        /* Yii1 parity: Add Work Report grayed + non-clickable while a task
           timer is running. pointer-events:none stops navigation. */
        #sidebar .nav-link.wr-disabled,
        #sidebar .nav-link.wr-disabled:hover,
        #sidebar .nav-link.wr-disabled:focus,
        #sidebar .nav-link.wr-disabled.active {
            color: #A8A8A8 !important;
            background: transparent !important;
            pointer-events: none;
            box-shadow: none;
        }

        /* ── Group toggle (parent) ── */
        #sidebar .nav-group-toggle {
            color: var(--sb-text);
            padding: .6rem .9rem;
            margin: 1px .75rem;
            font-size: .87rem;
            font-weight: 500;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: .7rem;
            cursor: pointer;
            user-select: none;
            transition: background .15s ease, color .15s ease;
            text-decoration: none;
        }
        #sidebar .nav-group-toggle:hover {
            background: var(--sb-hover);
            color: var(--sb-text-strong);
        }
        #sidebar .nav-group-toggle[aria-expanded="true"] {
            color: var(--sb-text-strong);
            font-weight: 600;
        }
        #sidebar .nav-group-toggle .arrow {
            margin-left: auto;
            font-size: .55rem;
            color: var(--sb-text-muted);
            transition: transform .25s ease;
        }
        #sidebar .nav-group-toggle[aria-expanded="true"] .arrow {
            transform: rotate(90deg);
            color: var(--sb-text-strong);
        }
        #sidebar .nav-group-toggle .badge {
            margin-left: auto;
            margin-right: .45rem;
            font-size: .65rem;
            font-weight: 600;
            padding: .22em .55em;
            border-radius: 999px;
        }
        #sidebar .nav-group-toggle .badge + .arrow { margin-left: 0; }

        /* ── Sublist (children) ── */
        #sidebar .nav-sublist {
            padding: .1rem 0 .35rem;
            margin: 0 .75rem .2rem 1.65rem;
            position: relative;
            border-left: 1px solid var(--sb-border);
        }
        #sidebar .nav-sublist .nav-link {
            padding: .42rem .85rem .42rem 1rem;
            margin: 1px .3rem 1px .55rem;
            font-size: .82rem;
            font-weight: 500;
            color: var(--sb-sub-text);
            border-radius: 6px;
        }
        #sidebar .nav-sublist .nav-link:hover {
            background: var(--sb-hover);
            color: var(--sb-text-strong);
        }
        #sidebar .nav-sublist .nav-link.active {
            background: transparent;
            color: var(--sb-accent);
            font-weight: 600;
        }
        #sidebar .nav-sublist .nav-link.active::before {
            content: "";
            position: absolute;
            left: -1px;
            top: .4rem;
            bottom: .4rem;
            width: 2px;
            background: var(--sb-accent);
            border-radius: 2px;
        }

        /* icon glyph */
        .sidebar-icon {
            width: 20px;
            text-align: center;
            flex-shrink: 0;
            font-size: 1rem;
            color: var(--sb-text-muted);
            transition: color .15s ease;
        }
        #sidebar .nav-link:hover .sidebar-icon,
        #sidebar .nav-group-toggle:hover .sidebar-icon,
        #sidebar .nav-group-toggle[aria-expanded="true"] .sidebar-icon {
            color: var(--sb-text-strong);
        }
        #sidebar .nav-link.active .sidebar-icon { color: var(--sb-accent); }

        /* ── Top bar ── */
        #topbar {
            height: 56px;
            background: var(--sb-topbar-bg);
            border-bottom: 1px solid var(--sb-border);
            position: fixed;
            top: 0;
            left: var(--sb-width);
            right: 0;
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 1.75rem;
            gap: .75rem;
        }
        #topbar .topbar-title {
            font-size: .98rem;
            font-weight: 600;
            color: var(--sb-text-strong);
            letter-spacing: .15px;
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #topbar .ms-auto { display: flex; align-items: center; gap: .6rem; min-width: 0; }
        #topbar .ms-auto > * { flex-shrink: 0; }
        /* Phones: header buttons collapse to icon + count so the bar never
           pushes the page wider than the screen. */
        #topbar .tb-icon { display: none; }
        #topbar .tb-user { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        @media (max-width: 575.98px) {
            #topbar { gap: .5rem; }
            #topbar .ms-auto { gap: .35rem; }
            #topbar .tb-label { display: none; }
            #topbar .tb-icon { display: inline-block; margin-right: .2rem; }
            #topbar .tb-user { max-width: 92px; }
        }
        .live-hdr-dot { width: .55rem; height: .55rem; border-radius: 50%; background: #e74c3c; display: inline-block; animation: live-hdr-pulse 1.3s infinite; }
        @keyframes live-hdr-pulse { 0% { box-shadow: 0 0 0 0 rgba(231,76,60,.6); } 70% { box-shadow: 0 0 0 6px rgba(231,76,60,0); } 100% { box-shadow: 0 0 0 0 rgba(231,76,60,0); } }

        /* ── Main content ── */
        #main-content {
            margin-left: var(--sb-width);
            margin-top: 56px;
            flex: 1;
            padding: 1.5rem 1.75rem;
            min-width: 0;
        }

        /* Fix dropdown menus clipped inside .table-responsive */
        .table-responsive { overflow: visible !important; }
        @media (max-width: 767.98px) {
            .table-responsive { overflow-x: auto !important; }
        }

        /* ── Mobile sidebar close button + backdrop ── */
        .sidebar-close {
            display: none;
            position: absolute;
            top: .75rem;
            right: .75rem;
            width: 34px;
            height: 34px;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: var(--sb-text-strong);
            font-size: 1.25rem;
            line-height: 1;
            border: 1px solid var(--sb-border);
            border-radius: 6px;
            cursor: pointer;
            z-index: 2;
        }
        .sidebar-close:hover { background: #f1f5f9; }
        #sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .45);
            z-index: 1039;
            display: none;
        }
        #sidebar-backdrop.show { display: block; }
        /* Lock background scroll while the mobile drawer is open */
        body.sidebar-locked { overflow: hidden; }

        /* ── Responsive ── */
        @media (max-width: 767.98px) {
            #sidebar {
                width: 0;
                min-width: 0;
                overflow: hidden;
                height: 100vh;
                height: 100dvh;
                max-height: 100dvh;
            }
            #sidebar.open {
                width: var(--sb-width);
                min-width: var(--sb-width);
                overflow-y: auto;
                box-shadow: 4px 0 18px rgba(15, 23, 42, .15);
            }
            #sidebar.open .sidebar-close { display: flex; }
            #topbar { left: 0; padding: 0 1rem; }
            #main-content { margin-left: 0; padding-left: 1rem; padding-right: 1rem; }
        }
        @media (min-width: 768px) {
            #sidebar-backdrop,
            #sidebar-backdrop.show { display: none !important; }
            .sidebar-close { display: none !important; }
        }

        /* ── Site-wide mobile responsive guard ── */
        @media (max-width: 767.98px) {
            html, body { overflow-x: hidden; max-width: 100vw; }
            #main-content { max-width: 100vw; }

            /* Page header rows stack/wrap nicely on mobile */
            #main-content .d-flex.justify-content-between { flex-wrap: wrap; gap: .5rem; }
            /* Action button groups in headers wrap and stretch */
            #main-content .d-flex.justify-content-between > .d-flex { flex-wrap: wrap; }

            /* Tables always scroll horizontally inside their container */
            .table-responsive { overflow-x: auto !important; -webkit-overflow-scrolling: touch; }

            /* Card padding tighter on mobile */
            .card-body { padding: .85rem; }

            /* GridView footer/summary wraps */
            .summary, .grid-view .summary { font-size: .8rem; }

            /* Long unbreakable strings (URLs, emails) can break within table cells.
               break-word wraps only when a word has nowhere else to break — keeps
               normal words like dates and codes intact, unlike `anywhere`. */
            td, th { overflow-wrap: break-word; word-break: normal; }

            /* Pagination wraps if too wide */
            .pagination { flex-wrap: wrap; }

            /* Bootstrap modals fit viewport */
            .modal-dialog { margin: .5rem; }
        }

        /* ── Mobile search-filter toggle ── */
        .search-toggle-btn {
            display: none;
            align-items: center;
            gap: .35rem;
            margin-bottom: .65rem;
        }
        .search-toggle-btn .badge { font-size: .65rem; }
        @media (max-width: 767.98px) {
            .search-toggle-btn { display: inline-flex; }
        }
    </style>
</head>
<body>
<?php $this->beginBody() ?>

<!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
<nav id="sidebar">
    <div class="brand">
        <span>Maestro</span> ERP
        <button type="button" class="sidebar-close" id="sidebar-close" aria-label="Close menu">&times;</button>
    </div>

<?php if (!Yii::$app->user->isGuest): ?>

    <!-- 1. Dashboard -->
    <?php if ($ca('site/index')): ?>
    <a class="nav-link" href="<?= \yii\helpers\Url::to(['/site/index']) ?>">
        <span class="sidebar-icon"><i class="fas fa-bars"></i></span> Dashboard
    </a>
    <?php endif; ?>

    <!-- 2. Clients -->
    <?php if ($ca('client/index') || $ca('client/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-clients" aria-expanded="<?= $navAria('nav-clients') ?>">
        <span class="sidebar-icon"><i class="fas fa-building"></i></span> Clients
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-clients') ?>" id="nav-clients">
        <?php if ($ca('client/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/client/index']) ?>">Clients</a>
        <?php endif; ?>
        <?php if ($ca('client/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/client/create']) ?>">Add New Client</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 3. Projects -->
    <?php if ($ca('project/index') || $ca('project/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-projects" aria-expanded="<?= $navAria('nav-projects') ?>">
        <span class="sidebar-icon"><i class="fas fa-folder"></i></span> Projects
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-projects') ?>" id="nav-projects">
        <?php if ($ca('project/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/project/index']) ?>">Projects</a>
        <?php endif; ?>
        <?php if ($ca('project/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/project/create']) ?>">Add New Project</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 3b. AMC -->
    <?php if ($ca('amc/projects') || $ca('amc/active') || $ca('amc/renew-list') || $ca('amc/ended') || $ca('amc/completed-projects')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-amc" aria-expanded="<?= $navAria('nav-amc') ?>">
        <span class="sidebar-icon"><i class="fas fa-tools"></i></span> AMC
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-amc') ?>" id="nav-amc">
        <?php if ($ca('amc/projects')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/amc/projects']) ?>">AMC Projects</a>
        <?php endif; ?>
        <?php if ($ca('amc/active')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/amc/active']) ?>">Active AMC</a>
        <?php endif; ?>
        <?php if ($ca('amc/renew-list')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/amc/renew-list']) ?>">Current Month Renew</a>
        <?php endif; ?>
        <?php if ($ca('amc/ended')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/amc/ended']) ?>">Ended AMC</a>
        <?php endif; ?>
        <?php /* Completed Projects menu hidden per request — the amc/completed-projects
                action + view still exist; re-add this link to expose it again. */ ?>
    </div>
    <?php endif; ?>

    <!-- 4. Employees -->
    <?php if ($ca('developer/index') || $ca('developer/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-employees" aria-expanded="<?= $navAria('nav-employees') ?>">
        <span class="sidebar-icon"><i class="fas fa-user"></i></span> Employees
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-employees') ?>" id="nav-employees">
        <?php if ($ca('developer/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/developer/index']) ?>">Employees</a>
        <?php endif; ?>
        <?php if ($ca('developer/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/developer/create']) ?>">Add New Employee</a>
        <?php endif; ?>
        <?php if ($ca('developer/ex-employees')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/developer/ex-employees']) ?>">Ex-Employees</a>
        <?php endif; ?>
        <?php if ($ca('developer/create-payslip')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/developer/create-payslip']) ?>">Create Payslip</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 5. Work Reports -->
    <?php if ($ca('work-report/index') || $ca('work-report/create') || $ca('work-report/my-work-reports')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-workreports" aria-expanded="<?= $navAria('nav-workreports') ?>">
        <span class="sidebar-icon"><i class="fas fa-chart-bar"></i></span> Work Reports
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-workreports') ?>" id="nav-workreports">
        <?php // "Add Work Report" is shown only when the user has the work-report/create
               // permission. Admin / sub-admin headers do not show this link at all (Yii1
               // parity with maestro_erp/protected/components/admin_header.php).
               // Yii1 parity: grayed out while a task timer is running. ?>
        <?php if (!$isAdmin && !$isSubAdmin && $ca('work-report/create')): ?>
        <a class="nav-link<?= $hasRunningTask ? ' wr-disabled' : '' ?>"
           href="<?= \yii\helpers\Url::to(['/work-report/create']) ?>"
           <?php if ($hasRunningTask): ?>title="Stop your running task before adding a work report" aria-disabled="true"<?php endif; ?>>Add Work Report</a>
        <?php endif; ?>
        <?php if ($ca('work-report/my-work-reports')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/my-work-reports']) ?>">My Work Reports</a>
        <?php endif; ?>
        <?php if ($ca('project/all-project')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/project/all-project']) ?>">All Projects</a>
        <?php endif; ?>
        <?php if ($ca('work-report/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/index']) ?>">Daily Work Report</a>
        <?php endif; ?>
        <?php if ($ca('work-report/yesterday')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/yesterday']) ?>">Employee yesterday's work report</a>
        <?php endif; ?>
        <?php if ($ca('work-report/developer-current-month')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/developer-current-month']) ?>">Employee monthly work report</a>
        <?php endif; ?>
        <?php if ($ca('work-report/cumulative-employee')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/cumulative-employee']) ?>">Employee based cumulative report</a>
        <?php endif; ?>
        <?php if ($ca('work-report/monthly-by-project')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/monthly-by-project']) ?>">Project based monthly work report</a>
        <?php endif; ?>
        <?php if ($ca('work-report/cumulative-client')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/cumulative-client']) ?>">Client based cumulative report</a>
        <?php endif; ?>
        <?php if ($ca('work-report/monthly-by-employee')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/monthly-by-employee']) ?>">Monthly report</a>
        <?php endif; ?>
        <?php if ($ca('work-report/overall')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/overall']) ?>">Overall report</a>
        <?php endif; ?>
        <?php if ($ca('work-report/performance')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/work-report/performance']) ?>">Performance report</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 6. Tasks -->
    <?php if ($ca('task/index') || $ca('task/create') || $ca('task/my-new-task-list') || $ca('task/completed-task-list') || $ca('task/assigned-tasks')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-tasks" aria-expanded="<?= $navAria('nav-tasks') ?>">
        <span class="sidebar-icon"><i class="fas fa-check"></i></span> Tasks
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-tasks') ?>" id="nav-tasks">
        <?php if ($ca('task/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/index']) ?>">My Tasks</a>
        <?php endif; ?>
        <?php if ($ca('task/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/create']) ?>">Add Task</a>
        <?php endif; ?>
        <?php if ($ca('task/my-new-task-list')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/my-new-task-list']) ?>">My New Tasks</a>
        <?php endif; ?>
        <?php if ($ca('task/completed-task-list')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/completed-task-list']) ?>">Completed Task List</a>
        <?php endif; ?>
        <?php if ($ca('task/assigned-tasks')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/task/assigned-tasks']) ?>">Assigned Task List</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 7. Leads -->
    <?php if ($ca('lead/index') || $ca('lead/my-leads') || $ca('lead/create') || $ca('lead/assigned') || $ca('lead/converted') || $ca('lead/dropped') || $ca('lead/lead-master') || $ca('lead/upload-lead')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-leads" aria-expanded="<?= $navAria('nav-leads') ?>">
        <span class="sidebar-icon"><i class="fas fa-bullseye"></i></span> Leads
        <?= $openLeads > 0 ? '<span class="badge bg-danger ms-1">' . $openLeads . '</span>' : '' ?>
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-leads') ?>" id="nav-leads">
        <?php if ($ca('lead/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/index']) ?>">Manage Leads</a>
        <?php endif; ?>
        <?php if ($ca('lead/my-leads')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/my-leads']) ?>">My Leads</a>
        <?php endif; ?>
        <?php if ($ca('lead/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/create']) ?>">Add New Lead</a>
        <?php endif; ?>
        <?php if ($ca('lead/assigned')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/assigned']) ?>">
            Assigned Leads <?= $openLeads > 0 ? '<span class="badge bg-danger">' . $openLeads . '</span>' : '' ?>
        </a>
        <?php endif; ?>
        <?php if ($ca('lead/converted')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/converted']) ?>">Converted Leads</a>
        <?php endif; ?>
        <?php if ($ca('lead/dropped')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/dropped']) ?>">Dropped Leads</a>
        <?php endif; ?>
        <?php /* Proposals stays hidden until that module is rolled out.
        <?php if ($ca('proposal/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/proposal/index']) ?>">Proposals</a>
        <?php endif; ?>
        */ ?>
        <?php if ($ca('lead/lead-master')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/lead-master']) ?>">Lead Master</a>
        <?php endif; ?>
        <?php if ($ca('lead/upload-lead')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/lead/upload-lead']) ?>">Upload Lead</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 8. Domains -->
    <?php if ($ca('domain/index') || $ca('domain/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-domains" aria-expanded="<?= $navAria('nav-domains') ?>">
        <span class="sidebar-icon"><i class="fas fa-globe"></i></span> Domains
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-domains') ?>" id="nav-domains">
        <?php if ($ca('domain/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/domain/index']) ?>">Domains</a>
        <?php endif; ?>
        <?php if ($ca('domain/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/domain/create']) ?>">Add Domain</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 9. Invoices (matches Yii1 admin_header / developer_header submenu order) -->
    <?php if ($ca('invoice/index') || $ca('invoice/create') || $ca('invoice/proforma') || $ca('inward-invoice/index') || $ca('inward-invoice/create') || $ca('invoice/monthly-report') || $ca('bank-detail/index') || $ca('bank-detail/create') || $ca('settings/invoice') || $ca('invoice/client-invoice')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-invoices" aria-expanded="<?= $navAria('nav-invoices') ?>">
        <span class="sidebar-icon"><i class="fas fa-file-alt"></i></span> <?= $isClient ? 'My Invoices' : 'Invoices' ?>
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-invoices') ?>" id="nav-invoices">
        <?php if ($isClient): ?>
            <?php if ($ca('invoice/client-invoice')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/invoice/client-invoice']) ?>">My Invoices</a>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($ca('invoice/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/invoice/index']) ?>">Invoices</a>
            <?php endif; ?>
            <?php if ($ca('invoice/create')): ?>
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#addInvoiceModal"
               onclick="openInvoiceModal('Add Invoice', '1')">Add Invoice</a>
            <?php endif; ?>
            <?php if ($ca('invoice/proforma')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/invoice/proforma']) ?>">Proforma Invoices</a>
            <?php endif; ?>
            <?php if ($ca('invoice/create')): ?>
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#addInvoiceModal"
               onclick="openInvoiceModal('Add Proforma Invoice', '2')">Add Proforma</a>
            <?php endif; ?>
            <?php if ($ca('inward-invoice/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/inward-invoice/index']) ?>">Inward Invoices</a>
            <?php endif; ?>
            <?php if ($ca('inward-invoice/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/inward-invoice/create']) ?>">Add Inward Invoices</a>
            <?php endif; ?>
            <?php if ($ca('invoice/monthly-report')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/invoice/monthly-report']) ?>">Monthly Report</a>
            <?php endif; ?>
            <?php if ($ca('bank-detail/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/bank-detail/index']) ?>">Payment Bank Details</a>
            <?php endif; ?>
            <?php if ($ca('bank-detail/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/bank-detail/create']) ?>">Add Bank Detail</a>
            <?php endif; ?>
            <?php /* TODO: Uncomment when Quotation module is ready for use
            <?php if ($ca('quotation/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/quotation/index']) ?>">Quotations</a>
            <?php endif; ?>
            */ ?>
            <?php if ($ca('settings/invoice')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/settings/invoice']) ?>">Invoice Settings</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 10. Vendors -->
    <?php if ($ca('vendor/index') || $ca('vendor/create')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-vendors" aria-expanded="<?= $navAria('nav-vendors') ?>">
        <span class="sidebar-icon"><i class="fas fa-box"></i></span> Vendors
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-vendors') ?>" id="nav-vendors">
        <?php if ($ca('vendor/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/vendor/index']) ?>">Vendors</a>
        <?php endif; ?>
        <?php if ($ca('vendor/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/vendor/create']) ?>">Add Vendor</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 11. Attendance -->
    <?php
    // Yii1 parity: sub-admin only sees the toggle when at least one child item
    // is permitted (admin always sees it). Without this, sub-admins without any
    // attendance grants saw an empty Attendance group.
    $showAttendanceMenu = $isAdmin
        || $ca('attendance/index') || $ca('attendance/my-attendance') || $ca('leave/my-requests')
        || $ca('leave/create') || $ca('leave/index') || $ca('attendance/monthly-attendance')
        || $ca('permission/index') || $ca('permission/create')
        || $ca('attendance/leave-settings') || $ca('holiday/index') || $ca('rfid-card/index');
    ?>
    <?php if ($showAttendanceMenu): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-attendance" aria-expanded="<?= $navAria('nav-attendance') ?>">
        <span class="sidebar-icon"><i class="fas fa-clock"></i></span> <?= ($isAdmin || $isSubAdmin) ? 'Attendance' : 'My Attendance' ?>
        <?= $pendingLeaves > 0 ? '<span class="badge bg-danger">' . $pendingLeaves . '</span>' : '' ?>
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-attendance') ?>" id="nav-attendance">
        <?php if ($isAdmin || $isSubAdmin): ?>
            <!-- Admin / Sub-admin: Yii1 admin_header layout with access checks for sub-admin -->
            <?php if ($isAdmin || $ca('attendance/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/index']) ?>">Attendance</a>
            <?php endif; ?>
            <?php if ($ca('attendance/live')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/live']) ?>">Live Attendance</a>
            <?php endif; ?>
            <?php if ($isAdmin || $ca('leave/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/create']) ?>">Add Leave Request</a>
            <?php endif; ?>
            <?php if ($isAdmin || $ca('leave/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/index']) ?>">
                Leave Requests <?= $pendingLeaves > 0 ? '<span class="badge bg-danger">' . $pendingLeaves . '</span>' : '' ?>
            </a>
            <?php endif; ?>
            <?php if ($isAdmin || $ca('permission/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/permission/index']) ?>">Permission Requests</a>
            <?php endif; ?>
            <?php if ($isAdmin || $ca('attendance/monthly-attendance')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/monthly-attendance']) ?>">Monthly Attendance</a>
            <?php endif; ?>
            <?php if ($isAdmin || $ca('attendance/leave-settings')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/leave-settings']) ?>">Leave Settings</a>
            <?php endif; ?>
            <?php if ($isAdmin || $ca('holiday/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/holiday/index']) ?>">Holiday's Settings</a>
            <?php endif; ?>
            <?php if ($isAdmin || $ca('rfid-card/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/rfid-card/index']) ?>">Manage RFID Cards</a>
            <?php endif; ?>
        <?php else: ?>
            <!-- Developer / Client: Yii1 developer_header layout with access checks -->
            <?php if ($ca('attendance/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/index']) ?>">All Attendance</a>
            <?php endif; ?>
            <?php if ($ca('attendance/my-attendance')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/my-attendance']) ?>">My Attendance</a>
            <?php endif; ?>
            <?php if ($ca('leave/my-requests')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/my-requests']) ?>">My Leave Requests</a>
            <?php endif; ?>
            <?php if ($ca('leave/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/create']) ?>">Send Leave Requests</a>
            <?php endif; ?>
            <?php if ($ca('permission/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/permission/index']) ?>">
                <?= \common\components\UserAccess::checkSpecificMenuAccess('permission/index') ? 'Permission Requests' : 'My Permissions' ?>
            </a>
            <?php endif; ?>
            <?php if ($ca('permission/create')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/permission/create']) ?>">Request Permission</a>
            <?php endif; ?>
            <?php if ($ca('leave/index') && ($isAdmin || ($isDev && \common\components\UserAccess::checkSpecificMenuAccess('leave/index')))): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/leave/index']) ?>">
                Leave Requests <?= $pendingLeaves > 0 ? '<span class="badge bg-danger">' . $pendingLeaves . '</span>' : '' ?>
            </a>
            <?php endif; ?>
            <?php if ($ca('attendance/monthly-attendance')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/monthly-attendance']) ?>">Monthly Attendance</a>
            <?php endif; ?>
            <?php if ($ca('attendance/leave-settings')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/attendance/leave-settings']) ?>">Leave Settings</a>
            <?php endif; ?>
            <?php if ($ca('holiday/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/holiday/index']) ?>">Holiday's Settings</a>
            <?php endif; ?>
            <?php if ($ca('rfid-card/index')): ?>
            <a class="nav-link" href="<?= \yii\helpers\Url::to(['/rfid-card/index']) ?>">Manage RFID Cards</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 12. Pay Slip -->
    <?php if ($ca('payslip/index') || $ca('salary-allowance/index') || $ca('payslip-upload/index') || $ca('payslip-upload/developer')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-payroll" aria-expanded="<?= $navAria('nav-payroll') ?>">
        <span class="sidebar-icon">&#x1F4B0;</span> Pay Slip
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-payroll') ?>" id="nav-payroll">
        <?php if ($ca('payslip-upload/developer') && $user && !$user->isAdmin() && !$user->isSubAdmin()): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/payslip-upload/developer']) ?>">My Payslips</a>
        <?php endif; ?>
        <?php if ($ca('salary-allowance/deductions')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/salary-allowance/deductions']) ?>">Manage Deductions</a>
        <?php endif; ?>
        <?php if ($ca('salary-allowance/earnings-split')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/salary-allowance/earnings-split']) ?>">Earnings Split</a>
        <?php endif; ?>
        <?php if ($ca('payslip-upload/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/payslip-upload/index']) ?>">Manage Payslips</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 13. Assets -->
    <?php if ($ca('asset/index') || $ca('asset/maintenance') || $ca('asset/categories') || $ca('asset/handover')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-assets" aria-expanded="<?= $navAria('nav-assets') ?>">
        <span class="sidebar-icon"><i class="fas fa-box"></i></span> Assets
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-assets') ?>" id="nav-assets">
        <?php if ($ca('asset/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/asset/index']) ?>">Assets</a>
        <?php endif; ?>
        <?php if ($ca('asset/maintenance')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/asset/maintenance']) ?>">Assets Maintenance</a>
        <?php endif; ?>
        <?php if ($ca('asset/categories')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/asset/categories']) ?>">Assets Categories</a>
        <?php endif; ?>
        <?php if ($ca('asset/handover')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/asset/handover']) ?>">Assets Handover</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 14. IOT -->
    <?php if ($ca('iot-product/index') || $ca('iot-product/masters') || $ca('iot-product/pick-vendor')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-iot" aria-expanded="<?= $navAria('nav-iot') ?>">
        <span class="sidebar-icon"><i class="fas fa-plug"></i></span> IOT
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-iot') ?>" id="nav-iot">
        <?php if ($ca('iot-product/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/index']) ?>">Products</a>
        <?php endif; ?>
        <?php if ($ca('iot-product/masters')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/masters']) ?>">IOT Masters</a>
        <?php endif; ?>
        <?php if ($ca('iot-product/issued')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/issued']) ?>">Issued Products</a>
        <?php endif; ?>
        <?php if ($ca('iot-product/non-purchased')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/non-purchased']) ?>">Non Purchased Products</a>
        <?php endif; ?>
        <?php if ($ca('iot-product/purchased')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/purchased']) ?>">Purchased Products</a>
        <?php endif; ?>
        <?php if ($ca('iot-product/pick-vendor')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/iot-product/pick-vendor']) ?>">Add Purchased Products</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Quotation / Delivery (merged from delivery app, 2026-04-17) -->
    <?php if ($ca('quotation-dashboard/index') || $ca('client-quotation/index') || $ca('dc/index') || $ca('dc/inward-index') || $ca('delivery-product/index')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-quotation" aria-expanded="<?= $navAria('nav-quotation') ?>">
        <span class="sidebar-icon"><i class="fas fa-file-alt"></i></span> Quotation &amp; DC
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-quotation') ?>" id="nav-quotation">
        <?php if ($ca('quotation-dashboard/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/quotation-dashboard/index']) ?>">Dashboard</a>
        <?php endif; ?>
        <?php if ($ca('client-quotation/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/client-quotation/index']) ?>">Quotations</a>
        <?php endif; ?>
        <?php if ($ca('client-quotation/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/client-quotation/create']) ?>">Add Quotation</a>
        <?php endif; ?>
        <?php if ($ca('dc/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/dc/index']) ?>">Delivery Challans</a>
        <?php endif; ?>
        <?php if ($ca('dc/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/dc/create']) ?>">Add Delivery Challan</a>
        <?php endif; ?>
        <?php if ($ca('dc/inward-index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/dc/inward-index']) ?>">Inward Delivery Challans</a>
        <?php endif; ?>
        <?php if ($ca('delivery-product/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/delivery-product/index']) ?>">Products</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Purchase Order module -->
    <?php if ($ca('po-dashboard/index') || $ca('po/create') || $ca('po/index')): ?>
    <a class="nav-group-toggle" data-bs-toggle="collapse" href="#nav-po" aria-expanded="<?= $navAria('nav-po') ?>">
        <span class="sidebar-icon"><i class="fas fa-file-invoice-dollar"></i></span> Purchase Order
        <span class="arrow"><i class="fas fa-caret-right"></i></span>
    </a>
    <div class="collapse nav-sublist <?= $navOpen('nav-po') ?>" id="nav-po">
        <?php if ($ca('po-dashboard/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/po-dashboard/index']) ?>">PO Dashboard</a>
        <?php endif; ?>
        <?php if ($ca('po/create')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/po/create']) ?>">Add PO</a>
        <?php endif; ?>
        <?php if ($ca('po/index')): ?>
        <a class="nav-link" href="<?= \yii\helpers\Url::to(['/po/index']) ?>">PO Listing</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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

    <div style="height: 2rem"></div>
</nav>

<!-- ── Mobile sidebar backdrop ─────────────────────────────────────────── -->
<div id="sidebar-backdrop"></div>

<!-- ── Top bar ─────────────────────────────────────────────────────────── -->
<div id="topbar">
    <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
    <span class="topbar-title"><?= Html::encode($this->title ?? 'Maestro ERP') ?></span>

    <div class="ms-auto">
        <?php if (!Yii::$app->user->isGuest): ?>
        <!-- Check-in / Check-out button removed from header — no need there. -->

        <!-- Live attendance dashboard (admins / those with the permission) -->
        <?php if ($ca('attendance/live')): ?>
        <a href="<?= \yii\helpers\Url::to(['/attendance/live']) ?>" class="btn btn-sm btn-outline-danger position-relative d-flex align-items-center gap-1" title="Live attendance" aria-label="Live attendance">
            <span class="live-hdr-dot"></span> <span class="tb-label">Live</span>
        </a>
        <?php endif; ?>

        <!-- Badges -->
        <?php if ($pendingLeaves > 0): ?>
        <a href="<?= \yii\helpers\Url::to(['/leave/index']) ?>" class="btn btn-sm btn-outline-warning position-relative" title="Pending leave requests" aria-label="Pending leave requests">
            <i class="fas fa-plane-departure tb-icon"></i><span class="tb-label">Leaves</span> <span class="badge bg-danger"><?= $pendingLeaves ?></span>
        </a>
        <?php endif; ?>
        <?php if ($openLeads > 0): ?>
        <a href="<?= \yii\helpers\Url::to(['/lead/assigned']) ?>" class="btn btn-sm btn-outline-primary position-relative" title="Unread lead follow-ups assigned to you" aria-label="Unread lead follow-ups assigned to you">
            <i class="fas fa-bullseye tb-icon"></i><span class="tb-label">Leads</span> <span class="badge bg-danger"><?= $openLeads ?></span>
        </a>
        <?php endif; ?>

        <!-- User dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle tb-user" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false" title="<?= Html::encode($username) ?>">
                <?= Html::encode($username) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-header"><?= Html::encode($username) ?></span></li>
                <?php
                // My Profile — Yii1 parity. developer_header.php links to
                // developer/{user->developer_id}; clients/admins without a
                // developer record fall back to their User record.
                $profileDevId = $user ? $user->getDeveloperId() : null;
                if ($profileDevId) {
                    $profileUrl = ['/developer/view', 'id' => $profileDevId];
                } else {
                    $profileUrl = ['/user/view', 'id' => Yii::$app->user->id];
                }
                ?>
                <li><?= Html::a('My Profile', $profileUrl, ['class' => 'dropdown-item']) ?></li>
                <li><?= Html::a('Change Password', ['/site/change-password'], ['class' => 'dropdown-item']) ?></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <?= Html::beginForm(['/site/logout'], 'post', ['id' => 'logout-form']) ?>
                    <?= Html::submitButton('Logout', ['class' => 'dropdown-item text-danger', 'id' => 'logout-btn']) ?>
                    <?= Html::endForm() ?>
                </li>
            </ul>
        </div>
        <?php else: ?>
        <?= Html::a('Login', ['/site/login'], ['class' => 'btn btn-sm btn-primary']) ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── Main content ────────────────────────────────────────────────────── -->
<div id="main-content">
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
                <div class="mb-3">
                    <label class="form-label">Invoice Category <span class="text-danger">*</span></label>
                    <?= \kartik\select2\Select2::widget([
                        'name'          => 'modal_invoice_category',
                        'data'          => [
                            \backend\models\Invoice::CATEGORY_SERVICE => 'Service Invoice',
                            \backend\models\Invoice::CATEGORY_PRODUCT => 'Sales Invoice',
                        ],
                        'options'       => ['id' => 'modal_invoice_category', 'placeholder' => 'Select Invoice Category'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select Company <span class="text-danger">*</span></label>
                    <?= \kartik\select2\Select2::widget([
                        'name'          => 'modal_company_id',
                        'data'          => $_modalCompanyData,
                        'options'       => ['id' => 'modal_company_id', 'placeholder' => 'Select Company'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select Client <span class="text-danger">*</span></label>
                    <?= \kartik\select2\Select2::widget([
                        'name'          => 'modal_client_id',
                        'data'          => $_modalClientData,
                        'options'       => ['id' => 'modal_client_id', 'placeholder' => 'Select Client'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                    <span class="text-danger small" id="modal_invoice_error"></span>
                </div>
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
    . 'var _ajaxMilestoneValue = ' . \yii\helpers\Json::encode(\yii\helpers\Url::to(['/project/get-milestone-value'])) . ';',
    \yii\web\View::POS_END
); ?>
<?php endif; ?>

<script>
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
    jQuery('#modal_invoice_category').val(null).trigger('change');
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
    if (!project)  { Swal.fire({icon:'warning', title:'Required', text:'Please Select Project!'}); return; }

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
