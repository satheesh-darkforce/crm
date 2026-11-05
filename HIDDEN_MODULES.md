# Hidden Modules

Phase 2 of `new-feature.md`: the sidebar shows only **Dashboard + the 7 CRM modules**. Everything else is *hidden from the menu only* — controllers, models, views, tables, routes and permissions are untouched, and every page still opens by URL for users who have access.

## How it works

`backend/views/layouts/main.php` captures each sidebar group into a named block, and `backend/config/params.php` decides which blocks are printed and in what order:

```php
// Only these groups are shown, in this order.
'sidebar.groups' => ['dashboard', 'clients', 'products', 'quotations', 'invoices', 'employees', 'hr', 'leads'],

// Single links hidden inside visible groups (by route).
'sidebar.hiddenRoutes' => [
    'quotation-dashboard/index', 'dc/index', 'dc/create', 'dc/inward-index', 'delivery-product/index',
    'inward-invoice/index', 'inward-invoice/create',
    'quotation/index',
    'proposal/index',
    'developer/create-payslip',
],
```

**To bring a module back:** add its key to `sidebar.groups` (position = menu order). **To bring back a single link:** remove its route from `sidebar.hiddenRoutes`. A per-server override can go in `backend/config/params-local.php`. No code change is needed, except for the four groups marked *commented out* below.

## What is visible

| Menu | Key | Contents |
|---|---|---|
| Dashboard | `dashboard` | `site/index` |
| Clients | `clients` | Clients, Add New Client |
| Products | `products` | Products & Services, Add Product, Categories |
| Quotations (was "Quotation & DC") | `quotations` | Quotations, Add Quotation |
| Invoices | `invoices` | Invoices, Add Invoice, Proforma Invoices, Add Proforma, Monthly Report, Payment Bank Details, Add Bank Detail, Invoice Settings |
| Employees | `employees` | Employees, Add New Employee, Ex-Employees |
| HR (was "Attendance"; developers still see "My Attendance") | `hr` | Attendance, Live Attendance, Leave requests, Permission requests, Monthly Attendance, Leave Settings, Holidays, RFID Cards |
| Leads | `leads` | Manage Leads, My Leads, Add New Lead, Assigned / Converted / Dropped Leads, Lead Master, Upload Lead |

## Hidden groups (whole menu)

| Menu | Key | Sub-menu items → routes | Controllers | Status | Dependencies / notes |
|---|---|---|---|---|---|
| Projects | `projects` | Projects `project/index`, Add New Project `project/create` | ProjectController | Working (redesigned, module 3) | Invoices link to projects/phases/milestones (`invoice.project_id`); work reports and tasks are per project. Client role's fallback landing page is `project/client-projects` |
| AMC | `amc` | AMC Projects `amc/projects`, Active AMC `amc/active`, Current Month Renew `amc/renew-list`, Ended AMC `amc/ended` | AmcController | Working (module 3) | Needs Projects; switch `features.amc` in params |
| Work Reports | `workreports` | Add work report, My Work Reports, All Projects `project/all-project`, Daily, Yesterday, Employee monthly, Employee cumulative, Project monthly, Client cumulative, Monthly, Overall, Performance (`work-report/*`) | WorkReportController | Working (module 4) | Needs Projects, Tasks, Employees |
| Tasks | `tasks` | My Tasks `task/index`, Add Task `task/create`, My New Tasks, Completed, Assigned (`task/*`) | TaskController | Working (module 4) | Needs Projects; a running task blocks adding work reports |
| Domains | `domains` | Domains `domain/index`, Add Domain `domain/create` | DomainController | Working (module 8) | Linked to Clients |
| Vendors | `vendors` | Vendors `vendor/index`, Add Vendor `vendor/create` | VendorController | Working | Used by Inward Invoices, Assets, IoT purchases |
| Pay Slip | `payslip` | My Payslips `payslip-upload/developer`, Manage Deductions `salary-allowance/deductions`, Earnings Split `salary-allowance/earnings-split`, Manage Payslips `payslip-upload/index` | PayslipController, PayslipUploadController, SalaryAllowanceController | Working (module 7) | Uses Employees + Attendance (LOP). Candidate for HR later |
| Assets | `assets` | Assets, Maintenance, Categories, Handover (`asset/*`) | AssetController | Working (module 8) | Uses Vendors, Employees |
| IOT | `iot` | Products, IOT Masters, Issued, Non Purchased, Purchased, Add Purchased (`iot-product/*`) | IotProductController | Working (module 8) | Uses Vendors, Inward Invoices, Projects. Separate from the CRM product catalogue |
| Purchase Order | `po` | PO Dashboard `po-dashboard/index`, Add PO `po/create`, PO Listing `po/index` | PoController, PoDashboardController | Working (module 6) | Linked to Client Quotations |

## Hidden single links (inside visible groups)

| Group | Link | Route | Why hidden |
|---|---|---|---|
| Quotations | Dashboard | `quotation-dashboard/index` | Quotation + delivery-challan overview; DC is out of scope |
| Quotations | Delivery Challans, Add Delivery Challan | `dc/index`, `dc/create` | Delivery challans not in the 7 modules |
| Quotations | Inward Delivery Challans | `dc/inward-index` | Same |
| Quotations | Products (DC item list) | `delivery-product/index` | Replaced by **Products** (same table `delivery_product_details`) |
| Invoices | Inward Invoices, Add Inward Invoices | `inward-invoice/index`, `inward-invoice/create` | Purchase-side invoices (vendors) are not part of Invoice Management |
| Invoices | Quotations (old module) | `quotation/index` | Old empty `quotations` table; real quotations are under Quotations |
| Leads | Proposals | `proposal/index` | Quotations replace proposals in the CRM flow |
| Employees | Create Payslip | `developer/create-payslip` | Payroll (see Pay Slip) |

## Groups already commented out before this phase

These were already disabled in `main.php` as `<?php /* … hidden for now, use later … */ ?>`. To show one, remove the comment markers around its block.

| Menu | Routes | Note |
|---|---|---|
| PM Tool | `pm-task/index`, `pm-quotation/index`, `project-management/index` | |
| User Management | `user/index` | Admins can still open `/user/index` directly |
| Settings | `settings/index`, `settings/accounts-portal`, `settings/access-menus` | Admins can still open these directly. **Access Menus is where employee/client permissions are granted** |
| Additional Works | `additional-works/index`, `additional-works/create` | |

## Other effects

* **Pages still work by URL** for anyone who has the permission; only the menu entry is gone. Checked for project, dc, inward-invoice, vendor, asset, iot-product and po (all 200 for admin).
* **Client landing page:** clients now land on *My Invoices* (`invoice/client-invoice`) when they have that permission, otherwise on `project/client-projects` as before (`SiteController`).
* **Dashboard widgets** may still show figures from hidden modules (projects, tasks, attendance); the CRM dashboard is rebuilt in a later phase (`PROJECT_ANALYSIS.md` §8).
* **Permissions are unchanged.** Hiding a menu does not revoke access; to block a module, remove the routes from the user's permissions as before.
