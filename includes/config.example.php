<?php
// Copy this file to `config.php` (same folder) and fill in real SMTP credentials
// to enable email-based verification. If `config.php` is absent, the site runs
// in "open mode": registration works without email confirmation and no user
// is restricted by missing verification.

return [
    'mail_driver'    => 'smtp',
    'mail_from'      => '',
    'mail_from_name' => 'PetLove Club',
    'smtp_host'      => '',
    'smtp_port'      => 587,
    'smtp_username'  => 'no-reply',
    'smtp_password'  => 'your_password',
    'smtp_secure'    => 'tls',

    // Public base URL of the site - used in email links (verification, password reset).
    // If left empty, falls back to the APP_URL env var, then to detected $_SERVER vars.
    // Set this to the canonical https URL when running behind a reverse proxy.
    'app_url'        => '',
];
