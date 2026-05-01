<?php
require_once 'includes/auth.php';

$viewer = current_user();
$flash = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$viewer) {
        header('Location: login.php');
        exit;
    }
    $action  = $_POST['action'] ?? '';
    $eventId = (int)($_POST['event_id'] ?? 0);

    if ($action === 'register') {
        if (is_restricted($viewer)) {
            $errors[] = restricted_message($viewer, 'запись на события');
        } else {
        $petId = (int)($_POST['pet_id'] ?? 0);
        $petCheck = $pdo->prepare("SELECT id FROM pets WHERE id = :pid AND user_id = :uid");
        $petCheck->execute([':pid' => $petId, ':uid' => $viewer['id']]);
        if (!$petCheck->fetch()) {
            $errors[] = 'Сначала добавьте питомца в профиле, чтобы записаться на событие.';
        } else {
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO event_registrations (event_id, user_id, pet_id)
                     VALUES (:e, :u, :p)"
                );
                $ins->execute([':e' => $eventId, ':u' => $viewer['id'], ':p' => $petId]);
                $flash = 'Вы успешно записаны на событие.';
            } catch (PDOException $e) {
                if ((int)$e->errorInfo[1] === 1062) {
                    $errors[] = 'Вы уже записаны на это событие.';
                } else {
                    throw $e;
                }
            }
        }
        }
    } elseif ($action === 'unregister') {
        $del = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = :e AND user_id = :u");
        $del->execute([':e' => $eventId, ':u' => $viewer['id']]);
        $flash = 'Запись отменена.';
    } elseif ($action === 'support') {
        $petId = (int)($_POST['pet_id'] ?? 0);
        $resp = ['ok' => false];
        $st = $pdo->prepare("SELECT is_completed FROM events WHERE id = :e");
        $st->execute([':e' => $eventId]);
        $ev = $st->fetch();
        if (!$ev) {
            $resp['error'] = 'not_found';
        } elseif ((int)$ev['is_completed'] === 1) {
            $resp['error'] = 'closed';
        } elseif (is_restricted($viewer)) {
            $resp['error'] = restricted_reason($viewer);
        } else {
            $st = $pdo->prepare(
                "SELECT p.user_id FROM event_registrations r
                  JOIN pets p ON p.id = r.pet_id
                 WHERE r.event_id = :e AND r.pet_id = :p"
            );
            $st->execute([':e' => $eventId, ':p' => $petId]);
            $row = $st->fetch();
            if (!$row) {
                $resp['error'] = 'not_participant';
            } elseif ((int)$row['user_id'] === (int)$viewer['id']) {
                $resp['error'] = 'self';
            } else {
                $cur = $pdo->prepare("SELECT pet_id FROM event_supports WHERE event_id = :e AND user_id = :u");
                $cur->execute([':e' => $eventId, ':u' => $viewer['id']]);
                $existing = $cur->fetch();
                if ($existing && (int)$existing['pet_id'] === $petId) {
                    $del = $pdo->prepare("DELETE FROM event_supports WHERE event_id = :e AND user_id = :u");
                    $del->execute([':e' => $eventId, ':u' => $viewer['id']]);
                    $resp['my_support'] = null;
                } else {
                    $up = $pdo->prepare(
                        "INSERT INTO event_supports (event_id, user_id, pet_id) VALUES (:e, :u, :p)
                         ON DUPLICATE KEY UPDATE pet_id = VALUES(pet_id), created_at = CURRENT_TIMESTAMP"
                    );
                    $up->execute([':e' => $eventId, ':u' => $viewer['id'], ':p' => $petId]);
                    $resp['my_support'] = $petId;
                }
                $counts = $pdo->prepare(
                    "SELECT p.id AS pet_id, COUNT(s.user_id) AS cnt
                       FROM event_registrations r
                       JOIN pets p ON p.id = r.pet_id
                  LEFT JOIN event_supports s ON s.event_id = r.event_id AND s.pet_id = p.id
                      WHERE r.event_id = :e
                   GROUP BY p.id"
                );
                $counts->execute([':e' => $eventId]);
                $byPet = [];
                $total = 0;
                foreach ($counts->fetchAll() as $c) {
                    $byPet[(int)$c['pet_id']] = (int)$c['cnt'];
                    $total += (int)$c['cnt'];
                }
                $resp['ok'] = true;
                $resp['counts'] = $byPet;
                $resp['total']  = $total;
            }
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        exit;
    }
}

$monthsShort = [
    1=>'ЯНВ',2=>'ФЕВ',3=>'МАР',4=>'АПР',5=>'МАЙ',6=>'ИЮН',
    7=>'ИЮЛ',8=>'АВГ',9=>'СЕН',10=>'ОКТ',11=>'НОЯ',12=>'ДЕК'
];
$monthsFull = [
    1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',
    7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'
];

$latest = $pdo->query(
    "SELECT * FROM events
     ORDER BY CASE WHEN event_date >= CURDATE() THEN 0 ELSE 1 END, event_date ASC
     LIMIT 1"
)->fetch();

