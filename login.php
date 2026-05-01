<?php
require_once 'includes/auth.php';

$mode = $_GET['mode'] ?? 'login';
$errors = [];
$old = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($login === '' || $password === '') {
            $errors[] = 'Введите логин и пароль.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :l OR email = :l LIMIT 1");
            $stmt->execute([':l' => $login]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Неверный логин или пароль.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                header('Location: profile.php?u=' . urlencode($user['username']));
                exit;
            }
        }
        $mode = 'login';
    } elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $first    = trim($_POST['first_name'] ?? '');
        $last     = trim($_POST['last_name'] ?? '');
        $old['username'] = $username;
        $old['email'] = $email;

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Имя пользователя: 3–30 символов, только латиница, цифры и _.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Пароль должен быть не короче 6 символов.';
        }
        $resumeUser = null;
        if (!$errors) {
            $byEmail = $pdo->prepare("SELECT id, email_verified_at FROM users WHERE email = :e LIMIT 1");
            $byEmail->execute([':e' => $email]);
            $existing = $byEmail->fetch();
            if ($existing) {
                if (mail_enabled() && empty($existing['email_verified_at'])) {
                    // Previous registration was abandoned before verification -
                    // overwrite the unverified row and resend a fresh code.
                    $resumeUser = $existing;
                } else {
                    $errors[] = 'Пользователь с таким email уже зарегистрирован.';
                }
            }
        }
        if (!$errors) {
            $sql = "SELECT id FROM users WHERE username = :u";
            $params = [':u' => $username];
            if ($resumeUser) {
                $sql .= " AND id <> :ex";
                $params[':ex'] = (int)$resumeUser['id'];
            }
            $sql .= " LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            if ($st->fetch()) {
                $errors[] = 'Имя пользователя уже занято.';
            }
        }
        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $needsVerification = mail_enabled();

            if ($resumeUser) {
                $code = generate_verification_code();
                $upd = $pdo->prepare(
                    "UPDATE users
                        SET username = :u, password_hash = :p,
                            first_name = :fn, last_name = :ln,
                            email_verification_code = :c,
                            email_verification_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                            email_verification_sent_at = NOW()
                      WHERE id = :id"
                );
                $upd->execute([
                    ':u' => $username, ':p' => $hash,
                    ':fn' => $first !== '' ? $first : null,
                    ':ln' => $last  !== '' ? $last  : null,
                    ':c' => $code, ':id' => (int)$resumeUser['id'],
                ]);
                $_SESSION['user_id'] = (int)$resumeUser['id'];
                queue_verification_code($pdo, $email, $username, $code);
                header('Location: verify.php');
                exit;
            }

            $ins = $pdo->prepare(
                "INSERT INTO users (username, email, password_hash, first_name, last_name, email_verified_at)
                 VALUES (:u, :e, :p, :fn, :ln, :v)"
            );
            $ins->execute([
                ':u' => $username, ':e' => $email, ':p' => $hash,
                ':fn' => $first !== '' ? $first : null,
                ':ln' => $last  !== '' ? $last  : null,
                ':v'  => $needsVerification ? null : date('Y-m-d H:i:s'),
            ]);
            $newId = (int)$pdo->lastInsertId();
            $_SESSION['user_id'] = $newId;

            if ($needsVerification) {
                $code = generate_verification_code();
                $upd = $pdo->prepare(
                    "UPDATE users
                        SET email_verification_code = :c,
                            email_verification_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                            email_verification_sent_at = NOW()
                      WHERE id = :id"
                );
                $upd->execute([':c' => $code, ':id' => $newId]);
                queue_verification_code($pdo, $email, $username, $code);
                header('Location: verify.php');
                exit;
            }

            header('Location: profile.php?u=' . urlencode($username));
            exit;
        }
        $mode = 'register';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'register' ? 'Регистрация' : 'Вход' ?> - PetLove Club</title>
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
                    <i data-lucide="paw-print" class="w-7 h-7 text-white"></i>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-xl mb-6">
                <a href="?mode=login" class="btn-hover text-center py-2 rounded-lg font-bold text-sm <?= $mode==='login'    ? 'bg-white text-rose-500 shadow' : 'text-gray-600 hover:text-gray-900' ?>">Вход</a>
                <a href="?mode=register" class="btn-hover text-center py-2 rounded-lg font-bold text-sm <?= $mode==='register' ? 'bg-white text-rose-500 shadow' : 'text-gray-600 hover:text-gray-900' ?>">Регистрация</a>
            </div>

            <?php if ($errors): ?>
                <div class="mb-5 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                    <?php foreach ($errors as $e): ?>
                        <p class="text-rose-600 text-sm font-medium"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'register'): ?>
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="register">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Имя пользователя</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($old['username']) ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors"
                           placeholder="например, ivan_petrov">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($old['email']) ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors"
                           placeholder="you@example.com">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Пароль</label>
                    <input type="password" name="password" required minlength="6"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors"
                           placeholder="не менее 6 символов">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Имя <span class="text-gray-400 font-medium">(необязательно)</span></label>
                        <input type="text" name="first_name"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Фамилия <span class="text-gray-400 font-medium">(необязательно)</span></label>
                        <input type="text" name="last_name"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                    </div>
                </div>
                <button type="submit" class="btn-hover w-full px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500 transition-all duration-300">
                    Создать аккаунт
                </button>
                <p class="text-center text-sm text-gray-500">
                    Уже зарегистрированы? <a href="?mode=login" class="text-rose-500 font-bold hover:text-rose-600">Войти</a>
                </p>
            </form>
            <?php else: ?>
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Логин или email</label>
                    <input type="text" name="login" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Пароль</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                </div>
                <button type="submit" class="btn-hover w-full px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500 transition-all duration-300">
                    Войти
                </button>
                <?php if (mail_enabled()): ?>
                <p class="text-center text-sm">
                    <a href="forgot.php" class="text-gray-500 hover:text-rose-500 font-medium">Забыли пароль?</a>
                </p>
                <?php endif; ?>
                <p class="text-center text-sm text-gray-500">
                    Нет аккаунта? <a href="?mode=register" class="text-rose-500 font-bold hover:text-rose-600">Зарегистрироваться</a>
                </p>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/page_scripts.php'; ?>
</body>
</html>
