<?php
// Copy to docker/config/config.php and fill in real SMTP credentials.
// Mounted into the container as /var/www/html/includes/config.php.
// If this file is missing, the site runs in "open mode" (no email verification).

return [
    'mail_driver'    => 'smtp',
    'mail_from'      => '',
    'mail_from_name' => 'PetLove Club',
    'smtp_host'      => '',
    'smtp_port'      => 587,
    'smtp_username'  => 'no-reply',
    'smtp_password'  => '',
    'smtp_secure'    => 'tls',

    // Public base URL - used in verification / password-reset email links.
    'app_url'        => '',
];
