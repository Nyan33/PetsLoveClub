<?php
require_once __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $uid]);
    $target = $stmt->fetch();

    if (!$target) {
        admin_flash('Пользователь не найден.', 'err');
        header('Location: users.php'); exit;
    }
    if (!admin_can_target_user($target)) {
        admin_flash('Недостаточно прав для действия с этим пользователем.', 'err');
        header('Location: users.php'); exit;
    }

    if ($action === 'update') {
        $username  = trim($_POST['username']  ?? '');
        $email     = trim($_POST['email']     ?? '');
        $first     = trim($_POST['first_name']?? '');
        $last      = trim($_POST['last_name'] ?? '');
        $password  = (string)($_POST['password'] ?? '');
        $role      = (int)($_POST['role'] ?? 0);

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            admin_flash('Имя пользователя: 3–30 символов, латиница/цифры/_.', 'err');
            header('Location: users.php?id=' . $uid); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            admin_flash('Некорректный email.', 'err');
            header('Location: users.php?id=' . $uid); exit;
        }
        $maxRole = admin_assignable_max_role();
        if ($role < 0 || $role > $maxRole) {
            admin_flash('Эту роль вы назначать не можете.', 'err');
            header('Location: users.php?id=' . $uid); exit;
        }
        $check = $pdo->prepare("SELECT id FROM users WHERE (username=:u OR email=:e) AND id<>:id LIMIT 1");
        $check->execute([':u' => $username, ':e' => $email, ':id' => $uid]);
        if ($check->fetch()) {
            admin_flash('Имя пользователя или email уже заняты.', 'err');
            header('Location: users.php?id=' . $uid); exit;
        }

        $sql = "UPDATE users SET username=:u, email=:e, first_name=:f, last_name=:l, role=:r";
        $params = [
            ':u' => $username, ':e' => $email,
            ':f' => $first !== '' ? $first : null,
            ':l' => $last  !== '' ? $last  : null,
            ':r' => $role, ':id' => $uid,
        ];
        if ($password !== '') {
            if (mb_strlen($password) < 6) {
                admin_flash('Пароль должен быть не короче 6 символов.', 'err');
                header('Location: users.php?id=' . $uid); exit;
            }
            $sql .= ", password_hash=:p";
            $params[':p'] = password_hash($password, PASSWORD_BCRYPT);
        }
        $sql .= " WHERE id=:id";
        $pdo->prepare($sql)->execute($params);

        admin_flash('Профиль пользователя обновлён.');
        header('Location: users.php?id=' . $uid); exit;
    }

    if ($action === 'ban' || $action === 'unban') {
        $val = $action === 'ban' ? 1 : 0;
        $pdo->prepare("UPDATE users SET banned=:b WHERE id=:id")
            ->execute([':b' => $val, ':id' => $uid]);
        admin_flash($val ? 'Пользователь заблокирован.' : 'Блокировка снята.');
        header('Location: users.php?id=' . $uid); exit;
    }

    if ($action === 'verify' || $action === 'unverify') {
        if (!mail_enabled()) {
            admin_flash('Email-верификация отключена (нет SMTP-конфига).', 'err');
            header('Location: users.php?id=' . $uid); exit;
        }
        if ($action === 'verify') {
            $pdo->prepare(
                "UPDATE users
                    SET email_verified_at = NOW(),
                        email_verification_code = NULL,
                        email_verification_expires_at = NULL
                  WHERE id = :id"
            )->execute([':id' => $uid]);
            admin_flash('Email подтверждён вручную.');
        } else {
            $pdo->prepare(
                "UPDATE users
                    SET email_verified_at = NULL,
                        email_verification_code = NULL,
                        email_verification_expires_at = NULL,
                        email_verification_sent_at = NULL
                  WHERE id = :id"
            )->execute([':id' => $uid]);
            admin_flash('Верификация снята - пользователь снова обязан подтвердить email.');
        }
        header('Location: users.php?id=' . $uid); exit;
    }

    if ($action === 'delete') {
        if (!empty($target['avatar_url']) && str_starts_with($target['avatar_url'], 'uploads/')) {
            delete_local_upload($target['avatar_url']);
        }
        $petPhotos = $pdo->prepare("SELECT photo_url FROM pets WHERE user_id=:id");
        $petPhotos->execute([':id' => $uid]);
        foreach ($petPhotos->fetchAll() as $pp) {
            if (!empty($pp['photo_url']) && str_starts_with($pp['photo_url'], 'uploads/')) {
                delete_local_upload($pp['photo_url']);
            }
        }
        $pdo->prepare("DELETE FROM users WHERE id=:id")->execute([':id' => $uid]);
        admin_flash('Пользователь удалён.');
        header('Location: users.php'); exit;
    }
}

$selectedId = (int)($_GET['id'] ?? 0);
$query      = trim($_GET['q'] ?? '');

