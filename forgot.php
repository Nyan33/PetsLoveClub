<?php
require_once 'includes/auth.php';

if (!mail_enabled()) {
    // Without SMTP, we have no way to deliver a reset link.
    header('Location: login.php');
    exit;
}

$errors = [];
$flash  = null;
$old    = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $old['email'] = $email;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    } else {
        $st = $pdo->prepare("SELECT id, username, email FROM users WHERE email = :e LIMIT 1");
        $st->execute([':e' => $email]);
        $u = $st->fetch();
        if ($u) {
            $token = generate_reset_token();
            $upd = $pdo->prepare(
                "UPDATE users
                    SET password_reset_token = :t,
                        password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                  WHERE id = :id"
            );
            $upd->execute([':t' => $token, ':id' => $u['id']]);
            queue_password_reset_link($pdo, $u['email'], $u['username'], $token);
        }
        // Always show the same message - don't leak which emails exist.
        $flash = 'Если такой email зарегистрирован, мы отправили на него ссылку для сброса пароля. Проверьте входящие.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля - PetLove Club</title>
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
                    <i data-lucide="key-round" class="w-7 h-7 text-white"></i>
                </div>
            </div>
            <h1 class="text-2xl font-black text-center text-gray-900 mb-2">Забыли пароль?</h1>
            <p class="text-center text-sm text-gray-600 mb-6">Введите email - пришлём ссылку для сброса пароля.</p>

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
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($old['email']) ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors"
                           placeholder="you@example.com">
                </div>
                <button type="submit" class="btn-hover w-full px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500 transition-all duration-300">
                    Отправить ссылку
                </button>
                <p class="text-center text-sm text-gray-500">
                    Вспомнили? <a href="login.php" class="text-rose-500 font-bold hover:text-rose-600">Войти</a>
                </p>
            </form>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/page_scripts.php'; ?>
</body>
</html>