$stmtRest = $pdo->prepare("SELECT * FROM events WHERE id != :id ORDER BY event_date ASC");
$stmtRest->execute([':id' => $latest ? $latest['id'] : 0]);
$rest = $stmtRest->fetchAll();

$userPets = [];
$myRegistrations = [];
if ($viewer) {
    $ps = $pdo->prepare("SELECT id, name FROM pets WHERE user_id = :uid ORDER BY created_at DESC");
    $ps->execute([':uid' => $viewer['id']]);
    $userPets = $ps->fetchAll();

    $rs = $pdo->prepare(
        "SELECT r.event_id, p.name AS pet_name
           FROM event_registrations r
           JOIN pets p ON p.id = r.pet_id
          WHERE r.user_id = :uid"
    );
    $rs->execute([':uid' => $viewer['id']]);
    foreach ($rs->fetchAll() as $r) {
        $myRegistrations[(int)$r['event_id']] = $r['pet_name'];
    }
}
$registeredLatest = $latest && isset($myRegistrations[(int)$latest['id']]);

// Build per-event participants list with support counts
$allEvents = array_filter(array_merge([$latest], $rest));
$eventIds = array_map(fn($e) => (int)$e['id'], $allEvents);

$participantsByEvent = [];
$supportTotalsByEvent = [];
$mySupports = [];

if ($eventIds) {
    $in = implode(',', $eventIds);
    $rs = $pdo->query(
        "SELECT r.event_id, p.id AS pet_id, p.name AS pet_name, p.breed AS pet_breed, p.photo_url, p.user_id,
                u.username, u.first_name, u.last_name, u.avatar_url,
                (SELECT COUNT(*) FROM event_supports s WHERE s.event_id = r.event_id AND s.pet_id = p.id) AS support_count
           FROM event_registrations r
           JOIN pets p  ON p.id = r.pet_id
           JOIN users u ON u.id = p.user_id
          WHERE r.event_id IN ($in)
          ORDER BY support_count DESC, r.created_at ASC"
    )->fetchAll();
    foreach ($rs as $row) {
        $eid = (int)$row['event_id'];
        $participantsByEvent[$eid][] = $row;
        $supportTotalsByEvent[$eid] = ($supportTotalsByEvent[$eid] ?? 0) + (int)$row['support_count'];
    }

    if ($viewer) {
        $sQ = $pdo->prepare("SELECT event_id, pet_id FROM event_supports WHERE user_id = :u AND event_id IN ($in)");
        $sQ->execute([':u' => $viewer['id']]);
        foreach ($sQ->fetchAll() as $r) {
            $mySupports[(int)$r['event_id']] = (int)$r['pet_id'];
        }
    }
}

