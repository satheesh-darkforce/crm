<?php

namespace backend\controllers;

use backend\models\Developer;
use backend\models\MasterSetting;
use common\components\UserAccess;
use Yii;
use yii\web\Response;

class SettingsController extends Controller
{
    // ─── General / Index ──────────────────────────────────────────────────────

    public function actionIndex(): string|Response
    {
        $model = MasterSetting::getInstance();

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->updated_by   = Yii::$app->user->id;
            $model->updated_date = date('Y-m-d H:i:s');
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'General settings saved.');
                return $this->redirect(['index']);
            }
        }

        return $this->render('index', compact('model'));
    }

    // ─── Invoice Settings ─────────────────────────────────────────────────────

    public function actionInvoice(): string|Response
    {
        $model = MasterSetting::getInstance();

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->updated_by   = Yii::$app->user->id;
            $model->updated_date = date('Y-m-d H:i:s');
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Invoice settings updated.');
                return $this->redirect(['invoice']);
            }
        }

        return $this->render('invoice', compact('model'));
    }

    // ─── Accounts Portal ──────────────────────────────────────────────────────

    /**
     * Manage which developers have access to the accounts portal app.
     * Mirrors Yii1: access_permissions_new column in developer table (JSON array).
     */
    public function actionAccountsPortal(): string|Response
    {
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        if (Yii::$app->request->isPost) {
            $post      = Yii::$app->request->post();
            $selected  = isset($post['portal_access']) ? (array)$post['portal_access'] : [];

            // Update every developer: grant or revoke portal access
            foreach ($developers as $dev) {
                $hasAccess = in_array($dev->id, $selected);
                if ($hasAccess) {
                    // Store a simple JSON array — compatible with Yii1 format
                    $dev->access_permissions_new = json_encode(['accounts_portal']);
                } else {
                    $dev->access_permissions_new = null;
                }
                $dev->save(false);
            }

            Yii::$app->session->setFlash('success', 'Accounts portal access updated.');
            return $this->redirect(['accounts-portal']);
        }

        return $this->render('accounts-portal', compact('developers'));
    }

    // ─── Access Menus ─────────────────────────────────────────────────────────

    /**
     * Manage route-level permissions for a developer.
     * Mirrors Yii1: access_permissions column (comma-separated route strings).
     */
    public function actionAccessMenus(): string|Response
    {
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $devId      = (int)Yii::$app->request->get('dev_id', 0);
        $selected   = null;

        if ($devId) {
            $selected = Developer::findOne(['id' => $devId, 'status' => 1]);
        }

        if (Yii::$app->request->isPost && $selected) {
            $routes = Yii::$app->request->post('routes', []);
            $selected->access_permissions = implode(',', array_filter($routes));
            $selected->save(false);
            Yii::$app->session->setFlash('success', 'Access permissions saved for ' . $selected->developer_name . '.');
            return $this->redirect(['access-menus', 'dev_id' => $devId]);
        }

        $allRoutes = $this->getAllAccessibleRoutes();
        if (!UserAccess::isAmcEnabled()) {
            unset($allRoutes['AMC']);
        }

        return $this->render('access-menus', compact('developers', 'selected', 'allRoutes'));
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Full list of named routes that can be granted to developers.
     * Grouped by module — sourced from Yii1 UserAccess::checkaccess() logic.
     */
    private function getAllAccessibleRoutes(): array
    {
        return [
            'Live Attendance' => [
                'attendance/live-all-data' => 'Show All Live Data',
                'attendance/live'          => 'Live Attendance',
            ],
            'CRM' => [
                'lead/index'                  => 'Manage Leads — List',
                'lead/create'                 => 'Leads — Create',
                'lead/view'                   => 'Leads — View',
                'lead/update'                 => 'Leads — Update',
                'lead/delete'                 => 'Leads — Delete',
                'lead/change-status'          => 'Leads — Change Lead Status',
                'lead/change-lead-status'     => 'Leads — Active / Inactive',
                'lead/my-leads'               => 'My Leads — List',
                'lead/assigned'               => 'Assigned Leads — List',
                'lead/converted'              => 'Converted Leads — List',
                'lead/dropped'                => 'Dropped Leads — List',
                'lead/add-to-client'          => 'Converted Leads — Add To Client',
                'lead-followers/index'        => 'Follow Ups — List',
                'lead-followers/create'       => 'Follow Ups — Create',
                'lead-followers/update'       => 'Follow Ups — Update',
                'lead-followers/view'         => 'Follow Ups — View',
                'lead-followers/view-attachments' => 'Follow Ups — Attachments',
                'lead/change-followup-status' => 'Follow Ups — Close',
                'lead/lead-master'            => 'Lead Master — List',
                'lead/lead-master-view'       => 'Lead Master — View',
                'lead/create-lead-master'     => 'Lead Master — Create',
                'lead/update-lead-master'     => 'Lead Master — Update',
                'lead/delete-lead-master'     => 'Lead Master — Delete',
                'lead/upload-lead'            => 'Lead Master — Upload',
                'lead/convert-to-lead'        => 'Lead Master — Convert to Lead',
                'client/index'       => 'Clients — List',
                'client/view'        => 'Clients — View',
            ],
            'Projects' => [
                'project/index'      => 'Projects — List',
                'project/view'       => 'Projects — View',
                'task/index'         => 'Tasks — List',
                'task/view'          => 'Tasks — View',
                'task/create'        => 'Tasks — Create',
                'task/update'        => 'Tasks — Update',
            ],
            'AMC' => [
                'amc/projects'      => 'AMC Projects — List',
                'amc/active'        => 'Active AMC — List',
                'amc/renew-list'    => 'Current Month Renew — List',
                'amc/ended'         => 'Ended AMC — List',
                'amc/view'          => 'AMC — View',
                'amc/start'         => 'AMC — Start / Create',
                'amc/update'        => 'AMC — Edit',
                'amc/renew'         => 'AMC — Renew',
                'amc/update-renew'  => 'AMC — Edit Renewal',
                'amc/end'           => 'AMC — End',
                'amc/remove'        => 'AMC — Remove from AMC',
            ],
            'Finance' => [
                'invoice/index'        => 'Invoices — List',
                'invoice/view'         => 'Invoices — View',
                'invoice/print'        => 'Invoices — Print',
                'invoice/download-pdf' => 'Invoices — Download PDF',
                'inward-invoice/index' => 'Inward Invoices — List',
                'vendor/index'         => 'Vendors — List',
                'bank-detail/index'    => 'Bank Details — List',
            ],
            'HR / Payroll' => [
                'employee/index'      => 'Employees — List',
                'payslip/index'       => 'Payslips — List',
                'payslip/view'        => 'Payslips — View',
                'attendance/index'    => 'Attendance — List',
                'leave/index'         => 'Leave Requests — List',
                'permission/index'    => 'Permission Requests — List',
                'permission/create'   => 'Permission Requests — Create',
                'permission/decide'   => 'Permission Requests — Approve/Reject',
            ],
            'Payroll Settings' => [
                'salary-allowance/deductions'     => 'Manage Deductions',
                'salary-allowance/earnings-split' => 'Earnings Split',
            ],
            'Assets' => [
                'asset/index'        => 'Assets — List',
                'asset/maintenance'  => 'Asset Maintenance',
                'asset/handover'     => 'Asset Handover',
            ],
            'Domains / IOT' => [
                'domain/index'       => 'Domains — List',
                'iot-product/index'  => 'IOT Products — List',
                'iot-product/issued' => 'IOT Issued',
            ],
            'Quotation' => [
                'quotation-dashboard/index'        => 'Quotation Dashboard',
                'client-quotation/index'           => 'Quotations — List',
                'client-quotation/create'          => 'Quotations — Create',
                'client-quotation/add-child'       => 'Quotations — Add Revision',
                'client-quotation/cancel'          => 'Quotations — Cancel',
            ],
            'DC (Delivery Challan)' => [
                'dc/index'                      => 'DC — List',
                'dc/create'                     => 'DC — Create',
                'dc/update'                     => 'DC — Update',
                'dc/cancel'                     => 'DC — Cancel',
                'dc/save-return'                => 'DC — Mark Returned',
                'delivery-product/index'        => 'DC Products — List',
                'delivery-product/create'       => 'DC Products — Create',
                'delivery-product/update'       => 'DC Products — Update',
                'delivery-product/delete'       => 'DC Products — Delete',
            ],
            'Inward DC' => [
                'dc/inward-index'   => 'Inward DC — List',
                'dc/create-inward'  => 'Inward DC — Create',
                'dc/inward-view'    => 'Inward DC — View',
                'dc/add-return'     => 'Inward DC — Add Return',
                'dc/update-return'  => 'Inward DC — Edit Return',
            ],
            'Purchase Order' => [
                'po-dashboard/index'   => 'PO Dashboard',
                'po/create'            => 'Add PO',
                'po/index'             => 'PO Listing',
            ],
        ];
    }
}
