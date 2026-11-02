<?php

return [
    'adminEmail' => 'admin@example.com',
    'bsVersion'  => '5.x',

    // Feature switches. Requires the amc / amc_renew tables — while false,
    // every amc/* route is denied and AMC UI is hidden.
    'features.amc' => true,

    // Login page branding — override per installation in params-local.php.
    // logo: path under backend/web (e.g. 'images/client-logo.png'); leave null to show initials.
    'login.companyName' => 'Your Company',
    'login.tagline'     => 'Business operations, all in one place.',
    'login.logo'        => null,
    'login.accent'      => '#0d9488',
];
