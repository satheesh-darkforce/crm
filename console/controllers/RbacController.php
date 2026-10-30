<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\rbac\DbManager;

/**
 * RBAC seeder — sets up roles and permissions.
 *
 * Run: php yii rbac/init
 *
 * Migrated from Yii1: UserAccess::checkaccess() and controller accessRules()
 *
 * Roles:
 *   admin     — Full access to everything
 *   sub_admin — Admin panel, limited permissions
 *   developer — Developer portal only
 *   client    — Client portal only
 */
class RbacController extends Controller
{
    public function actionInit(): void
    {
        /** @var DbManager $auth */
        $auth = Yii::$app->authManager;
        $auth->removeAll();

        // ── Permissions ───────────────────────────────────────────────────────

        // Dashboard
        $viewDashboard = $auth->createPermission('viewDashboard');
        $viewDashboard->description = 'View dashboard';
        $auth->add($viewDashboard);

        // Employee management
        $manageEmployees = $auth->createPermission('manageEmployees');
        $manageEmployees->description = 'Create/edit/delete employees and developers';
        $auth->add($manageEmployees);

        $viewEmployees = $auth->createPermission('viewEmployees');
        $viewEmployees->description = 'View employee list';
        $auth->add($viewEmployees);

        // Project management
        $manageProjects = $auth->createPermission('manageProjects');
        $manageProjects->description = 'Create/edit/delete projects';
        $auth->add($manageProjects);

        $viewProjects = $auth->createPermission('viewProjects');
        $viewProjects->description = 'View projects';
        $auth->add($viewProjects);

        // Task management
        $manageTasks = $auth->createPermission('manageTasks');
        $manageTasks->description = 'Create/edit/delete tasks';
        $auth->add($manageTasks);

        $viewTasks = $auth->createPermission('viewTasks');
        $viewTasks->description = 'View own tasks';
        $auth->add($viewTasks);

        // Work reports
        $manageWorkReports = $auth->createPermission('manageWorkReports');
        $manageWorkReports->description = 'View and manage all work reports';
        $auth->add($manageWorkReports);

        $submitWorkReport = $auth->createPermission('submitWorkReport');
        $submitWorkReport->description = 'Submit own work reports';
        $auth->add($submitWorkReport);

        // Invoice management
        $manageInvoices = $auth->createPermission('manageInvoices');
        $manageInvoices->description = 'Create/edit/delete invoices';
        $auth->add($manageInvoices);

        $viewInvoices = $auth->createPermission('viewInvoices');
        $viewInvoices->description = 'View invoices (client-facing)';
        $auth->add($viewInvoices);

        // Attendance
        $manageAttendance = $auth->createPermission('manageAttendance');
        $manageAttendance->description = 'View and verify all attendance';
        $auth->add($manageAttendance);

        $markAttendance = $auth->createPermission('markAttendance');
        $markAttendance->description = 'Mark own attendance';
        $auth->add($markAttendance);

        // Leave
        $manageLeave = $auth->createPermission('manageLeave');
        $manageLeave->description = 'Approve/reject leave requests';
        $auth->add($manageLeave);

        $applyLeave = $auth->createPermission('applyLeave');
        $applyLeave->description = 'Apply for leave';
        $auth->add($applyLeave);

        // Payslip
        $managePayslips = $auth->createPermission('managePayslips');
        $managePayslips->description = 'Generate/upload payslips';
        $auth->add($managePayslips);

        $viewOwnPayslip = $auth->createPermission('viewOwnPayslip');
        $viewOwnPayslip->description = 'View own payslip';
        $auth->add($viewOwnPayslip);

        // CRM
        $manageCrm = $auth->createPermission('manageCrm');
        $manageCrm->description = 'Manage leads, quotations, proposals';
        $auth->add($manageCrm);

        // Assets & Inventory
        $manageAssets = $auth->createPermission('manageAssets');
        $manageAssets->description = 'Manage assets and IoT inventory';
        $auth->add($manageAssets);

        // Domains
        $manageDomains = $auth->createPermission('manageDomains');
        $manageDomains->description = 'Manage domain registrations';
        $auth->add($manageDomains);

        // Settings
        $manageSettings = $auth->createPermission('manageSettings');
        $manageSettings->description = 'Manage master settings';
        $auth->add($manageSettings);

        // API access
        $apiAccess = $auth->createPermission('apiAccess');
        $apiAccess->description = 'Access REST API endpoints';
        $auth->add($apiAccess);

        // ── Roles ─────────────────────────────────────────────────────────────

        // Developer role
        $developer = $auth->createRole('developer');
        $developer->description = 'Developer — can submit work, view tasks, mark attendance';
        $auth->add($developer);
        $auth->addChild($developer, $viewDashboard);
        $auth->addChild($developer, $viewProjects);
        $auth->addChild($developer, $viewTasks);
        $auth->addChild($developer, $submitWorkReport);
        $auth->addChild($developer, $markAttendance);
        $auth->addChild($developer, $applyLeave);
        $auth->addChild($developer, $viewOwnPayslip);
        $auth->addChild($developer, $manageCrm);
        $auth->addChild($developer, $apiAccess);

        // Client role
        $client = $auth->createRole('client');
        $client->description = 'Client — can view projects and invoices';
        $auth->add($client);
        $auth->addChild($client, $viewDashboard);
        $auth->addChild($client, $viewProjects);
        $auth->addChild($client, $viewInvoices);

        // Sub-admin role (inherits developer + more)
        $subAdmin = $auth->createRole('sub_admin');
        $subAdmin->description = 'Sub-admin — elevated access, cannot manage settings';
        $auth->add($subAdmin);
        $auth->addChild($subAdmin, $developer);
        $auth->addChild($subAdmin, $manageEmployees);
        $auth->addChild($subAdmin, $viewEmployees);
        $auth->addChild($subAdmin, $manageProjects);
        $auth->addChild($subAdmin, $manageTasks);
        $auth->addChild($subAdmin, $manageWorkReports);
        $auth->addChild($subAdmin, $manageInvoices);
        $auth->addChild($subAdmin, $manageAttendance);
        $auth->addChild($subAdmin, $manageLeave);
        $auth->addChild($subAdmin, $managePayslips);
        $auth->addChild($subAdmin, $manageAssets);
        $auth->addChild($subAdmin, $manageDomains);

        // Admin role (inherits everything)
        $admin = $auth->createRole('admin');
        $admin->description = 'Admin — full access';
        $auth->add($admin);
        $auth->addChild($admin, $subAdmin);
        $auth->addChild($admin, $manageSettings);
        $auth->addChild($admin, $viewEmployees);

        echo "RBAC roles and permissions seeded successfully.\n";
        echo "  Roles: admin, sub_admin, developer, client\n";
        echo "  Permissions: " . count($auth->getPermissions()) . "\n";
    }
}
