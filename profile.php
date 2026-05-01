<?php
require_once 'includes/auth.php';
require_once 'includes/image_upload.php';

$viewer = current_user();
$username = $_GET['u'] ?? ($viewer['username'] ?? '');
if ($username === '') {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
$stmt->execute([':u' => $username]);
$profile = $stmt->fetch();
if (!$profile) {
    http_response_code(404);
    $notFound = true;
}

$isOwner = $viewer && $profile && (int)$viewer['id'] === (int)$profile['id'];
$tab = $_GET['tab'] ?? 'pets';
$flash = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $viewer && ($_POST['action'] ?? '') === 'gallery_vote') {
    $gid  = (int)($_POST['gallery_id'] ?? 0);
    $vote = (int)($_POST['vote'] ?? 0);
    $resp = ['ok' => false, 'score' => 0, 'my_vote' => 0];
    if (is_restricted($viewer)) {
        $resp['error'] = restricted_reason($viewer);
    } elseif ($vote !== 1 && $vote !== -1) {
        $resp['error'] = 'bad_vote';
    } else {
        $chk = $pdo->prepare("SELECT id FROM gallery WHERE id = :g");
        $chk->execute([':g' => $gid]);
        if ($chk->fetch()) {
            $cur = $pdo->prepare("SELECT vote FROM gallery_votes WHERE gallery_id = :g AND user_id = :u");
            $cur->execute([':g' => $gid, ':u' => $viewer['id']]);
            $ex = $cur->fetch();
            if ($ex && (int)$ex['vote'] === $vote) {
                $pdo->prepare("DELETE FROM gallery_votes WHERE gallery_id = :g AND user_id = :u")
                    ->execute([':g' => $gid, ':u' => $viewer['id']]);
                $resp['my_vote'] = 0;
            } else {
                $pdo->prepare(
                    "INSERT INTO gallery_votes (gallery_id, user_id, vote) VALUES (:g, :u, :v)
                     ON DUPLICATE KEY UPDATE vote = VALUES(vote), created_at = CURRENT_TIMESTAMP"
                )->execute([':g' => $gid, ':u' => $viewer['id'], ':v' => $vote]);
                $resp['my_vote'] = $vote;
            }
            $sum = $pdo->prepare("SELECT COALESCE(SUM(vote),0) FROM gallery_votes WHERE gallery_id = :g");
            $sum->execute([':g' => $gid]);
            $resp['score'] = (int)$sum->fetchColumn();
            $resp['ok'] = true;
        } else {
            $resp['error'] = 'not_found';
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $viewer && !$isOwner) {
    $action = $_POST['action'] ?? '';
    if ($action === 'admin_delete_gallery' && (int)($viewer['role'] ?? 0) >= ROLE_ADMIN) {
        $gid = (int)($_POST['gallery_id'] ?? 0);
        $st = $pdo->prepare("SELECT image_url FROM gallery WHERE id = :g");
        $st->execute([':g' => $gid]);
        $row = $st->fetch();
        if ($row) {
            $pdo->prepare("DELETE FROM gallery WHERE id = :g")->execute([':g' => $gid]);
            if (!empty($row['image_url'])) delete_local_upload($row['image_url']);
        }
        header('Location: profile.php?u=' . urlencode($profile['username']) . '&tab=gallery');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $newUsername  = trim($_POST['username']     ?? '');
        $newEmail     = trim($_POST['email']        ?? '');
        $first        = trim($_POST['first_name']   ?? '');
        $last         = trim($_POST['last_name']    ?? '');
        $avatarData   = $_POST['avatar_data']       ?? '';
        $removeAvatar = !empty($_POST['avatar_remove']);

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $newUsername)) {
            $errors[] = 'Имя пользователя: 3–30 символов, только латиница, цифры и _.';
        }
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        }
        if (!$errors && $newUsername !== $profile['username']) {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1");
            $check->execute([':u' => $newUsername, ':id' => $profile['id']]);
            if ($check->fetch()) {
                $errors[] = 'Это имя пользователя уже занято.';
            }
        }
        $emailChanged = !$errors && strcasecmp($newEmail, (string)$profile['email']) !== 0;
        if ($emailChanged) {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = :e AND id != :id LIMIT 1");
            $check->execute([':e' => $newEmail, ':id' => $profile['id']]);
            if ($check->fetch()) {
                $errors[] = 'Этот email уже используется другим аккаунтом.';
            }
        }

        $newAvatarPath = $profile['avatar_url'] ?? null;
        if (!$errors && $avatarData !== '') {
            $saved = save_base64_image($avatarData, 'avatars', 'u' . (int)$profile['id']);
            if (!$saved) {
                $errors[] = 'Не удалось сохранить аватар. Поддерживаются JPG, PNG, WEBP до 5 МБ.';
            } else {
                if (!empty($profile['avatar_url']) && str_starts_with($profile['avatar_url'], 'uploads/')) {
                    delete_local_upload($profile['avatar_url']);
                }
                $newAvatarPath = $saved;
            }
        } elseif (!$errors && $removeAvatar) {
            if (!empty($profile['avatar_url']) && str_starts_with($profile['avatar_url'], 'uploads/')) {
                delete_local_upload($profile['avatar_url']);
            }
            $newAvatarPath = null;
        }

        if (!$errors) {
            $sql = "UPDATE users SET username = :un, email = :em, first_name = :fn, last_name = :ln, avatar_url = :av";
            $params = [
                ':un' => $newUsername,
                ':em' => $newEmail,
                ':fn' => $first !== '' ? $first : null,
                ':ln' => $last  !== '' ? $last  : null,
                ':av' => $newAvatarPath,
                ':id' => $profile['id'],
            ];
            if ($emailChanged && mail_enabled()) {
                $sql .= ", email_verified_at = NULL,
                          email_verification_code = :code,
                          email_verification_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                          email_verification_sent_at = NOW()";
                $params[':code'] = generate_verification_code();
            }
            $sql .= " WHERE id = :id";
            $pdo->prepare($sql)->execute($params);

            if ($emailChanged && mail_enabled()) {
                queue_verification_code($pdo, $newEmail, $newUsername, $params[':code']);
                header('Location: verify.php');
                exit;
            }
            header('Location: profile.php?u=' . urlencode($newUsername) . '&tab=settings');
            exit;
        }
    } elseif ($action === 'add_pet') {
        if (is_restricted($viewer)) {
            $errors[] = restricted_message($viewer, 'добавление питомцев');
        }
        $name      = trim($_POST['name']  ?? '');
        $breed     = trim($_POST['breed'] ?? '');
        $type      = $_POST['type'] ?? '';
        $photoData = $_POST['photo_data'] ?? '';

        if (!$errors) {
        if ($name === '' || $breed === '') $errors[] = 'Укажите кличку и породу.';
        if (!in_array($type, ['dog','cat','other'], true)) $errors[] = 'Выберите тип питомца.';
        if ($photoData === '') $errors[] = 'Загрузите и обрежьте фотографию питомца.';
        }

        if (!$errors) {
            $saved = save_base64_image($photoData, 'pets', 'p' . (int)$profile['id']);
            if (!$saved) {
                $errors[] = 'Не удалось сохранить фото. Поддерживаются JPG, PNG, WEBP до 5 МБ.';
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO pets (user_id, name, breed, type, photo_url)
                     VALUES (:uid, :n, :b, :t, :p)"
                );
                $ins->execute([
                    ':uid' => $profile['id'], ':n' => $name, ':b' => $breed,
                    ':t' => $type, ':p' => $saved,
                ]);
                header('Location: profile.php?u=' . urlencode($profile['username']) . '&tab=pets');
                exit;
            }
        }
    } elseif ($action === 'edit_pet') {
        $pid       = (int)($_POST['pet_id'] ?? 0);
        $name      = trim($_POST['name']  ?? '');
        $breed     = trim($_POST['breed'] ?? '');
        $type      = $_POST['type'] ?? '';
        $photoData = $_POST['photo_data'] ?? '';

        $cur = $pdo->prepare("SELECT * FROM pets WHERE id = :id AND user_id = :uid");
        $cur->execute([':id' => $pid, ':uid' => $profile['id']]);
        $petRow = $cur->fetch();

        if (!$petRow) {
            $errors[] = 'Питомец не найден.';
        } else {
            if ($name === '' || $breed === '') $errors[] = 'Укажите кличку и породу.';
            if (!in_array($type, ['dog','cat','other'], true)) $errors[] = 'Выберите тип питомца.';
        }

        if (!$errors) {
            $newPhoto = $petRow['photo_url'];
            if ($photoData !== '') {
                $saved = save_base64_image($photoData, 'pets', 'p' . (int)$profile['id']);
                if (!$saved) {
                    $errors[] = 'Не удалось сохранить фото. Поддерживаются JPG, PNG, WEBP до 5 МБ.';
                } else {
                    if (!empty($petRow['photo_url']) && str_starts_with($petRow['photo_url'], 'uploads/')) {
                        delete_local_upload($petRow['photo_url']);
                    }
                    $newPhoto = $saved;
                }
            }
            if (!$errors) {
                $upd = $pdo->prepare(
                    "UPDATE pets SET name = :n, breed = :b, type = :t, photo_url = :p
                       WHERE id = :id AND user_id = :uid"
                );
                $upd->execute([
                    ':n' => $name, ':b' => $breed, ':t' => $type, ':p' => $newPhoto,
                    ':id' => $pid, ':uid' => $profile['id'],
                ]);
                header('Location: profile.php?u=' . urlencode($profile['username']) . '&tab=pets');
                exit;
            }
        }
    } elseif ($action === 'delete_pet') {
        $pid = (int)($_POST['pet_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT photo_url FROM pets WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $pid, ':uid' => $profile['id']]);
        $petRow = $stmt->fetch();
        $del = $pdo->prepare("DELETE FROM pets WHERE id = :id AND user_id = :uid");
        $del->execute([':id' => $pid, ':uid' => $profile['id']]);
        if ($petRow && !empty($petRow['photo_url'])) {
            delete_local_upload($petRow['photo_url']);
        }
        header('Location: profile.php?u=' . urlencode($profile['username']) . '&tab=pets');
        exit;
    } elseif ($action === 'unregister_event') {
        $eid = (int)($_POST['event_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = :e AND user_id = :u");
        $del->execute([':e' => $eid, ':u' => $profile['id']]);
        header('Location: profile.php?u=' . urlencode($profile['username']) . '&tab=events');
        exit;
    } elseif ($action === 'add_gallery') {
        if (is_restricted($viewer)) {
            $errors[] = restricted_message($viewer, 'публикация в галерее');
        } else {
        $petId     = (int)($_POST['pet_id'] ?? 0);
        $photoData = $_POST['photo_data'] ?? '';
        $caption   = trim($_POST['caption'] ?? '');
        if (mb_strlen($caption) > 1000) $caption = mb_substr($caption, 0, 1000);
        $check = $pdo->prepare("SELECT id FROM pets WHERE id = :p AND user_id = :u");
        $check->execute([':p' => $petId, ':u' => $profile['id']]);
        if (!$check->fetch()) {
            $errors[] = 'Выберите своего питомца.';
        } elseif ($photoData === '') {
            $errors[] = 'Загрузите и обрежьте фотографию.';
        } else {
            $saved = save_base64_image($photoData, 'gallery', 'g' . (int)$profile['id']);
            if (!$saved) {
                $errors[] = 'Не удалось сохранить фото. JPG/PNG/WEBP до 5 МБ.';
            } else {
                $ins = $pdo->prepare("INSERT INTO gallery (user_id, pet_id, image_url, caption) VALUES (:u, :p, :i, :c)");
                $ins->execute([':u' => $profile['id'], ':p' => $petId, ':i' => $saved, ':c' => $caption !== '' ? $caption : null]);
                header('Location: profile.php?u=' . urlencode($profile['username']) . '&tab=gallery');
                exit;
            }
        }
        }
    } elseif ($action === 'delete_gallery') {
        $gid = (int)($_POST['gallery_id'] ?? 0);
        $st = $pdo->prepare("SELECT image_url FROM gallery WHERE id = :g AND user_id = :u");
        $st->execute([':g' => $gid, ':u' => $profile['id']]);
        $row = $st->fetch();
        if ($row) {
            $del = $pdo->prepare("DELETE FROM gallery WHERE id = :g AND user_id = :u");
            $del->execute([':g' => $gid, ':u' => $profile['id']]);
            if (!empty($row['image_url'])) delete_local_upload($row['image_url']);
        }
        header('Location: profile.php?u=' . urlencode($profile['username']) . '&tab=gallery');
        exit;
    }
}

$pets = [];
$awards = [];
$myEvents = [];
$gallery = [];
if (!empty($profile)) {
    $ps = $pdo->prepare("SELECT * FROM pets WHERE user_id = :uid ORDER BY created_at DESC");
    $ps->execute([':uid' => $profile['id']]);
    $pets = $ps->fetchAll();

    $as = $pdo->prepare(
        "SELECT c.*, p.name AS pet_name, p.type AS pet_type
           FROM champions c
           JOIN pets p ON p.id = c.pet_id
          WHERE p.user_id = :uid
          ORDER BY c.year DESC"
    );
    $as->execute([':uid' => $profile['id']]);
    $awards = $as->fetchAll();

    if ($isOwner) {
        $es = $pdo->prepare(
            "SELECT e.*, p.name AS pet_name, r.id AS reg_id
               FROM event_registrations r
               JOIN events e ON e.id = r.event_id
               JOIN pets p   ON p.id = r.pet_id
              WHERE r.user_id = :uid AND e.is_completed = 0
              ORDER BY e.event_date ASC"
        );
        $es->execute([':uid' => $profile['id']]);
        $myEvents = $es->fetchAll();
    }

    $viewerId = $viewer ? (int)$viewer['id'] : 0;
    $gs = $pdo->prepare(
        "SELECT g.*, p.name AS pet_name, p.breed AS pet_breed, p.type AS pet_type,
                (SELECT COALESCE(SUM(vote),0) FROM gallery_votes v WHERE v.gallery_id = g.id) AS score,
                (SELECT vote FROM gallery_votes v WHERE v.gallery_id = g.id AND v.user_id = :vid) AS my_vote
           FROM gallery g
           JOIN pets p ON p.id = g.pet_id
          WHERE g.user_id = :uid
          ORDER BY g.created_at DESC"
    );
    $gs->execute([':uid' => $profile['id'], ':vid' => $viewerId]);
    $gallery = $gs->fetchAll();
}

$myEventsParticipantsJson = [];
if (!empty($myEvents)) {
    $eventIds = array_map(fn($e) => (int)$e['id'], $myEvents);
    $in = implode(',', $eventIds);
    $rows = $pdo->query(
        "SELECT r.event_id, p.id AS pet_id, p.name AS pet_name, p.breed AS pet_breed, p.photo_url, p.user_id,
                u.username, u.first_name, u.last_name, u.avatar_url,
                (SELECT COUNT(*) FROM event_supports s WHERE s.event_id = r.event_id AND s.pet_id = p.id) AS support_count
           FROM event_registrations r
           JOIN pets p  ON p.id = r.pet_id
           JOIN users u ON u.id = p.user_id
          WHERE r.event_id IN ($in)
          ORDER BY support_count DESC, r.created_at ASC"
    )->fetchAll();
    $partsByEvent = [];
    foreach ($rows as $row) $partsByEvent[(int)$row['event_id']][] = $row;

    $mySupports = [];
    if ($viewer) {
        $sQ = $pdo->prepare("SELECT event_id, pet_id FROM event_supports WHERE user_id = :u AND event_id IN ($in)");
        $sQ->execute([':u' => $viewer['id']]);
        foreach ($sQ->fetchAll() as $r) $mySupports[(int)$r['event_id']] = (int)$r['pet_id'];
    }

    foreach ($myEvents as $ev) {
        $eid = (int)$ev['id'];
        $items = [];
        $myPetIds = [];
        foreach ($partsByEvent[$eid] ?? [] as $p) {
            $owner_full = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
            if ($viewer && (int)$p['user_id'] === (int)$viewer['id']) $myPetIds[] = (int)$p['pet_id'];
            $items[] = [
                'pet_id'        => (int)$p['pet_id'],
                'pet_name'      => $p['pet_name'],
                'pet_breed'     => $p['pet_breed'],
                'photo_url'     => $p['photo_url'],
                'user_id'       => (int)$p['user_id'],
                'username'      => $p['username'],
                'owner_name'    => $owner_full !== '' ? $owner_full : $p['username'],
                'avatar_url'    => $p['avatar_url'],
                'support_count' => (int)$p['support_count'],
                'is_winner'     => ((int)($ev['winner_pet_id'] ?? 0) === (int)$p['pet_id']),
            ];
        }
        $myEventsParticipantsJson[$eid] = [
            'title'        => $ev['title'],
            'is_completed' => (int)$ev['is_completed'] === 1,
            'winner_pet_id'=> $ev['winner_pet_id'] ? (int)$ev['winner_pet_id'] : null,
            'my_support'   => $mySupports[$eid] ?? null,
            'my_pet_ids'   => $myPetIds,
            'logged_in'    => (bool)$viewer,
            'restricted'   => is_restricted($viewer),
            'restrict_reason' => restricted_reason($viewer),
            'participants' => $items,
        ];
    }
}

$profileRoleInfo = !empty($profile) ? role_info((int)($profile['role'] ?? 0)) : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['username'] ?? 'Профиль') ?> - PetLove Club</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css">
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>
    <link rel="stylesheet" href="./styles/tailwind.css">
    <link rel="shortcut icon" href="./favicon.png" type="image/x-icon">
</head>
<body class="bg-amber-50 min-h-screen">

<?php $active = ''; include 'includes/nav.php'; ?>

<?php if (!empty($notFound)): ?>
<section class="pt-40 pb-20 px-4">
    <div class="max-w-2xl mx-auto text-center">
        <h1 class="text-4xl font-black text-gray-900 mb-3">Пользователь не найден</h1>
        <p class="text-gray-600 mb-6">Профиль с таким именем не существует.</p>
        <a href="index.php" class="btn-hover inline-block px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">На главную</a>
    </div>
</section>
<?php else: ?>

<section class="pt-32 pb-10 px-3 sm:px-4 overflow-x-hidden">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden border-2 border-gray-100">
            <div class="h-24 sm:h-32 bg-gradient-to-r from-rose-400 to-orange-400"></div>
            <div class="px-5 sm:px-8 pb-6 sm:pb-8">
                <div class="flex flex-col sm:flex-row items-start sm:items-end -mt-14 sm:-mt-16 gap-4 sm:gap-6">
                    <?php if (!empty($profile['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($profile['avatar_url']) ?>" alt=""
                             class="w-24 h-24 sm:w-32 sm:h-32 rounded-2xl object-cover border-4 border-white shadow-lg flex-shrink-0">
                    <?php else: ?>
                        <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-2xl bg-rose-400 border-4 border-white shadow-lg flex items-center justify-center text-white text-4xl sm:text-5xl font-black flex-shrink-0">
                            <?= htmlspecialchars(mb_strtoupper(mb_substr($profile['username'],0,1))) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1 pt-1 sm:pt-2 min-w-0 w-full">
                        <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 break-words"><?= htmlspecialchars(display_name($profile)) ?></h1>
                            <?php if ($profileRoleInfo): ?>
                                <span class="role-badge inline-flex items-center justify-center"
                                      data-role-tip="<?= htmlspecialchars($profileRoleInfo['desc']) ?>">
                                    <i data-lucide="<?= $profileRoleInfo['icon'] ?>" class="w-6 h-6 sm:w-7 sm:h-7 <?= $profileRoleInfo['text'] ?>"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-500 font-medium text-sm sm:text-base break-all">@<?= htmlspecialchars($profile['username']) ?></p>
                    </div>
                    <?php if ($isOwner): ?>
                        <a href="logout.php" class="btn-hover px-4 sm:px-5 py-2 sm:py-2.5 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 text-sm sm:text-base">Выйти</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mt-8 border-b-2 border-gray-100 overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
            <div class="flex gap-1 sm:gap-2 min-w-max">
                <a href="?u=<?= urlencode($profile['username']) ?>&tab=pets"
                   class="btn-hover px-3 sm:px-5 py-2.5 sm:py-3 font-bold text-sm sm:text-base whitespace-nowrap border-b-4 -mb-0.5 <?= $tab==='pets'    ? 'text-rose-500 border-rose-400' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
                    Питомцы (<?= count($pets) ?>)
                </a>
                <a href="?u=<?= urlencode($profile['username']) ?>&tab=awards"
                   class="btn-hover px-3 sm:px-5 py-2.5 sm:py-3 font-bold text-sm sm:text-base whitespace-nowrap border-b-4 -mb-0.5 <?= $tab==='awards' ? 'text-rose-500 border-rose-400' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
                    Награды (<?= count($awards) ?>)
                </a>
                <a href="?u=<?= urlencode($profile['username']) ?>&tab=gallery"
                   class="btn-hover px-3 sm:px-5 py-2.5 sm:py-3 font-bold text-sm sm:text-base whitespace-nowrap border-b-4 -mb-0.5 <?= $tab==='gallery' ? 'text-rose-500 border-rose-400' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
                    Галерея (<?= count($gallery) ?>)
                </a>
                <?php if ($isOwner): ?>
                <a href="?u=<?= urlencode($profile['username']) ?>&tab=events"
                   class="btn-hover px-3 sm:px-5 py-2.5 sm:py-3 font-bold text-sm sm:text-base whitespace-nowrap border-b-4 -mb-0.5 <?= $tab==='events' ? 'text-rose-500 border-rose-400' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
                    Мои события (<?= count($myEvents) ?>)
                </a>
                <a href="?u=<?= urlencode($profile['username']) ?>&tab=settings"
                   class="btn-hover px-3 sm:px-5 py-2.5 sm:py-3 font-bold text-sm sm:text-base whitespace-nowrap border-b-4 -mb-0.5 <?= $tab==='settings' ? 'text-rose-500 border-rose-400' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
                    Настройки
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="mt-6 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                <?php foreach ($errors as $e): ?>
                    <p class="text-rose-600 text-sm font-medium"><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-6">
        <?php if ($tab === 'pets'): ?>
            <?php if ($isOwner && !is_restricted($viewer)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 border-2 border-gray-100">
                <h2 class="text-xl font-black text-gray-900 mb-4">Добавить питомца</h2>
                <form method="post" id="pet-form" class="grid md:grid-cols-2 gap-4">
                    <input type="hidden" name="action" value="add_pet">
                    <input type="hidden" name="photo_data" id="pet-photo-data" value="">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Кличка</label>
                        <input type="text" name="name" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Порода</label>
                        <input type="text" name="breed" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Тип</label>
                        <select name="type" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors bg-white">
                            <option value="dog">Собака</option>
                            <option value="cat">Кошка</option>
                            <option value="other">Другое</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Фото</label>
                        <div class="flex items-center gap-4">
                            <div id="pet-photo-preview" class="w-20 h-20 rounded-xl bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 overflow-hidden flex-shrink-0">
                                <i data-lucide="image" class="w-7 h-7"></i>
                            </div>
                            <div class="flex-1">
                                <button type="button" id="pet-photo-btn" class="btn-hover px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-sm inline-flex items-center gap-2">
                                    <i data-lucide="upload" class="w-4 h-4"></i>
                                    <span>Выбрать и обрезать</span>
                                </button>
                                <input type="file" id="pet-photo-input" accept="image/jpeg,image/png,image/webp" class="hidden">
                                <p class="text-xs text-gray-500 mt-1.5">JPG, PNG или WEBP, до 5 МБ.</p>
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="btn-hover px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500 transition-all duration-300">
                            Добавить
                        </button>
                    </div>
                </form>
            </div>
            <?php elseif ($isOwner && is_restricted($viewer)): ?>
            <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 mb-6 text-rose-700 font-medium text-sm">
                <?= htmlspecialchars(restricted_message($viewer, 'добавление питомцев')) ?>
                <?php if (restricted_reason($viewer) === 'unverified'): ?>
                    <a href="verify.php" class="font-bold underline">Подтвердить email</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!$pets): ?>
                <p class="text-center text-gray-400 py-12">Питомцы пока не добавлены.</p>
            <?php else: ?>
            <?php
            $petsJson = [];
            if ($isOwner) {
                foreach ($pets as $p) {
                    $petsJson[(int)$p['id']] = [
                        'id'    => (int)$p['id'],
                        'name'  => $p['name'],
                        'breed' => $p['breed'],
                        'type'  => $p['type'],
                        'photo' => $p['photo_url'],
                    ];
                }
            }
            ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($pets as $pet): ?>
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden btn-hover border-2 border-gray-100 hover:border-rose-200">
                    <div class="relative h-56 overflow-hidden">
                        <img src="<?= htmlspecialchars($pet['photo_url']) ?>" alt="<?= htmlspecialchars($pet['name']) ?>" class="w-full h-full object-cover">
                        <span class="absolute top-3 left-3 px-3 py-1 bg-white/90 backdrop-blur-sm text-rose-500 text-xs font-bold rounded-full">
                            <?= htmlspecialchars(pet_type_label($pet['type'])) ?>
                        </span>
                    </div>
                    <div class="p-5">
                        <h3 class="font-black text-gray-900 text-lg"><?= htmlspecialchars($pet['name']) ?></h3>
                        <p class="text-gray-500 font-medium text-sm"><?= htmlspecialchars($pet['breed']) ?></p>
                        <?php if ($isOwner): ?>
                            <div class="mt-3 flex items-center gap-3">
                                <button type="button" data-edit-pet="<?= (int)$pet['id'] ?>" class="btn-hover text-xs font-bold text-rose-500 hover:text-rose-700 inline-flex items-center gap-1">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>Редактировать
                                </button>
                                <form method="post" onsubmit="return confirm('Удалить питомца? Если он был в списке чемпионов или записан на события - записи также будут удалены.');">
                                    <input type="hidden" name="action" value="delete_pet">
                                    <input type="hidden" name="pet_id" value="<?= (int)$pet['id'] ?>">
                                    <button type="submit" class="btn-hover text-xs font-bold text-gray-400 hover:text-rose-700">Удалить</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($isOwner): ?>
            <script id="pets-data" type="application/json"><?= json_encode($petsJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
            <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($tab === 'awards'): ?>
            <?php if (!$awards): ?>
                <p class="text-center text-gray-400 py-12">Пока нет наград. Если ваш питомец попадёт в список чемпионов клуба, награды появятся здесь.</p>
            <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($awards as $a): ?>
                <div class="group relative rounded-3xl overflow-hidden shadow-xl btn-hover">
                    <div class="relative h-80">
                        <img src="<?= htmlspecialchars($a['image_url']) ?>" alt="" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>
                        <div class="absolute top-4 right-4 w-12 h-12 bg-amber-400 rounded-full flex items-center justify-center shadow-lg">
                            <i data-lucide="award" class="w-6 h-6 text-amber-900"></i>
                        </div>
                        <div class="absolute bottom-0 left-0 right-0 p-6">
                            <p class="text-amber-400 font-bold text-sm mb-1"><?= htmlspecialchars($a['title']) ?></p>
                            <h3 class="text-white font-black text-xl"><?= htmlspecialchars($a['name']) ?></h3>
                            <p class="text-white/70 text-sm font-medium"><?= htmlspecialchars($a['breed']) ?> · <?= (int)$a['year'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'gallery'): ?>
            <?php if ($isOwner): ?>
                <?php if (!$pets): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 border-2 border-gray-100">
                    <p class="text-gray-600">Сначала добавьте питомца на вкладке «Питомцы», чтобы загружать фото в галерею.</p>
                </div>
                <?php else: ?>
                <?php if (!is_restricted($viewer)): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 border-2 border-gray-100">
                    <h2 class="text-xl font-black text-gray-900 mb-4">Опубликовать пост</h2>
                    <form method="post" id="gallery-form" class="space-y-4">
                        <input type="hidden" name="action" value="add_gallery">
                        <input type="hidden" name="photo_data" id="gallery-photo-data" value="">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Питомец</label>
                                <select name="pet_id" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors bg-white font-bold text-gray-700">
                                    <?php foreach ($pets as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['breed']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Фото (произвольная обрезка)</label>
                                <div class="flex items-center gap-4">
                                    <div id="gallery-photo-preview" class="w-20 h-20 rounded-xl bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 overflow-hidden flex-shrink-0">
                                        <i data-lucide="image" class="w-7 h-7"></i>
                                    </div>
                                    <div class="flex-1">
                                        <button type="button" id="gallery-photo-btn" class="btn-hover px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-sm inline-flex items-center gap-2">
                                            <i data-lucide="upload" class="w-4 h-4"></i>
                                            <span>Выбрать и обрезать</span>
                                        </button>
                                        <input type="file" id="gallery-photo-input" accept="image/jpeg,image/png,image/webp" class="hidden">
                                        <p class="text-xs text-gray-500 mt-1.5">Любое соотношение сторон.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">Подпись (необязательно)</label>
                            <textarea name="caption" rows="3" maxlength="1000" placeholder="Расскажите о фото - текст увидят при открытии поста."
                                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors"></textarea>
                        </div>
                        <button type="submit" class="btn-hover px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500 transition-all duration-300">Опубликовать</button>
                    </form>
                </div>
                <?php else: ?>
                <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 mb-6 text-rose-700 font-medium text-sm">
                    <?= htmlspecialchars(restricted_message($viewer, 'публикация в галерее')) ?>
                    <?php if (restricted_reason($viewer) === 'unverified'): ?>
                        <a href="verify.php" class="font-bold underline">Подтвердить email</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$gallery): ?>
                <p class="text-center text-gray-400 py-12">В галерее пока нет фотографий.</p>
            <?php else: ?>
            <?php
            $galleryJson = [];
            foreach ($gallery as $g) {
                $galleryJson[(int)$g['id']] = [
                    'id'        => (int)$g['id'],
                    'image_url' => $g['image_url'],
                    'caption'   => $g['caption'] ?? '',
                    'pet_name'  => $g['pet_name'],
                    'pet_breed' => $g['pet_breed'],
                    'pet_type'  => pet_type_label($g['pet_type']),
                    'score'     => (int)$g['score'],
                    'my_vote'   => $g['my_vote'] !== null ? (int)$g['my_vote'] : 0,
                    'is_owner'  => $isOwner,
                    'can_admin_delete' => $viewer && (int)($viewer['role'] ?? 0) >= ROLE_ADMIN && !$isOwner,
                    'logged_in' => (bool)$viewer,
                    'restricted' => is_restricted($viewer),
                    'restrict_reason' => restricted_reason($viewer),
                    'is_mine'   => $isOwner,
                    'created_at'=> $g['created_at'],
                ];
            }
            ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($gallery as $g):
                    $score = (int)$g['score'];
                ?>
                <div class="group relative bg-white rounded-2xl overflow-hidden shadow-lg border-2 border-gray-100 hover:border-rose-200 btn-hover cursor-pointer"
                     onclick="openGalleryPost(<?= (int)$g['id'] ?>)">
                    <div class="overflow-hidden bg-gray-100">
                        <img src="<?= htmlspecialchars($g['image_url']) ?>" alt="<?= htmlspecialchars($g['pet_name']) ?>" class="w-full h-auto object-cover" loading="lazy">
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 p-3 bg-gradient-to-t from-black/80 to-transparent pointer-events-none">
                        <p class="text-white font-bold text-sm truncate"><?= htmlspecialchars($g['pet_name']) ?></p>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-white/70 text-xs truncate"><?= htmlspecialchars(pet_type_label($g['pet_type'])) ?></p>
                            <span class="inline-flex items-center gap-1 text-white text-xs font-bold">
                                <i data-lucide="arrow-up" class="w-3 h-3"></i><span data-gallery-tile-score="<?= (int)$g['id'] ?>"><?= $score ?></span>
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($g['caption'])): ?>
                        <span class="absolute top-2 left-2 w-7 h-7 rounded-full bg-white/85 text-gray-700 flex items-center justify-center pointer-events-none" title="Есть подпись">
                            <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <script id="gallery-data" type="application/json"><?= json_encode($galleryJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
            <?php endif; ?>

        <?php elseif ($tab === 'events' && $isOwner): ?>
            <?php if (!$myEvents): ?>
                <p class="text-center text-gray-400 py-12">Вы ещё не записаны ни на одно событие.</p>
            <?php else: ?>
            <?php
            $monthsShort = [
                1=>'ЯНВ',2=>'ФЕВ',3=>'МАР',4=>'АПР',5=>'МАЙ',6=>'ИЮН',
                7=>'ИЮЛ',8=>'АВГ',9=>'СЕН',10=>'ОКТ',11=>'НОЯ',12=>'ДЕК'
            ];
            $myRegistrations = [];
            foreach ($myEvents as $e) $myRegistrations[(int)$e['id']] = $e['pet_name'];
            $cancelAction = 'unregister_event';
            $showParticipantsBtn = true;
            ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($myEvents as $event) include 'includes/event_card.php'; ?>
            </div>
            <script id="participants-data" type="application/json"><?= json_encode($myEventsParticipantsJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
            <?php endif; ?>

        <?php elseif ($tab === 'settings' && $isOwner): ?>
            <div class="bg-white rounded-2xl shadow-lg p-6 border-2 border-gray-100">
                <h2 class="text-xl font-black text-gray-900 mb-4">Настройки профиля</h2>
                <form method="post" id="profile-form" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="avatar_data"   id="avatar-data"   value="">
                    <input type="hidden" name="avatar_remove" id="avatar-remove" value="">

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Аватар</label>
                        <div class="flex items-center gap-4">
                            <div id="avatar-preview-wrap" class="w-24 h-24 rounded-2xl bg-gray-100 border-2 border-gray-200 overflow-hidden flex items-center justify-center text-gray-400 flex-shrink-0">
                                <?php if (!empty($profile['avatar_url'])): ?>
                                    <img id="avatar-preview-img" src="<?= htmlspecialchars($profile['avatar_url']) ?>" alt="" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i data-lucide="user" class="w-10 h-10"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" id="avatar-btn" class="btn-hover px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-sm inline-flex items-center gap-2">
                                    <i data-lucide="upload" class="w-4 h-4"></i>
                                    <span>Загрузить и обрезать</span>
                                </button>
                                <?php if (!empty($profile['avatar_url'])): ?>
                                <button type="button" id="avatar-remove-btn" class="btn-hover px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl text-sm inline-flex items-center gap-2">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    <span>Убрать</span>
                                </button>
                                <?php endif; ?>
                                <input type="file" id="avatar-input" accept="image/jpeg,image/png,image/webp" class="hidden">
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">JPG, PNG или WEBP, до 5 МБ. Аватар обрезается до квадрата.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Имя пользователя (никнейм)</label>
                        <input type="text" name="username" required minlength="3" maxlength="30" pattern="[a-zA-Z0-9_]{3,30}"
                               value="<?= htmlspecialchars($profile['username']) ?>"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                        <p class="text-xs text-gray-500 mt-1">3–30 символов: латиница, цифры и _.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Email</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($profile['email'] ?? '') ?>"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                        <?php if (mail_enabled()): ?>
                            <p class="text-xs text-gray-500 mt-1">При смене email потребуется заново подтвердить адрес - на новый email придёт 6-значный код.</p>
                        <?php endif; ?>
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">Имя</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">Фамилия</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                        </div>
                    </div>
                    <button type="submit" class="btn-hover px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500 transition-all duration-300">Сохранить</button>
                </form>
            </div>
        <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($isOwner): ?>
<!-- Edit pet modal -->
<div id="edit-pet-modal" class="hidden fixed inset-0 z-[65] bg-black/70 backdrop-blur-sm items-center justify-center p-3 sm:p-6">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full border-2 border-gray-100 flex flex-col overflow-hidden" style="max-height: calc(100dvh - 1.5rem);">
        <div class="p-5 border-b-2 border-gray-100 flex items-center justify-between flex-shrink-0">
            <h3 class="text-lg font-black text-gray-900">Редактировать питомца</h3>
            <button type="button" id="edit-pet-close" class="btn-hover w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5 text-gray-700"></i>
            </button>
        </div>
        <form method="post" id="edit-pet-form" class="p-5 space-y-4 overflow-y-auto">
            <input type="hidden" name="action" value="edit_pet">
            <input type="hidden" name="pet_id"     id="edit-pet-id"     value="">
            <input type="hidden" name="photo_data" id="edit-pet-photo-data" value="">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Кличка</label>
                <input type="text" name="name" id="edit-pet-name" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Порода</label>
                <input type="text" name="breed" id="edit-pet-breed" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Тип</label>
                <select name="type" id="edit-pet-type" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors bg-white">
                    <option value="dog">Собака</option>
                    <option value="cat">Кошка</option>
                    <option value="other">Другое</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Фото</label>
                <div class="flex items-center gap-4">
                    <div id="edit-pet-photo-preview" class="w-20 h-20 rounded-xl bg-gray-100 border-2 border-gray-200 flex items-center justify-center text-gray-400 overflow-hidden flex-shrink-0">
                        <i data-lucide="image" class="w-7 h-7"></i>
                    </div>
                    <div class="flex-1">
                        <button type="button" id="edit-pet-photo-btn" class="btn-hover px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-sm inline-flex items-center gap-2">
                            <i data-lucide="upload" class="w-4 h-4"></i>
                            <span>Заменить фото</span>
                        </button>
                        <input type="file" id="edit-pet-photo-input" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <p class="text-xs text-gray-500 mt-1.5">Оставьте пустым, чтобы сохранить текущее фото.</p>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button type="button" id="edit-pet-cancel" class="btn-hover px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-sm">Отмена</button>
                <button type="submit" class="btn-hover px-5 py-2.5 bg-rose-400 hover:bg-rose-500 text-white font-bold rounded-xl text-sm">Сохранить</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Crop modal -->
<div id="crop-modal" class="hidden fixed inset-0 z-[70] bg-black/70 backdrop-blur-sm items-center justify-center p-3 sm:p-6">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full border-2 border-gray-100 flex flex-col overflow-hidden" style="max-height: calc(100vh - 1.5rem); max-height: calc(100dvh - 1.5rem);">
        <div class="p-5 border-b-2 border-gray-100 flex items-center justify-between flex-shrink-0">
            <h3 class="text-lg font-black text-gray-900">Обрезка изображения</h3>
            <button type="button" id="crop-close" class="btn-hover w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5 text-gray-700"></i>
            </button>
        </div>
        <div class="p-5 flex-1 min-h-0 bg-gray-50 overflow-auto">
            <div class="max-w-full max-h-[55vh] mx-auto">
                <img id="crop-image" src="" alt="" class="block max-w-full">
            </div>
        </div>
        <div class="p-5 border-t-2 border-gray-100 flex flex-wrap gap-2 justify-between flex-shrink-0">
            <div class="flex gap-2">
                <button type="button" data-crop-action="rotate-left" class="btn-hover px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-700"><i data-lucide="rotate-ccw" class="w-4 h-4"></i></button>
                <button type="button" data-crop-action="rotate-right" class="btn-hover px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-700"><i data-lucide="rotate-cw" class="w-4 h-4"></i></button>
                <button type="button" data-crop-action="reset" class="btn-hover px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-700 text-sm font-bold">Сброс</button>
            </div>
            <div class="flex gap-2">
                <button type="button" id="crop-cancel" class="btn-hover px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-700 font-bold text-sm">Отмена</button>
                <button type="button" id="crop-apply"  class="btn-hover px-4 py-2 bg-rose-400 hover:bg-rose-500 text-white rounded-xl font-bold text-sm">Применить</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal     = document.getElementById('crop-modal');
    const cropImg   = document.getElementById('crop-image');
    const closeBtn  = document.getElementById('crop-close');
    const cancelBtn = document.getElementById('crop-cancel');
    const applyBtn  = document.getElementById('crop-apply');

    let cropper = null;
    let pending = null; // { aspectRatio, outSize, onApply }

    function openCrop(file, opts, onApply) {
        const reader = new FileReader();
        reader.onload = (e) => {
            cropImg.src = e.target.result;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            if (cropper) cropper.destroy();
            cropper = new Cropper(cropImg, {
                aspectRatio: opts.freeCrop ? NaN : opts.aspectRatio,
                viewMode: 1,
                autoCropArea: 1,
                background: false,
                movable: true,
                zoomable: true,
            });
            pending = { ...opts, onApply };
        };
        reader.readAsDataURL(file);
    }

    function closeCrop() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        if (cropper) { cropper.destroy(); cropper = null; }
        pending = null;
    }

    closeBtn.addEventListener('click', closeCrop);
    cancelBtn.addEventListener('click', closeCrop);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeCrop(); });

    document.querySelectorAll('[data-crop-action]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!cropper) return;
            const a = btn.dataset.cropAction;
            if (a === 'rotate-left')  cropper.rotate(-90);
            if (a === 'rotate-right') cropper.rotate(90);
            if (a === 'reset')        cropper.reset();
        });
    });

    applyBtn.addEventListener('click', () => {
        if (!cropper || !pending) return;
        const canvasOpts = { imageSmoothingQuality: 'high' };
        if (!pending.freeCrop) {
            canvasOpts.width  = pending.outSize;
            canvasOpts.height = pending.outSize / (pending.aspectRatio || 1);
        } else {
            const data = cropper.getCropBoxData();
            const ratio = data.width / data.height;
            const max = pending.outSize || 1600;
            if (ratio >= 1) { canvasOpts.width = max; canvasOpts.height = Math.round(max / ratio); }
            else            { canvasOpts.height = max; canvasOpts.width  = Math.round(max * ratio); }
        }
        const canvas = cropper.getCroppedCanvas(canvasOpts);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        pending.onApply(dataUrl);
        closeCrop();
    });

    // ---- Avatar (settings) ----
    const avatarBtn   = document.getElementById('avatar-btn');
    const avatarInput = document.getElementById('avatar-input');
    const avatarData  = document.getElementById('avatar-data');
    const avatarRemove= document.getElementById('avatar-remove');
    const avatarRemBtn= document.getElementById('avatar-remove-btn');
    const avatarWrap  = document.getElementById('avatar-preview-wrap');

    if (avatarBtn) {
        avatarBtn.addEventListener('click', () => avatarInput.click());
        avatarInput.addEventListener('change', () => {
            const f = avatarInput.files[0];
            if (!f) return;
            openCrop(f, { aspectRatio: 1, outSize: 512 }, (dataUrl) => {
                avatarData.value   = dataUrl;
                avatarRemove.value = '';
                avatarWrap.innerHTML = '<img src="' + dataUrl + '" class="w-full h-full object-cover" alt="">';
            });
            avatarInput.value = '';
        });
    }
    if (avatarRemBtn) {
        avatarRemBtn.addEventListener('click', () => {
            avatarData.value   = '';
            avatarRemove.value = '1';
            avatarWrap.innerHTML = '<i data-lucide="user" class="w-10 h-10"></i>';
            if (window.lucide) lucide.createIcons();
        });
    }

    // ---- Gallery photo ----
    const galBtn   = document.getElementById('gallery-photo-btn');
    const galInput = document.getElementById('gallery-photo-input');
    const galData  = document.getElementById('gallery-photo-data');
    const galPrev  = document.getElementById('gallery-photo-preview');

    if (galBtn) {
        galBtn.addEventListener('click', () => galInput.click());
        galInput.addEventListener('change', () => {
            const f = galInput.files[0];
            if (!f) return;
            openCrop(f, { aspectRatio: NaN, outSize: 1600, freeCrop: true }, (dataUrl) => {
                galData.value = dataUrl;
                galPrev.innerHTML = '<img src="' + dataUrl + '" class="w-full h-full object-cover" alt="">';
            });
            galInput.value = '';
        });
        document.getElementById('gallery-form').addEventListener('submit', (e) => {
            if (!galData.value) {
                e.preventDefault();
                alert('Сначала загрузите и обрежьте фотографию.');
            }
        });
    }

    // ---- Pet photo ----
    const petBtn   = document.getElementById('pet-photo-btn');
    const petInput = document.getElementById('pet-photo-input');
    const petData  = document.getElementById('pet-photo-data');
    const petPrev  = document.getElementById('pet-photo-preview');

    if (petBtn) {
        petBtn.addEventListener('click', () => petInput.click());
        petInput.addEventListener('change', () => {
            const f = petInput.files[0];
            if (!f) return;
            openCrop(f, { aspectRatio: 4 / 3, outSize: 1024 }, (dataUrl) => {
                petData.value = dataUrl;
                petPrev.innerHTML = '<img src="' + dataUrl + '" class="w-full h-full object-cover" alt="">';
            });
            petInput.value = '';
        });
        document.getElementById('pet-form').addEventListener('submit', (e) => {
            if (!petData.value) {
                e.preventDefault();
                alert('Сначала загрузите и обрежьте фотографию питомца.');
            }
        });
    }

    // ---- Edit pet ----
    const editModal     = document.getElementById('edit-pet-modal');
    const editIdInput   = document.getElementById('edit-pet-id');
    const editName      = document.getElementById('edit-pet-name');
    const editBreed     = document.getElementById('edit-pet-breed');
    const editType      = document.getElementById('edit-pet-type');
    const editPhotoData = document.getElementById('edit-pet-photo-data');
    const editPhotoPrev = document.getElementById('edit-pet-photo-preview');
    const editPhotoBtn  = document.getElementById('edit-pet-photo-btn');
    const editPhotoInput= document.getElementById('edit-pet-photo-input');
    const editCloseBtn  = document.getElementById('edit-pet-close');
    const editCancelBtn = document.getElementById('edit-pet-cancel');

    function openEditPetModal(id) {
        if (!window.__petsData) {
            const el = document.getElementById('pets-data');
            try { window.__petsData = el ? JSON.parse(el.textContent || '{}') : {}; }
            catch (e) { window.__petsData = {}; }
        }
        const p = window.__petsData[String(id)] || window.__petsData[id];
        if (!p) return;
        editIdInput.value   = p.id;
        editName.value      = p.name;
        editBreed.value     = p.breed;
        editType.value      = p.type;
        editPhotoData.value = '';
        editPhotoPrev.innerHTML = p.photo
            ? '<img src="' + p.photo + '" class="w-full h-full object-cover" alt="">'
            : '<i data-lucide="image" class="w-7 h-7"></i>';
        editModal.classList.remove('hidden');
        editModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        if (window.lucide) lucide.createIcons();
    }
    function closeEditPetModal() {
        editModal.classList.add('hidden');
        editModal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    if (editModal) {
        document.querySelectorAll('[data-edit-pet]').forEach(btn => {
            btn.addEventListener('click', () => openEditPetModal(btn.dataset.editPet));
        });
        editCloseBtn.addEventListener('click', closeEditPetModal);
        editCancelBtn.addEventListener('click', closeEditPetModal);
        editModal.addEventListener('click', (e) => { if (e.target === editModal) closeEditPetModal(); });

        editPhotoBtn.addEventListener('click', () => editPhotoInput.click());
        editPhotoInput.addEventListener('change', () => {
            const f = editPhotoInput.files[0];
            if (!f) return;
            openCrop(f, { aspectRatio: 4 / 3, outSize: 1024 }, (dataUrl) => {
                editPhotoData.value = dataUrl;
                editPhotoPrev.innerHTML = '<img src="' + dataUrl + '" class="w-full h-full object-cover" alt="">';
            });
            editPhotoInput.value = '';
        });
    }
})();
</script>

