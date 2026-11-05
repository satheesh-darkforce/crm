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
    // Single links hidden inside the visible groups (by route).
    'sidebar.hiddenRoutes' => [
        'quotation-dashboard/index', 'dc/index', 'dc/create', 'dc/inward-index', 'delivery-product/index', // delivery challans
        'inward-invoice/index', 'inward-invoice/create',                                                  // purchase invoices
        'quotation/index',                                                                                // old empty quotation module
        'proposal/index',                                                                                 // proposals (quotations replace them)
        'developer/create-payslip',                                                                       // payroll
    ],

    // Login page branding — override per installation in params-local.php.
    // logo: path under backend/web (e.g. 'images/client-logo.png'); leave null to show initials.
    'login.companyName' => 'Your Company',
    'login.tagline'     => 'Business operations, all in one place.',
    'login.logo'        => null,
    'login.accent'      => '#0d9488',
];
