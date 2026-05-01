<?php
// CLI worker: drain pending rows from `mail_queue` and ship them via SMTP.
//
// Usage (from a cron job, every minute):
//   * * * * * /usr/local/bin/php /var/www/html/scripts/process_mail_queue.php >> /var/log/petlove_mail.log 2>&1
//
// Inside docker-compose:
//   docker exec petslove-app php /var/www/html/scripts/process_mail_queue.php
//
// Reads SMTP creds from includes/config.php and DB creds from the same env vars
// the web app uses (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, DB_PORT).

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';

if (!mail_enabled()) {
    fwrite(STDERR, "mail_config missing - cannot send. Aborting.\n");
    exit(1);
}

$limit = (int)(getenv('MAIL_QUEUE_BATCH') ?: 20);
$stats = process_mail_queue($pdo, $limit);

$ts = date('Y-m-d H:i:s');
echo "[{$ts}] mail_queue picked={$stats['picked']} sent={$stats['sent']} failed={$stats['failed']}\n";
