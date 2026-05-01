<?php
require_once 'includes/auth.php';

$token  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$flash  = null;
$ok     = false;

$user = null;
if ($token !== '') {
    $st = $pdo->prepare(
        "SELECT id, username, email, password_reset_expires_at
           FROM users WHERE password_reset_token = :t LIMIT 1"
    );
    $st->execute([':t' => $token]);
    $user = $st->fetch();
}

$expired = false;
if ($user) {
    if (!empty($user['password_reset_expires_at'])) {
        $st = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, NOW(), password_reset_expires_at) FROM users WHERE id = :id");
        $st->execute([':id' => $user['id']]);
        $secLeft = $st->fetchColumn();
        if ($secLeft === false || $secLeft === null || (int)$secLeft <= 0) $expired = true;
    } else {
        $expired = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && !$expired) {
    $password  = (string)($_POST['password']  ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    if (mb_strlen($password) < 6) {
        $errors[] = 'Пароль должен быть не короче 6 символов.';
    } elseif ($password !== $password2) {
        $errors[] = 'Пароли не совпадают.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare(
            "UPDATE users
                SET password_hash = :p,
                    password_reset_token = NULL,
                    password_reset_expires_at = NULL
              WHERE id = :id"
        );
        $upd->execute([':p' => $hash, ':id' => $user['id']]);
        $ok = true;
        $flash = 'Пароль обновлён. Теперь вы можете войти.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новый пароль - PetLove Club</title>
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
                    <i data-lucide="lock" class="w-7 h-7 text-white"></i>
                </div>
            </div>
            <h1 class="text-2xl font-black text-center text-gray-900 mb-2">Новый пароль</h1>

            <?php if (!$user): ?>
                <p class="text-center text-sm text-gray-600 mb-5">Ссылка недействительна. Запросите новую на странице восстановления пароля.</p>
                <a href="forgot.php" class="btn-hover block w-full text-center px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">Запросить новую ссылку</a>
            <?php elseif ($expired && !$ok): ?>
                <p class="text-center text-sm text-gray-600 mb-5">Срок действия ссылки истёк. Запросите новую.</p>
                <a href="forgot.php" class="btn-hover block w-full text-center px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">Запросить новую ссылку</a>
            <?php elseif ($ok): ?>
                <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-xl">
                    <p class="text-green-700 text-sm font-medium"><?= htmlspecialchars($flash) ?></p>
                </div>
                <a href="login.php" class="btn-hover block w-full text-center px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">Войти</a>
            <?php else: ?>
                <p class="text-center text-sm text-gray-600 mb-5">Установка нового пароля для <b><?= htmlspecialchars($user['email']) ?></b>.</p>

                <?php if ($errors): ?>
                    <div class="mb-5 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                        <?php foreach ($errors as $e): ?>
                            <p class="text-rose-600 text-sm font-medium"><?= htmlspecialchars($e) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="space-y-4">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Новый пароль</label>
                        <input type="password" name="password" required minlength="6"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors"
                               placeholder="не менее 6 символов">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Повторите пароль</label>
                        <input type="password" name="password2" required minlength="6"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                    </div>
                    <button type="submit" class="btn-hover w-full px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500 transition-all duration-300">
                        Сохранить пароль
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/page_scripts.php'; ?>
</body>
</html>
