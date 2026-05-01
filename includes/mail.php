<?php
// Email helpers. Loads SMTP credentials from includes/config.php.
// If that file is absent, mail_enabled() returns false and the site runs
// without email verification.
//
// Outgoing mail goes through the `mail_queue` table: HTTP requests INSERT
// rows via queue_*() helpers, and a CLI cron worker (scripts/process_mail_queue.php)
// picks them up and talks to the SMTP server. This keeps the SMTP round-trip
// out of the request path, which matters because the production server
// can't reach our SMTP host from inside a web request but can from cron.

function mail_config(): ?array {
    static $cfg = false;
    if ($cfg !== false) return $cfg;
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) { $cfg = null; return $cfg; }
    $loaded = require $path;
    if (!is_array($loaded)) { $cfg = null; return $cfg; }
    $required = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'mail_from'];
    foreach ($required as $k) {
        if (empty($loaded[$k])) { $cfg = null; return $cfg; }
    }
    if (empty($loaded['app_url'])) {
        $envUrl = getenv('APP_URL');
        if (is_string($envUrl) && $envUrl !== '') $loaded['app_url'] = $envUrl;
    }
    $cfg = $loaded;
    return $cfg;
}

function mail_enabled(): bool {
    return mail_config() !== null;
}

function generate_verification_code(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generate_reset_token(): string {
    return bin2hex(random_bytes(32));
}

function mail_build(): ?\PHPMailer\PHPMailer\PHPMailer {
    $cfg = mail_config();
    if (!$cfg) return null;

    require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
    require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $cfg['smtp_host'];
    $mail->Port       = (int)$cfg['smtp_port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['smtp_username'];
    $mail->Password   = $cfg['smtp_password'];
    $mail->Timeout    = 15;
    if (!empty($cfg['smtp_debug'])) {
        $mail->SMTPDebug   = (int)$cfg['smtp_debug'];
        $mail->Debugoutput = function ($str, $level) {
            error_log('PHPMailer[' . $level . ']: ' . trim((string)$str));
        };
    }
    $secure = strtolower((string)($cfg['smtp_secure'] ?? 'tls'));
    if ($secure === 'ssl') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($secure === '' || $secure === 'none') {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
    } else {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'quoted-printable';
    $mail->setFrom($cfg['mail_from'], $cfg['mail_from_name'] ?? 'PetLove Club');
    return $mail;
}

function site_base_url(): string {
    $cfg = mail_config();
    if ($cfg && !empty($cfg['app_url'])) {
        return rtrim((string)$cfg['app_url'], '/');
    }
    $envUrl = getenv('APP_URL');
    if (is_string($envUrl) && $envUrl !== '') {
        return rtrim($envUrl, '/');
    }
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
    }
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (preg_match('#/(admin|edit)$#', $dir)) {
        $dir = preg_replace('#/(admin|edit)$#', '', $dir);
    }
    return $proto . '://' . $host . $dir;
}

function build_verification_message(string $username, string $code): array {
    $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $html = <<<HTML
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#fff7ed;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff7ed;padding:40px 16px;">
  <tr><td align="center">
    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.05);">
      <tr><td style="background:#e11d48;padding:28px 40px;text-align:center;">
        <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:900;letter-spacing:0.5px;">PetLove Club</h1>
        <p style="margin:6px 0 0;color:#ffe4e6;font-size:13px;">Сообщество любителей домашних животных</p>
      </td></tr>
      <tr><td style="padding:36px 40px 24px;">
        <p style="margin:0 0 16px;font-size:18px;font-weight:700;">Здравствуйте, {$safeUser}!</p>
        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#4b5563;">Спасибо, что присоединились к PetLove Club. Чтобы завершить регистрацию и получить полный доступ к функциям сайта, подтвердите ваш email — введите код ниже на странице подтверждения.</p>
        <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;text-align:center;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">Ваш код подтверждения</p>
        <div style="font-size:32px;font-weight:900;letter-spacing:0.4em;text-align:center;background:#fff1f2;color:#e11d48;border:2px dashed #fecdd3;border-radius:16px;padding:22px 16px;margin:0 0 24px;font-family:'Courier New',monospace;">{$code}</div>
        <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#4b5563;">Код действителен <b>30 минут</b> с момента отправки.</p>
        <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#4b5563;">Если вы не регистрировались на PetLove Club, просто проигнорируйте это письмо — никаких действий с вашим email не произойдёт.</p>
      </td></tr>
      <tr><td style="border-top:1px solid #f3f4f6;padding:20px 40px;background:#fafafa;text-align:center;">
        <p style="margin:0 0 6px;font-size:12px;color:#9ca3af;">С любовью к питомцам,<br><b style="color:#e11d48;">команда PetLove Club</b></p>
        <p style="margin:8px 0 0;font-size:11px;color:#d1d5db;">Это автоматическое письмо. Пожалуйста, не отвечайте на него.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;
    $text = "PetLove Club\r\n"
        . "===========\r\n\r\n"
        . "Здравствуйте, {$username}!\r\n\r\n"
        . "Спасибо за регистрацию в PetLove Club. Чтобы подтвердить email,\r\n"
        . "введите следующий 6-значный код на странице подтверждения:\r\n\r\n"
        . "    {$code}\r\n\r\n"
        . "Код действителен 30 минут с момента отправки.\r\n\r\n"
        . "Если вы не регистрировались на PetLove Club, просто проигнорируйте\r\n"
        . "это письмо - никаких действий с вашим email не произойдёт.\r\n\r\n"
        . "--\r\n"
        . "С любовью к питомцам, команда PetLove Club\r\n";
    return [
        'subject' => 'Код подтверждения PetLove Club: ' . $code,
        'html'    => $html,
        'text'    => $text,
    ];
}

function build_password_reset_message(string $username, string $token): array {
    $url = site_base_url() . '/reset.php?token=' . urlencode($token);
    $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeUrl  = htmlspecialchars($url,      ENT_QUOTES, 'UTF-8');
    $html = <<<HTML
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#fff7ed;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff7ed;padding:40px 16px;">
  <tr><td align="center">
    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.05);">
      <tr><td style="background:#e11d48;padding:28px 40px;text-align:center;">
        <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:900;letter-spacing:0.5px;">PetLove Club</h1>
        <p style="margin:6px 0 0;color:#ffe4e6;font-size:13px;">Сообщество любителей домашних животных</p>
      </td></tr>
      <tr><td style="padding:36px 40px 24px;">
        <p style="margin:0 0 16px;font-size:18px;font-weight:700;">Здравствуйте, {$safeUser}!</p>
        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#4b5563;">Кто-то (надеемся, что вы) запросил сброс пароля для вашего аккаунта PetLove Club. Если это были вы — нажмите на кнопку ниже, чтобы задать новый пароль.</p>
        <p style="text-align:center;margin:0 0 28px;"><a href="{$safeUrl}" style="display:inline-block;background:#e11d48;color:#ffffff;font-weight:900;padding:16px 36px;border-radius:14px;text-decoration:none;font-size:15px;letter-spacing:0.3px;">Сбросить пароль</a></p>
        <p style="margin:0 0 8px;font-size:13px;color:#6b7280;line-height:1.5;">Если кнопка не работает, скопируйте эту ссылку в браузер:</p>
        <p style="margin:0 0 24px;font-size:12px;color:#9ca3af;word-break:break-all;background:#f9fafb;border:1px solid #f3f4f6;border-radius:8px;padding:10px 12px;font-family:'Courier New',monospace;"><a href="{$safeUrl}" style="color:#e11d48;text-decoration:none;">{$safeUrl}</a></p>
        <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#4b5563;">Ссылка действительна <b>1 час</b>.</p>
        <p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">Если вы не запрашивали сброс — просто проигнорируйте письмо. Ваш пароль останется прежним.</p>
      </td></tr>
      <tr><td style="border-top:1px solid #f3f4f6;padding:20px 40px;background:#fafafa;text-align:center;">
        <p style="margin:0 0 6px;font-size:12px;color:#9ca3af;">С любовью к питомцам,<br><b style="color:#e11d48;">команда PetLove Club</b></p>
        <p style="margin:8px 0 0;font-size:11px;color:#d1d5db;">Это автоматическое письмо. Пожалуйста, не отвечайте на него.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;
    $text = "PetLove Club - Сброс пароля\r\n"
        . "===========================\r\n\r\n"
        . "Здравствуйте, {$username}!\r\n\r\n"
        . "Кто-то запросил сброс пароля для вашего аккаунта PetLove Club.\r\n"
        . "Чтобы задать новый пароль, перейдите по ссылке:\r\n\r\n"
        . "    {$url}\r\n\r\n"
        . "Ссылка действительна 1 час.\r\n\r\n"
        . "Если вы не запрашивали сброс - просто проигнорируйте это письмо.\r\n"
        . "Ваш пароль останется прежним.\r\n\r\n"
        . "--\r\n"
        . "С любовью к питомцам, команда PetLove Club\r\n";
    return [
        'subject' => 'Сброс пароля PetLove Club',
        'html'    => $html,
        'text'    => $text,
    ];
}

