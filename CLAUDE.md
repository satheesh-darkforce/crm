# Maestro ERP — Claude Code Context

## Project Overview
This is a **Yii2 Advanced Template** ERP system migrated from Yii1.
The Yii1 source is preserved at `../yii1/maestro_erp/` — always reference it when implementing missing features.

- **Backend app**: `backend/` — single unified app for ALL user roles (admin, sub_admin, developer, client)
- **Access control**: `common/components/UserAccess::checkAccess('controller/action')` — mirrors Yii1 architecture
- **Models**: `backend/models/` — all models live in the backend app (the `frontend/` app and the shared `common/models/` were removed; `common/` now holds only components, config, widgets, fixtures, tests)
- **Database**: MySQL, `maestro_erp`, `192.168.1.110:3306`, user `maestro_erp`
- **Timezone**: Asia/Kolkata

## Architecture Rules — Access Control (matches Yii1)
- All backend controllers extend `backend\controllers\Controller`
- `Controller.php` uses `UserAccess::checkAccess()` per route to control access
- **Admin (usertype=1)**: full access to everything
- **Sub-admin (usertype=4)**: full access (same as admin for now)
- **Developer (usertype=2)**: default permissions (hardcoded list in UserAccess) + custom from `developer.access_permissions` (comma-separated routes)
- **Client (usertype=3)**: custom permissions from `client.access_permissions` (comma-separated routes)
- Sidebar menu items use `UserAccess::checkAccess()` — only visible routes are shown
- Login (`/site/login`) accepts ALL roles — same dashboard, same controllers, filtered by permissions
- Permission route format: `controller-id/action-id` (Yii2 style, e.g. `task/index`, `work-report/create`)
- `payment_status=0` filter: use `!== null && !== ''` check — PHP falsy skips value "0"
- URL routing: `enablePrettyUrl=true`, no custom rules — uses Yii2 convention `/controller/action`
- All soft-deletes use `status=0` (never hard delete rows)

## UI Conventions
- **Bootstrap 5** throughout — `yii\bootstrap5\Html`, `yii\bootstrap5\ActiveForm`
- **List tables**: always include the `.list-table` CSS style block (see any existing index.php)
- **Action menus**: use Bootstrap dropdown with `⋮` button (`&#8942;`) — see `invoice/index.php` pattern
- **Form inputs**: always add `form-control-sm` or `form-select-sm` class
- **Sidebar**: `backend/views/layouts/main.php` — edit here to add new nav items
- **Select2**: Use `kartik\select2\Select2` widget for ALL dropdowns with more than 5 options OR data from DB — both inside ActiveForm and in plain filter forms. Never use CDN or plain `<select>` for such cases. Always set `'pluginOptions' => ['allowClear' => true, 'width' => '100%']` so it fills its column correctly.
- **Date picker**: Use `kartik\date\DatePicker` widget for ALL date inputs everywhere — both inside ActiveForm and in plain filter forms. Never use `<input type="date">` or CDN date pickers.
  - Always use `type => DatePicker::TYPE_INPUT`, `format => 'yyyy-mm-dd'`, `autoclose => true`

## Database Connection
```
DSN: mysql:host=<db-host>;dbname=maestro_erp
User: <db-user>
Password: <see common/config/main-local.php>
```
Config files: `common/config/main-local.php` (DB), `common/config/main.php` (shared components)
Credentials live only in the git-ignored `*-local.php` files — never commit them.

## Key Models & Tables