$where  = ['u.role <= ' . admin_visible_max_role(), 'u.id <> ' . (int)$adminUser['id']];
$params = [];
if ($query !== '') {
    $where[] = '(u.username LIKE :q OR u.email LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q)';
    $params[':q'] = '%' . $query . '%';
}
$sql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.banned, u.email_verified_at, u.created_at, u.avatar_url,
               (SELECT COUNT(*) FROM pets p WHERE p.user_id = u.id) AS pet_count
          FROM users u
         WHERE " . implode(' AND ', $where) . "
         ORDER BY u.role DESC, u.created_at DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$users = $st->fetchAll();

$selected = null;
if ($selectedId) {
    foreach ($users as $u) if ((int)$u['id'] === $selectedId) { $selected = $u; break; }
    if (!$selected) {
        $st = $pdo->prepare("SELECT * FROM users WHERE id=:id");
        $st->execute([':id' => $selectedId]);
        $cand = $st->fetch();
        if ($cand && admin_can_target_user($cand)) $selected = $cand;
    }
}

$pageTitle  = 'Пользователи';
$pageActive = 'users';
include __DIR__ . '/_layout.php';
?>

<div class="grid lg:grid-cols-5 gap-4 sm:gap-6">
    <section class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-2xl p-4 sm:p-5">
        <form method="get" class="mb-4 flex gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($query) ?>"
                   placeholder="Поиск по имени, email…"
                   class="flex-1 min-w-0 px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
            <button type="submit" class="btn-hover px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-sm">
                <i data-lucide="search" class="w-4 h-4"></i>
            </button>
        </form>
        <p class="text-xs text-gray-500 mb-3 px-1">Видимы пользователи с ролью ≤ <?= admin_visible_max_role() ?>. <?= count($users) ?> чел.</p>
        <div class="space-y-1.5 max-h-[70vh] overflow-y-auto pr-1">
            <?php if (!$users): ?>
                <p class="text-sm text-gray-500 py-6 text-center">Никого не найдено.</p>
            <?php endif; ?>
            <?php foreach ($users as $u):
                $ri = role_info((int)$u['role']);
                $isSel = $selected && (int)$selected['id'] === (int)$u['id'];
            ?>
                <a href="?id=<?= (int)$u['id'] ?><?= $query !== '' ? '&q=' . urlencode($query) : '' ?>"
                   class="btn-hover flex items-center gap-3 p-2.5 rounded-xl border <?= $isSel ? 'bg-rose-500/15 border-rose-500/60' : 'border-transparent hover:bg-gray-800 hover:border-gray-700' ?>">
                    <?php if (!empty($u['avatar_url'])): ?>
                        <img src="../<?= htmlspecialchars($u['avatar_url']) ?>" alt="" class="w-9 h-9 rounded-full object-cover flex-shrink-0">
                    <?php else: ?>
                        <div class="w-9 h-9 rounded-full bg-rose-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                            <?= htmlspecialchars(mb_strtoupper(mb_substr($u['username'],0,1))) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm text-white truncate flex items-center gap-1.5">
                            <?= htmlspecialchars($u['username']) ?>
                            <?php if ($ri): ?><i data-lucide="<?= $ri['icon'] ?>" class="w-3.5 h-3.5 <?= $ri['text'] ?>"></i><?php endif; ?>
                            <?php if ((int)$u['banned']): ?><span class="px-1.5 py-0.5 text-[10px] rounded bg-rose-500/20 text-rose-300 font-bold">BAN</span><?php endif; ?>
                            <?php if (mail_enabled() && empty($u['email_verified_at'])): ?><span class="px-1.5 py-0.5 text-[10px] rounded bg-amber-500/20 text-amber-300 font-bold" title="Email не подтверждён">!@</span><?php endif; ?>
                        </p>
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($u['email']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="lg:col-span-3 bg-gray-900 border border-gray-800 rounded-2xl p-4 sm:p-6">
        <?php if (!$selected): ?>
            <p class="text-gray-500 text-center py-16">Выберите пользователя из списка для редактирования.</p>
        <?php else:
            $selRole = (int)$selected['role'];
            $maxAssign = admin_assignable_max_role();
        ?>
        <div class="flex items-center gap-4 mb-6">
            <?php if (!empty($selected['avatar_url'])): ?>
                <img src="../<?= htmlspecialchars($selected['avatar_url']) ?>" alt="" class="w-16 h-16 rounded-2xl object-cover">
            <?php else: ?>
                <div class="w-16 h-16 rounded-2xl bg-rose-500 flex items-center justify-center text-white text-2xl font-black">
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($selected['username'],0,1))) ?>
                </div>
            <?php endif; ?>
            <div class="min-w-0">
                <h2 class="text-xl font-black text-white truncate"><?= htmlspecialchars($selected['username']) ?></h2>
                <p class="text-sm text-gray-400 truncate">ID: <?= (int)$selected['id'] ?> · с <?= date('d.m.Y', strtotime($selected['created_at'])) ?></p>
            </div>
        </div>

        <form method="post" class="space-y-4" autocomplete="off">
            <input type="hidden" name="csrf"    value="<?= htmlspecialchars(admin_csrf()) ?>">
            <input type="hidden" name="action"  value="update">
            <input type="hidden" name="user_id" value="<?= (int)$selected['id'] ?>">

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">Имя пользователя</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($selected['username']) ?>"
                           class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($selected['email']) ?>"
                           class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">Имя</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($selected['first_name'] ?? '') ?>"
                           class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">Фамилия</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($selected['last_name'] ?? '') ?>"
                           class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">Новый пароль (оставьте пустым, чтобы не менять)</label>
                    <input type="password" name="password" minlength="6"
                           class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">Роль</label>
                    <select name="role" class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
                        <option value="0" <?= $selRole===0?'selected':'' ?>>0 - Пользователь</option>
                        <?php if ($maxAssign >= ROLE_EDITOR): ?>
                            <option value="1" <?= $selRole===1?'selected':'' ?>>1 - Редактор</option>
                        <?php endif; ?>
                        <?php if ($maxAssign >= ROLE_ADMIN): ?>
                            <option value="2" <?= $selRole===2?'selected':'' ?>>2 - Администратор</option>
                        <?php endif; ?>
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1">
                        <?= $isOwner ? 'Как владелец, вы можете назначать роли вплоть до администратора.' : 'Как администратор, вы можете назначать только роль редактора.' ?>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <button type="submit" class="btn-hover px-5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-sm inline-flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Сохранить
                </button>
                <a href="../profile.php?u=<?= urlencode($selected['username']) ?>" target="_blank"
                   class="btn-hover px-4 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-200 font-bold text-sm inline-flex items-center gap-2">
                    <i data-lucide="external-link" class="w-4 h-4"></i> Профиль
                </a>
            </div>
        </form>

        <div class="mt-6 pt-5 border-t border-gray-800 flex flex-wrap gap-2">
            <form method="post" class="contents" onsubmit="return confirm('<?= (int)$selected['banned'] ? 'Снять блокировку?' : 'Заблокировать пользователя? Он не сможет записываться на события.' ?>');">
                <input type="hidden" name="csrf"    value="<?= htmlspecialchars(admin_csrf()) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$selected['id'] ?>">
                <input type="hidden" name="action"  value="<?= (int)$selected['banned'] ? 'unban' : 'ban' ?>">
                <button type="submit"
                        class="btn-hover px-4 py-2.5 rounded-xl <?= (int)$selected['banned'] ? 'bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300' : 'bg-amber-500/20 hover:bg-amber-500/30 text-amber-300' ?> font-bold text-sm inline-flex items-center gap-2">
                    <i data-lucide="<?= (int)$selected['banned'] ? 'shield-check' : 'ban' ?>" class="w-4 h-4"></i>
                    <?= (int)$selected['banned'] ? 'Снять блокировку' : 'Заблокировать' ?>
                </button>
            </form>
            <?php if (mail_enabled()):
                $isVerified = !empty($selected['email_verified_at']);
            ?>
            <form method="post" class="contents" onsubmit="return confirm('<?= $isVerified ? 'Снять верификацию email? Пользователь снова станет ограниченным, пока не подтвердит email кодом.' : 'Подтвердить email вручную, без отправки кода?' ?>');">
                <input type="hidden" name="csrf"    value="<?= htmlspecialchars(admin_csrf()) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$selected['id'] ?>">
                <input type="hidden" name="action"  value="<?= $isVerified ? 'unverify' : 'verify' ?>">
                <button type="submit"
                        class="btn-hover px-4 py-2.5 rounded-xl <?= $isVerified ? 'bg-amber-500/20 hover:bg-amber-500/30 text-amber-300' : 'bg-sky-500/20 hover:bg-sky-500/30 text-sky-300' ?> font-bold text-sm inline-flex items-center gap-2">
                    <i data-lucide="<?= $isVerified ? 'mail-x' : 'mail-check' ?>" class="w-4 h-4"></i>
                    <?= $isVerified ? 'Снять верификацию' : 'Подтвердить email' ?>
                </button>
            </form>
            <?php endif; ?>
            <form method="post" class="contents" onsubmit="return confirm('Удалить пользователя НАВСЕГДА вместе с питомцами и записями? Это действие необратимо.');">
                <input type="hidden" name="csrf"    value="<?= htmlspecialchars(admin_csrf()) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$selected['id'] ?>">
                <input type="hidden" name="action"  value="delete">
                <button type="submit" class="btn-hover px-4 py-2.5 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 font-bold text-sm inline-flex items-center gap-2">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Удалить пользователя
                </button>
            </form>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