<!-- Gallery post modal -->
<div id="gallery-modal" class="hidden fixed inset-0 z-[60] bg-black/80 backdrop-blur-sm items-center justify-center p-3 sm:p-6">
    <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full relative animate-fade-in border-2 border-gray-100 flex flex-col lg:flex-row overflow-hidden" style="max-height: calc(100dvh - 1.5rem);">
        <button type="button" onclick="closeGalleryPost()" class="btn-hover absolute top-3 right-3 z-20 w-10 h-10 rounded-full bg-white/90 hover:bg-white border border-gray-200 flex items-center justify-center shadow-lg">
            <i data-lucide="x" class="w-5 h-5 text-gray-700"></i>
        </button>
        <div class="flex-1 min-h-[40vh] lg:min-h-0 bg-gray-100 flex items-center justify-center overflow-hidden">
            <img id="gp-image" src="" alt="" class="max-w-full max-h-full object-contain">
        </div>
        <div class="lg:w-80 flex flex-col flex-shrink-0 border-t-2 lg:border-t-0 lg:border-l-2 border-gray-100 max-h-[40vh] lg:max-h-none overflow-y-auto">
            <div class="p-5 border-b-2 border-gray-100">
                <p class="font-black text-gray-900 text-lg" id="gp-pet-name"></p>
                <p class="text-xs text-gray-500 mt-0.5"><span id="gp-pet-breed"></span> · <span id="gp-pet-type"></span></p>
            </div>
            <div class="p-5 flex-1">
                <p id="gp-caption" class="text-gray-700 text-sm whitespace-pre-line leading-relaxed"></p>
                <p id="gp-no-caption" class="text-gray-400 text-sm italic">Без подписи.</p>
            </div>
            <div class="p-4 border-t-2 border-gray-100 flex items-center justify-between gap-2 bg-gray-50">
                <div id="gp-vote-area" class="flex items-center gap-2"></div>
                <div id="gp-actions" class="flex items-center gap-2"></div>
            </div>
        </div>
    </div>
