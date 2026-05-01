<?php
require_once 'includes/auth.php';

$user = current_user();
if (!$user) {
    header('Location: login.php');
    exit;
}

// If verification isn't required (no SMTP config) or already verified - just go home.
if (!mail_enabled() || !empty($user['email_verified_at'])) {
    header('Location: profile.php?u=' . urlencode($user['username']));
    exit;
}

$errors = [];
$flash  = null;
$resendCooldownSec = 60;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'verify') {
        $code = preg_replace('/\D+/', '', (string)($_POST['code'] ?? ''));
        if (strlen($code) !== 6) {
            $errors[] = 'Введите 6-значный код из письма.';
        } else {
            $st = $pdo->prepare(
                "SELECT email_verification_code, email_verification_expires_at
                   FROM users WHERE id = :id"
            );
            $st->execute([':id' => $user['id']]);
            $row = $st->fetch();
            if (!$row || empty($row['email_verification_code'])) {
                $errors[] = 'Код не найден. Запросите новый.';
            } elseif (!empty($row['email_verification_expires_at']) && strtotime($row['email_verification_expires_at']) < time()) {
                $errors[] = 'Срок действия кода истёк. Запросите новый.';
            } elseif (!hash_equals((string)$row['email_verification_code'], $code)) {
                error_log(sprintf(
                    'PetLove verify mismatch: user_id=%d submitted=%s stored=%s submitted_len=%d stored_len=%d submitted_hex=%s stored_hex=%s',
                    (int)$user['id'],
                    $code,
                    (string)$row['email_verification_code'],
                    strlen($code),
                    strlen((string)$row['email_verification_code']),
                    bin2hex($code),
                    bin2hex((string)$row['email_verification_code'])
                ));
                $errors[] = 'Неверный код.';
            } else {
                $upd = $pdo->prepare(
                    "UPDATE users
                        SET email_verified_at = NOW(),
                            email_verification_code = NULL,
                            email_verification_expires_at = NULL
                      WHERE id = :id"
                );
                $upd->execute([':id' => $user['id']]);
                header('Location: profile.php?u=' . urlencode($user['username']));
                exit;
            }
        }
    } elseif ($action === 'resend') {
        $st = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, email_verification_sent_at, NOW()) AS s FROM users WHERE id = :id");
        $st->execute([':id' => $user['id']]);
        $elapsed = $st->fetchColumn();
        $elapsed = $elapsed === null ? PHP_INT_MAX : (int)$elapsed;
        if ($elapsed < $resendCooldownSec) {
            $errors[] = 'Слишком часто. Подождите ' . ($resendCooldownSec - $elapsed) . ' секунд и повторите.';
        } else {
            $code = generate_verification_code();
            $upd = $pdo->prepare(
                "UPDATE users
                    SET email_verification_code = :c,
                        email_verification_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                        email_verification_sent_at = NOW()
                  WHERE id = :id"
            );
            $upd->execute([':c' => $code, ':id' => $user['id']]);
            $ok = queue_verification_code($pdo, $user['email'], $user['username'], $code);
            if ($ok) {
                $flash = 'Новый код будет отправлен на ' . $user['email'] . ' в течение минуты.';
            } else {
                $errors[] = 'Не удалось поставить письмо в очередь. Попробуйте позже.';
            }
        }
    }
}

// Re-read elapsed seconds so the cooldown timer is fresh and timezone-safe.
$st = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, email_verification_sent_at, NOW()) AS s FROM users WHERE id = :id");
$st->execute([':id' => $user['id']]);
$elapsed = $st->fetchColumn();
$elapsed = $elapsed === null ? PHP_INT_MAX : (int)$elapsed;
$cooldownLeft = max(0, $resendCooldownSec - $elapsed);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение email - PetLove Club</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="./styles/tailwind.css">
    <link rel="shortcut icon" href="./favicon.png" type="image/x-icon">
</head>
<body class="bg-amber-50 min-h-screen">

<?php $active = ''; include 'includes/nav.php'; ?>

<section class="pt-32 pb-20 px-4">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-3xl shadow-xl p-8">
            <div class="flex items-center justify-center mb-6">
                <div class="w-14 h-14 bg-rose-400 rounded-2xl flex items-center justify-center">
                    <i data-lucide="mail-check" class="w-7 h-7 text-white"></i>
                </div>
            </div>

            <h1 class="text-2xl font-black text-center text-gray-900 mb-2">Подтвердите email</h1>
            <p class="text-center text-sm text-gray-600 mb-6">
                Мы отправили 6-значный код на <b><?= htmlspecialchars($user['email']) ?></b>.
                Введите его, чтобы активировать аккаунт.
            </p>

            <?php if ($errors): ?>
                <div class="mb-5 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                    <?php foreach ($errors as $e): ?>
                        <p class="text-rose-600 text-sm font-medium"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-xl">
                    <p class="text-green-700 text-sm font-medium"><?= htmlspecialchars($flash) ?></p>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="verify">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Код подтверждения</label>
                    <input type="text" name="code" required inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code"
                           class="w-full px-4 py-3 text-center text-2xl tracking-[0.4em] font-black border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors"
                           placeholder="••••••">
                </div>
                <button type="submit" class="btn-hover w-full px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500 transition-all duration-300">
                    Подтвердить
                </button>
            </form>

            <form method="post" class="mt-4">
                <input type="hidden" name="action" value="resend">
                <button type="submit" id="resend-btn"
                        <?= $cooldownLeft > 0 ? 'disabled' : '' ?>
                        class="btn-hover w-full px-6 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                    <?php if ($cooldownLeft > 0): ?>
                        Повторно отправить через <span id="cd"><?= (int)$cooldownLeft ?></span> с
                    <?php else: ?>
                        Отправить код снова
                    <?php endif; ?>
                </button>
            </form>

            <p class="text-center text-xs text-gray-500 mt-5">
                Не тот email? <a href="logout.php" class="text-rose-500 font-bold hover:text-rose-600">Выйти и зарегистрироваться заново</a>
            </p>
        </div>
    </div>
</section>

<?php if ($cooldownLeft > 0): ?>
<script>
(function () {
    const btn = document.getElementById('resend-btn');
    const cd  = document.getElementById('cd');
    let left  = <?= (int)$cooldownLeft ?>;
    const t = setInterval(() => {
        left -= 1;
        if (left <= 0) {
            clearInterval(t);
            btn.disabled = false;
            btn.textContent = 'Отправить код снова';
        } else if (cd) {
            cd.textContent = left;
        }
    }, 1000);
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/page_scripts.php'; ?>
</body>
</html>