function queue_mail(PDO $pdo, string $toEmail, ?string $toName, string $subject, string $html, string $text): bool {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO mail_queue (to_email, to_name, subject, body_html, body_text)
             VALUES (:e, :n, :s, :h, :t)"
        );
        $stmt->execute([
            ':e' => $toEmail,
            ':n' => $toName !== null && $toName !== '' ? $toName : null,
            ':s' => $subject,
            ':h' => $html,
            ':t' => $text,
        ]);
        return true;
    } catch (\Throwable $e) {
        error_log('PetLove queue_mail failed: ' . $e->getMessage() . ' | to=' . $toEmail);
        return false;
    }
}

function cancel_pending_mails(PDO $pdo, string $email, string $subjectLike): void {
    // A new code/link makes any older pending email obsolete - mark unsent
    // rows as failed so the worker skips them.
    $st = $pdo->prepare(
        "UPDATE mail_queue
            SET failed_at = NOW(), error = 'superseded by newer mail'
          WHERE sent_at IS NULL AND failed_at IS NULL
            AND to_email = :e
            AND subject LIKE :s"
    );
    $st->execute([':e' => $email, ':s' => $subjectLike]);
}

function queue_verification_code(PDO $pdo, string $email, string $username, string $code): bool {
    if (!mail_enabled()) return false;
    cancel_pending_mails($pdo, $email, 'Код подтверждения PetLove Club:%');
    $msg = build_verification_message($username, $code);
    return queue_mail($pdo, $email, $username, $msg['subject'], $msg['html'], $msg['text']);
}