</div>

<script>
function openGalleryPost(id) {
    if (!window.__galleryData) {
        const el = document.getElementById('gallery-data');
        try { window.__galleryData = el ? JSON.parse(el.textContent || '{}') : {}; }
        catch (e) { window.__galleryData = {}; }
    }
    const data = window.__galleryData;
    const p = data[String(id)] || data[id];
    if (!p) return;

    document.getElementById('gp-image').src = p.image_url;
    document.getElementById('gp-pet-name').textContent = p.pet_name;
    document.getElementById('gp-pet-breed').textContent = p.pet_breed || '';
    document.getElementById('gp-pet-type').textContent = p.pet_type || '';
    const cap = document.getElementById('gp-caption');
    const noCap = document.getElementById('gp-no-caption');
    if (p.caption && p.caption.length) { cap.textContent = p.caption; cap.classList.remove('hidden'); noCap.classList.add('hidden'); }
    else { cap.classList.add('hidden'); noCap.classList.remove('hidden'); }

    const voteArea = document.getElementById('gp-vote-area');
    const actions  = document.getElementById('gp-actions');

    if (!p.logged_in || p.restricted) {
        let tip = 'Войдите для голосования';
        if (p.restrict_reason === 'banned') tip = 'Аккаунт заблокирован';
        else if (p.restrict_reason === 'unverified') tip = 'Подтвердите email для голосования';
        voteArea.innerHTML = `<div title="${escapeHtml(tip)}" class="flex items-center gap-1 px-3 py-2 rounded-xl bg-gray-100 text-gray-500"><i data-lucide="arrow-up" class="w-4 h-4"></i><span class="text-sm font-bold">${p.score}</span><i data-lucide="arrow-down" class="w-4 h-4 ml-1"></i></div>`;
    } else {
        const upCls = p.my_vote === 1 ? 'bg-rose-500 text-white hover:bg-rose-600' : 'bg-rose-50 text-rose-500 hover:bg-rose-100';
        const dnCls = p.my_vote === -1 ? 'bg-gray-700 text-white hover:bg-gray-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200';
        voteArea.innerHTML = `
            <button type="button" data-vote-btn data-id="${p.id}" data-vote="1" class="btn-hover w-9 h-9 rounded-xl ${upCls} flex items-center justify-center"><i data-lucide="arrow-up" class="w-4 h-4"></i></button>
            <span data-vote-score="${p.id}" class="text-sm font-black text-gray-800 min-w-[1.5rem] text-center">${p.score}</span>
            <button type="button" data-vote-btn data-id="${p.id}" data-vote="-1" class="btn-hover w-9 h-9 rounded-xl ${dnCls} flex items-center justify-center"><i data-lucide="arrow-down" class="w-4 h-4"></i></button>`;
    }

    let actHtml = '';
    if (p.is_owner) {
        actHtml = `<form method="post" onsubmit="return confirm('Удалить пост?');">
            <input type="hidden" name="action" value="delete_gallery">
            <input type="hidden" name="gallery_id" value="${p.id}">
            <button type="submit" class="btn-hover px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-xs inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i>Удалить</button>
        </form>`;
    } else if (p.can_admin_delete) {
        actHtml = `<form method="post" onsubmit="return confirm('Удалить пост пользователя как администратор?');">
            <input type="hidden" name="action" value="admin_delete_gallery">
            <input type="hidden" name="gallery_id" value="${p.id}">
            <button type="submit" class="btn-hover px-3 py-2 rounded-xl bg-rose-100 hover:bg-rose-200 text-rose-700 font-bold text-xs inline-flex items-center gap-1"><i data-lucide="shield" class="w-3.5 h-3.5"></i>Удалить</button>
        </form>`;
    }
    actions.innerHTML = actHtml;

    const m = document.getElementById('gallery-modal');
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
    if (window.lucide) lucide.createIcons();
}
function closeGalleryPost() {
    const m = document.getElementById('gallery-modal');
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeGalleryPost(); });
document.getElementById('gallery-modal').addEventListener('click', e => {
    if (e.target.id === 'gallery-modal') closeGalleryPost();
});
function escapeHtml(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

document.getElementById('gallery-modal').addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-vote-btn]');
    if (!btn) return;
    e.preventDefault();
    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';
    try {
        const fd = new FormData();
        fd.append('action', 'gallery_vote');
        fd.append('gallery_id', btn.dataset.id);
        fd.append('vote', btn.dataset.vote);
        const res = await fetch(window.location.pathname + window.location.search, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.ok) return;
        const id = btn.dataset.id;
        const post = window.__galleryData && (window.__galleryData[String(id)] || window.__galleryData[id]);
        if (post) { post.score = data.score; post.my_vote = data.my_vote; }
        const upBtn = document.querySelector(`[data-vote-btn][data-id="${id}"][data-vote="1"]`);
        const dnBtn = document.querySelector(`[data-vote-btn][data-id="${id}"][data-vote="-1"]`);
        if (upBtn) upBtn.className = 'btn-hover w-9 h-9 rounded-xl flex items-center justify-center ' +
            (data.my_vote === 1 ? 'bg-rose-500 text-white hover:bg-rose-600' : 'bg-rose-50 text-rose-500 hover:bg-rose-100');
        if (dnBtn) dnBtn.className = 'btn-hover w-9 h-9 rounded-xl flex items-center justify-center ' +
            (data.my_vote === -1 ? 'bg-gray-700 text-white hover:bg-gray-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200');
        const scoreEl = document.querySelector(`[data-vote-score="${id}"]`);
        if (scoreEl) scoreEl.textContent = data.score;
        const tile = document.querySelector(`[data-gallery-tile-score="${id}"]`);
        if (tile) tile.textContent = data.score;
    } finally {
        btn.dataset.busy = '';
    }
});
</script>

<?php endif; ?>

<?php
$viewer = $viewer ?? current_user();
$userPets = $pets ?? [];
include 'includes/event_modals.php';
?>
<?php include 'includes/footer.php'; ?>
<?php include 'includes/page_scripts.php'; ?>
</body>
</html>