| Model | Table | Notes |
|-------|-------|-------|
| User | users | Auth, roles: admin/sub_admin |
| ClientModel | client | client_name field |
| Invoice | invoice | Types: 1=Tax, 2=Proforma, 3=Credit Note; Status: 0=Cancelled, 1=Active |
| InvoiceDetails | invoice_details | quantity (NOT qty) |
| InvoiceReceipt | invoice_receipt | payment_reference (NOT reference_no) |
| InwardInvoice | inward_invoice | Purchase invoices from vendors |
| InwardInvoiceDetails | inward_invoice_details | product, qty, price_per_qty, item_total |
| Vendor | vendors | company_name, gst_no |
| BankDetail | bank_details | acc_name, acc_no, ifsc, is_default |
| Domain | domains | domain_status: 1=Live,2=Expired,3=Need Follow,4=Pending Payment,5=Pending Renewal |
| DomainRenewalHistory | domain_renewal_history | |
| Asset | assets | warranty/amc fields, status |
| AssetBrand | assets_brand | brand_name |
| AssetCategory | assets_category | name |
| AssetSubcategory | assets_subcategory | name, category_id |
| AssetLocation | assets_loc | loc (field name) |
| AssetHandover | assets_handover | dev_id FK to developers table |
| AssetMaintenance | assets_maintenance | service_provider, service_cost |
| IotProduct | iot_product | |
| IotNonPurchaseProducts | iot_non_purchase_products | product_id (int FK) NOT product_name |
| IotConsumption | iot_consumption | |

## Implemented Controllers (backend/controllers/)
- SiteController — login, logout, dashboard
- UserController — user management
- EmployeeController — employee CRUD
- DeveloperController — developer list, ex-employees, create-payslip
- PayslipController — payroll management
- PayslipUploadController — bulk payslip upload
- SalaryAllowanceController — allowances + deductions
- AttendanceController — daily/monthly attendance, leave settings, RFID verify
- LeaveController — leave requests
- HolidayController — holiday calendar
- RfidCardController — RFID card management + assignment
- LeadController — CRM leads (all, converted, dropped, my-leads, upload, followers)
- ClientController — client management + access permissions
- ProposalController — sales proposals with file attachment
- QuotationController — quotations with line items + detail attachments
- ProjectController — projects
- TaskController — tasks (all, my tasks, assigned, completed, history)
- AdditionalWorksController — extra developer work hours
- WorkReportController — work reports (multiple sub-views)
- PmTaskController — PM tool tasks
- ProjectManagementController — PM projects
- PmQuotationController — PM quotations
- IotProductController — IOT products, issued, purchased, non-purchased, masters
- DomainController — domains with inline status AJAX, renewal history
- AssetController — assets CRUD + maintenance + handover + categories/masters AJAX
- InvoiceController — invoices (Tax/Proforma/Credit Note), add-receipt, cancel, monthly-report
- VendorController — vendor CRUD
- InwardInvoiceController — purchase invoice CRUD with line items
- BankDetailController — bank accounts, set-default
- SettingsController — master settings, invoice settings, accounts portal, access menus

## What Remains
All modules are fully implemented. Known minor gaps (all low priority):
- `actionAutocompleteClient` (ClientController) — not needed; Select2 dropdown handles search
- `actionPaymentPreview` (InvoiceController) — Yii1 rendered a partial view; N/A in Yii2
- `actionDeleteAttachment` (InwardInvoiceController) — no attachment table in Yii2 yet
- The Yii1 path for Yii2 source is `/var/www/html/projects/maestroerp/maestro_erp/protected/` (NOT `../yii1/`)

## Time Units (Important)
- `work_report.total_time` is stored in **minutes** (NOT seconds)
- `task.total_time` and `task.expected_time` are stored in **seconds**
- Format minutes: `sprintf('%02d:%02d', intdiv($m, 60), $m % 60)`
- Format seconds: `sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60))`

## How to Run
Apache serves `backend/web/` at: `http://maestro.iat.com/erp/maestro_erp_yii2/backend/web/`

## When Adding a New Module
1. Create model in `backend/models/` (namespace `backend\models`)
2. Create controller in `backend/controllers/` extending `Controller`
3. Create view directory `backend/views/<controller-id>/`
4. Add nav link in `backend/views/layouts/main.php` wrapped with `$ca('controller-id/action-id')`
5. Add routes to `UserAccess::getDeveloperDefaultAccess()` if developers need access by default
6. For client access, add routes to the client's `access_permissions` field in the DB