// JSON payload for the participants modal
$participantsJson = [];
foreach ($allEvents as $ev) {
    $eid = (int)$ev['id'];
    $list = $participantsByEvent[$eid] ?? [];
    $items = [];
    $myPetIds = [];
    foreach ($list as $p) {
        $owner_full = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
        if ($viewer && (int)$p['user_id'] === (int)$viewer['id']) {
            $myPetIds[] = (int)$p['pet_id'];
        }
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
    $participantsJson[$eid] = [
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>События - PetLove Club</title>
    <meta name="description" content="Расписание выставок, семинаров и встреч PetLove Club. Регистрируйтесь онлайн, выбирайте питомца и забронируйте место - количество ограничено.">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="./styles/tailwind.css">
    <link rel="shortcut icon" href="./favicon.png" type="image/x-icon">
</head>
<body class="bg-amber-50 min-h-screen">

    <?php $active = 'events'; include 'includes/nav.php'; ?>

    <section class="pt-32 pb-8 px-4">
        <div class="max-w-7xl mx-auto text-center">
            <div class="inline-flex items-center space-x-2 px-4 py-2 bg-rose-100 rounded-full mb-5">
                <i data-lucide="calendar" class="w-4 h-4 text-rose-500"></i>
                <span class="text-sm font-bold text-rose-700">Расписание</span>
            </div>
            <h1 class="text-5xl lg:text-6xl font-black text-gray-900 mb-5">Ближайшие события</h1>
            <p class="text-xl text-gray-600 font-medium max-w-2xl mx-auto">
                Зарегистрируйтесь прямо сейчас - места ограничены
            </p>
        </div>
    </section>

    <?php if ($errors || $flash): ?>
    <section class="px-4 mb-6">
        <div class="max-w-3xl mx-auto">
            <?php if ($errors): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl mb-3">
                    <?php foreach ($errors as $e): ?>
                        <p class="text-rose-600 text-sm font-medium"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($flash): ?>
                <div class="p-4 bg-green-50 border border-green-200 rounded-xl">
                    <p class="text-green-700 text-sm font-medium"><?= htmlspecialchars($flash) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($latest):
        $ts         = strtotime($latest['event_date']);
        $dayNum     = date('j', $ts);
        $monthShort = $monthsShort[(int)date('n', $ts)];
        $dateStr    = $dayNum . ' ' . $monthsFull[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    ?>
    <section class="pb-12 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="relative rounded-2xl sm:rounded-3xl overflow-hidden shadow-2xl min-h-[380px] sm:min-h-[420px] flex items-end">
                <img src="<?= htmlspecialchars($latest['image_url']) ?>"
                     alt="<?= htmlspecialchars($latest['title']) ?>"
                     class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-black/10"></div>

                <div class="absolute top-4 sm:top-6 left-4 sm:left-6">
                    <span class="px-3 sm:px-4 py-1.5 sm:py-2 bg-rose-400 text-white text-xs sm:text-sm font-bold rounded-full shadow-lg">
                        ⭐ Ближайшее событие
                    </span>
                </div>

                <div class="relative z-10 w-full p-5 sm:p-8 lg:p-12 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5 lg:gap-6">
                    <div class="flex items-end gap-4 sm:gap-6 min-w-0">
                        <div class="w-16 h-16 sm:w-24 sm:h-24 bg-rose-400 rounded-xl sm:rounded-2xl flex flex-col items-center justify-center text-white shadow-xl flex-shrink-0">
                            <div class="text-[10px] sm:text-sm font-bold"><?= $monthShort ?></div>
                            <div class="text-2xl sm:text-4xl font-black leading-none"><?= $dayNum ?></div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-white font-black text-xl sm:text-3xl lg:text-4xl mb-2 sm:mb-3 leading-tight break-words">
                                <?= htmlspecialchars($latest['title']) ?>
                            </h2>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 sm:gap-4 text-white/80 font-medium text-xs sm:text-sm">
                                <span class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0"></i><?= $dateStr ?></span>
                                <span class="flex items-center gap-1.5 min-w-0"><i data-lucide="map-pin"  class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0"></i><span class="truncate"><?= htmlspecialchars($latest['location']) ?></span></span>
                                <span class="flex items-center gap-1.5"><i data-lucide="users"    class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0"></i><?= (int)$latest['seats'] ?> мест</span>
                            </div>
                            <?php if (!empty($latest['description'])): ?>
                                <p class="text-white/65 mt-2 sm:mt-3 max-w-2xl text-xs sm:text-sm leading-relaxed line-clamp-3 sm:line-clamp-none"><?= htmlspecialchars($latest['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex flex-row lg:flex-row gap-2 sm:gap-3 flex-shrink-0 items-center w-full lg:w-auto">
                        <?php $latestTitleAttr = htmlspecialchars($latest['title'], ENT_QUOTES); ?>
                        <button type="button"
                                onclick="openParticipantsModal(<?= (int)$latest['id'] ?>)"
                                title="Участники"
                                class="btn-hover w-12 h-12 sm:w-14 sm:h-14 bg-white/95 text-gray-800 rounded-xl sm:rounded-2xl hover:bg-white shadow-xl flex items-center justify-center flex-shrink-0">
                            <i data-lucide="users" class="w-5 h-5 sm:w-6 sm:h-6"></i>
                        </button>
                        <?php if ((int)$latest['is_completed'] === 1): ?>
                            <span class="flex-1 lg:flex-initial px-5 sm:px-7 py-3 sm:py-4 bg-gray-800/80 text-white font-bold rounded-xl sm:rounded-2xl text-center text-sm sm:text-base shadow-xl">
                                Событие прошло
                            </span>
                        <?php elseif ($registeredLatest): ?>
                            <button type="button"
                                    onclick="openCancelModal(<?= (int)$latest['id'] ?>, '<?= $latestTitleAttr ?>', '<?= htmlspecialchars($myRegistrations[(int)$latest['id']], ENT_QUOTES) ?>')"
                                    class="btn-hover flex-1 lg:flex-initial px-5 sm:px-7 py-3 sm:py-4 bg-white/95 text-gray-800 font-bold rounded-xl sm:rounded-2xl hover:bg-white shadow-xl text-sm sm:text-base">
                                Отменить запись
                            </button>
                        <?php else: ?>
                            <button type="button"
                                    onclick="openSignupModal(<?= (int)$latest['id'] ?>, '<?= $latestTitleAttr ?>')"
                                    class="btn-hover flex-1 lg:flex-initial px-5 sm:px-7 py-3 sm:py-4 bg-rose-400 text-white font-bold rounded-xl sm:rounded-2xl hover:bg-rose-500 shadow-xl text-sm sm:text-base">
                                Записаться
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($rest)): ?>
    <section class="pb-28 px-4">
        <div class="max-w-7xl mx-auto">
            <h2 class="text-2xl font-black text-gray-900 mb-8">Все события</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($rest as $event) include 'includes/event_card.php'; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <script id="participants-data" type="application/json"><?= json_encode($participantsJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <?php include 'includes/event_modals.php'; ?>
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/page_scripts.php'; ?>
</body>
</html>
