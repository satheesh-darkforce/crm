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

    // Document number prefixes (PREFIX/26-27/001). GST invoice numbers must stay one
    // unbroken series per financial year — do not change these mid-year.
    'numbering.quote'    => [1 => 'GDQ'],                                                      // quotations GDQ/26-27/001
    'numbering.invoice'  => ['domestic' => 'GD', 'international' => 'GDEX', 'sales' => 'GD'], // tax / proforma invoices GD/26-27/001
    'numbering.employee' => 'GD',                                                              // employee codes GD001; letters only

    // Brand shown in the sidebar, page titles and the login page.
    'app.name' => 'Green Divine',

    // Company details printed on invoices, quotations, payslips and challans.
    // gstin / email: fill in before sending documents (left empty — not on the website).
    'company' => [
        'name'      => 'Green Divine',
        'legalName' => 'Green Divine',
        'tagline'   => 'Every space has a story',
        'address'   => 'L1, Splendid Plaza, 02, 100 Feet Road, Beside Sukh Sagar Hotel, 5th Block, Koramangala, Bengaluru, Karnataka 560095',
        'city'      => 'Bengaluru',
        'state'     => 'Karnataka',
        'stateCode' => '29',
        'phone'     => '+91 97399 90899',
        'email'     => '',
        'website'   => 'www.greendivine.in',
        'gstin'     => '',
        'logo'      => 'images/logo.png',      // under backend/web
        'letterhead'=> 'images/letterhead.png', // invoice page background (logo + footer)
    ],
    // Single links hidden inside the visible groups (by route).
    'sidebar.hiddenRoutes' => [
        'quotation-dashboard/index', 'dc/index', 'dc/create', 'dc/inward-index', 'delivery-product/index', // delivery challans
        'inward-invoice/index', 'inward-invoice/create',                                                  // purchase invoices
        'quotation/index',                                                                                // old empty quotation module
        'proposal/index',                                                                                 // proposals (quotations replace them)
        'developer/create-payslip',                                                                       // payroll
        'lead/lead-master', 'lead/upload-lead',                                                         // cold-prospect list (IT sales)
        'bank-detail/create',                                                                             // same as "Add account" on Payment Bank Details
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
    'login.logo'        => 'images/logo.png',
    'login.accent'      => '#7366ff',
];