function queue_password_reset_link(PDO $pdo, string $email, string $username, string $token): bool {
    if (!mail_enabled()) return false;
    cancel_pending_mails($pdo, $email, 'Сброс пароля PetLove Club');
    $msg = build_password_reset_message($username, $token);
    return queue_mail($pdo, $email, $username, $msg['subject'], $msg['html'], $msg['text']);
}

// Worker: pick up unsent rows, send via SMTP, stamp the result.
// Called from scripts/process_mail_queue.php on a cron schedule.
function process_mail_queue(PDO $pdo, int $limit = 20, int $maxAttempts = 5): array {
    $stats = ['picked' => 0, 'sent' => 0, 'failed' => 0];
    if (!mail_enabled()) return $stats;

    $sel = $pdo->prepare(
        "SELECT id, to_email, to_name, subject, body_html, body_text, attempts
           FROM mail_queue
          WHERE sent_at IS NULL AND failed_at IS NULL AND attempts < :ma
          ORDER BY id ASC
          LIMIT {$limit}"
    );
    $sel->bindValue(':ma', $maxAttempts, PDO::PARAM_INT);
    $sel->execute();
    $rows = $sel->fetchAll();
    $stats['picked'] = count($rows);
    if (!$rows) return $stats;

    $markSent   = $pdo->prepare("UPDATE mail_queue SET sent_at = NOW(), attempts = attempts + 1, error = NULL WHERE id = :id");
    $markFailed = $pdo->prepare("UPDATE mail_queue SET failed_at = NOW(), attempts = attempts + 1, error = :err WHERE id = :id");
    $markRetry  = $pdo->prepare("UPDATE mail_queue SET attempts = attempts + 1, error = :err WHERE id = :id");

    foreach ($rows as $row) {
        $mail = mail_build();
        if (!$mail) {
            $markFailed->execute([':id' => $row['id'], ':err' => 'mail_config missing']);
            $stats['failed']++;
            continue;
        }
        try {
            $mail->addAddress($row['to_email'], (string)($row['to_name'] ?? ''));
            $mail->Subject = $row['subject'];
            $mail->isHTML(true);
            $mail->Body    = $row['body_html'];
            $mail->AltBody = (string)($row['body_text'] ?? '');
            $mail->send();
            $markSent->execute([':id' => $row['id']]);
            $stats['sent']++;
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            $nextAttempts = (int)$row['attempts'] + 1;
            if ($nextAttempts >= $maxAttempts) {
                $markFailed->execute([':id' => $row['id'], ':err' => $err]);
                $stats['failed']++;
            } else {
                $markRetry->execute([':id' => $row['id'], ':err' => $err]);
            }
            error_log('PetLove mail_queue send failed (id=' . $row['id'] . ' to=' . $row['to_email'] . '): ' . $err);
        }
    }
    return $stats;
}
