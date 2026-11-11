<?php

return [
    'adminEmail' => 'admin@example.com',
    'bsVersion'  => '5.x',

    // Feature switches. Requires the amc / amc_renew tables — while false,
    // every amc/* route is denied and AMC UI is hidden.
    'features.amc' => true,

    // Sidebar (see HIDDEN_MODULES.md). Only the groups listed here are shown, in this
    // order; every other group stays in views/layouts/main.php and its routes keep
    // working. To bring one back, add its key (e.g. 'projects', 'amc', 'vendors').
    'sidebar.groups' => ['dashboard', 'clients', 'products', 'quotations', 'invoices', 'employees', 'hr', 'leads'],
    // The same groups under section labels (old Green Divine admin style); '' = no label.
    // When set, this decides the order — keep it in step with sidebar.groups.
    'sidebar.sections' => [
        ''          => ['dashboard'],
        'Sales'     => ['leads', 'clients', 'quotations', 'invoices'],
        'Catalogue' => ['products'],
        'Team'      => ['employees', 'hr'],
    ],

    // Brand shown in the sidebar, page titles and the login page.
    'app.name' => 'Green Divine',
    // Single links hidden inside the visible groups (by route).
    'sidebar.hiddenRoutes' => [
        'quotation-dashboard/index', 'dc/index', 'dc/create', 'dc/inward-index', 'delivery-product/index', // delivery challans
        'inward-invoice/index', 'inward-invoice/create',                                                  // purchase invoices
        'quotation/index',                                                                                // old empty quotation module
        'proposal/index',                                                                                 // proposals (quotations replace them)
        'developer/create-payslip',                                                                       // payroll
        'lead/lead-master', 'lead/upload-lead',                                                         // cold-prospect list (IT sales)
    ],

    // Green Divine mode: Leads, Quotations and Invoices show only what Green Divine
    // uses; hidden fields get server defaults (HIDDEN_MODULES.md "Simplified forms").
    // Set false to show every field again.
    'crm.simple' => true,

    // Client portal logins. While false, Add Client / Add To Client do not ask for a
    // username and password, clients are saved without a login account, and the
    // portal actions (reset password, portal permissions) are hidden and refused.
    // Set true to bring portal logins back; existing client logins are untouched.
    'client.portalLogin' => false,

    // Login page branding — override per installation in params-local.php.
    // logo: path under backend/web (e.g. 'images/client-logo.png'); leave null to show initials.
    'login.companyName' => 'Green Divine',
    'login.tagline'     => 'Artificial grass, vertical gardens & turf — CRM',
    'login.logo'        => null,
    'login.accent'      => '#7366ff',
];
