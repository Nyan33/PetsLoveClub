<?php
require_once __DIR__ . '/_init.php';

/**
 * If an event has a winner, ensure a corresponding champion row exists for that pet.
 * If the winner was changed/removed, the previous auto-created champion is removed.
 */
function admin_promote_winner_to_champion(PDO $pdo, int $eventId, ?int $winnerPetId, string $eventTitle, string $eventDate): void {
    $del = $pdo->prepare("SELECT id, image_url, pet_id FROM champions WHERE event_id = :e");
    $del->execute([':e' => $eventId]);
    $existing = $del->fetch();

    if ($winnerPetId === null) {
        if ($existing) {
            $pdo->prepare("DELETE FROM champions WHERE id = :id")->execute([':id' => $existing['id']]);
            if (!empty($existing['image_url']) && str_starts_with($existing['image_url'], 'uploads/')) {
                delete_local_upload($existing['image_url']);
            }
        }
        return;
    }

    $st = $pdo->prepare("SELECT name, breed, photo_url FROM pets WHERE id = :p");
    $st->execute([':p' => $winnerPetId]);
    $pet = $st->fetch();
    if (!$pet) return;

    $title = 'Победитель: ' . $eventTitle;
    $year  = (int)date('Y', strtotime($eventDate ?: 'now'));

    if ($existing) {
        if ((int)$existing['pet_id'] === $winnerPetId) {
            $pdo->prepare("UPDATE champions SET title = :t, year = :y WHERE id = :id")
                ->execute([':t' => $title, ':y' => $year, ':id' => $existing['id']]);
        } else {
            $pdo->prepare("UPDATE champions SET name = :n, breed = :b, title = :t, year = :y, pet_id = :p, image_url = :i WHERE id = :id")
                ->execute([
                    ':n' => $pet['name'], ':b' => $pet['breed'], ':t' => $title, ':y' => $year,
                    ':p' => $winnerPetId, ':i' => $pet['photo_url'], ':id' => $existing['id'],
                ]);
        }
    } else {
        $pdo->prepare("INSERT INTO champions (name, breed, title, image_url, year, pet_id, event_id) VALUES (:n, :b, :t, :i, :y, :p, :e)")
            ->execute([
                ':n' => $pet['name'], ':b' => $pet['breed'], ':t' => $title,
                ':i' => $pet['photo_url'], ':y' => $year, ':p' => $winnerPetId, ':e' => $eventId,
            ]);
    }
}

$editId = (int)($_GET['id'] ?? 0);
$mode   = $_GET['mode'] ?? 'list'; // list | edit | new

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $action = $_POST['action'] ?? '';
    $eid    = (int)($_POST['event_id'] ?? 0);

    if ($action === 'save') {
        $title       = trim($_POST['title']    ?? '');
        $eventDate   = trim($_POST['event_date'] ?? '');
        $location    = trim($_POST['location'] ?? '');
        $seats       = (int)($_POST['seats'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $imageData   = $_POST['image_data']  ?? '';
        $imageUrl    = trim($_POST['image_url'] ?? '');
        $isCompleted = !empty($_POST['is_completed']) ? 1 : 0;
        $winnerPetId = (int)($_POST['winner_pet_id'] ?? 0) ?: null;

        if ($title === '' || $location === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            admin_flash('Заполните обязательные поля корректно.', 'err');
            header('Location: events.php?mode=' . ($eid ? 'edit&id=' . $eid : 'new')); exit;
        }

        $existing = null;
        if ($eid) {
            $st = $pdo->prepare("SELECT * FROM events WHERE id=:id");
            $st->execute([':id' => $eid]);
            $existing = $st->fetch();
        }

        $finalImage = $existing['image_url'] ?? '';
        if ($imageData !== '') {
            $saved = save_base64_image($imageData, 'events', 'e');
            if (!$saved) {
                admin_flash('Не удалось сохранить изображение.', 'err');
                header('Location: events.php?mode=' . ($eid ? 'edit&id=' . $eid : 'new')); exit;
            }
            if ($existing && !empty($existing['image_url']) && str_starts_with($existing['image_url'], 'uploads/')) {
                delete_local_upload($existing['image_url']);
            }
            $finalImage = $saved;
        } elseif ($imageUrl !== '') {
            $finalImage = $imageUrl;
        }
        if ($finalImage === '') {
            admin_flash('Загрузите изображение или укажите URL.', 'err');
            header('Location: events.php?mode=' . ($eid ? 'edit&id=' . $eid : 'new')); exit;
        }

        // Validate winner - must be registered for this event
        if ($winnerPetId !== null && $eid) {
            $w = $pdo->prepare("SELECT 1 FROM event_registrations WHERE event_id = :e AND pet_id = :p");
            $w->execute([':e' => $eid, ':p' => $winnerPetId]);
            if (!$w->fetch()) $winnerPetId = null;
        } elseif ($winnerPetId !== null && !$eid) {
            $winnerPetId = null;
        }

        if ($existing) {
            $pdo->prepare("UPDATE events SET title=:t, event_date=:d, location=:l, seats=:s, image_url=:i, description=:desc, is_completed=:c, winner_pet_id=:w WHERE id=:id")
                ->execute([':t'=>$title, ':d'=>$eventDate, ':l'=>$location, ':s'=>$seats, ':i'=>$finalImage, ':desc'=>$description!==''?$description:null, ':c'=>$isCompleted, ':w'=>$winnerPetId, ':id'=>$eid]);
            admin_promote_winner_to_champion($pdo, $eid, $winnerPetId, $title, $eventDate);
            admin_flash('Событие обновлено.');
            header('Location: events.php?mode=edit&id=' . $eid); exit;
        }
        $pdo->prepare("INSERT INTO events (title, event_date, location, seats, image_url, description, is_completed) VALUES (:t,:d,:l,:s,:i,:desc,:c)")
            ->execute([':t'=>$title, ':d'=>$eventDate, ':l'=>$location, ':s'=>$seats, ':i'=>$finalImage, ':desc'=>$description!==''?$description:null, ':c'=>$isCompleted]);
        $newId = (int)$pdo->lastInsertId();
        admin_flash('Событие создано.');
        header('Location: events.php?mode=edit&id=' . $newId); exit;
    }

    if ($action === 'delete' && $eid) {
        $st = $pdo->prepare("SELECT image_url FROM events WHERE id=:id");
        $st->execute([':id' => $eid]);
        $row = $st->fetch();
        if ($row && !empty($row['image_url']) && str_starts_with($row['image_url'], 'uploads/')) {
            delete_local_upload($row['image_url']);
        }
        $pdo->prepare("DELETE FROM events WHERE id=:id")->execute([':id' => $eid]);
        admin_flash('Событие удалено.');
        header('Location: events.php'); exit;
    }

    if ($action === 'unregister') {
        $regId = (int)($_POST['reg_id'] ?? 0);
        $pdo->prepare("DELETE FROM event_registrations WHERE id=:id")->execute([':id' => $regId]);
        admin_flash('Запись отменена.');
        header('Location: events.php?mode=edit&id=' . $eid); exit;
    }
}

$existing = null;
if ($mode === 'edit' && $editId) {
    $st = $pdo->prepare("SELECT * FROM events WHERE id=:id");
    $st->execute([':id' => $editId]);
    $existing = $st->fetch();
    if (!$existing) { header('Location: events.php'); exit; }
}

$pageTitle  = 'События';
$pageActive = 'events';
include __DIR__ . '/_layout.php';

if ($mode === 'list'):
    $events = $pdo->query("SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id) AS reg_count
                            FROM events e ORDER BY e.event_date DESC")->fetchAll();
?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h1 class="text-xl sm:text-2xl font-black">Все события (<?= count($events) ?>)</h1>
        <a href="events.php?mode=new" class="btn-hover inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-sm">
            <i data-lucide="plus" class="w-4 h-4"></i> Новое событие
        </a>
    </div>
    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($events as $e): ?>
            <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden btn-hover hover:border-rose-500/40">
                <div class="h-40 sm:h-48 relative">
                    <img src="<?= str_starts_with($e['image_url'], 'uploads/') ? '../' . htmlspecialchars($e['image_url']) : htmlspecialchars($e['image_url']) ?>" class="w-full h-full object-cover" alt="">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                    <div class="absolute bottom-3 left-3 right-3">
                        <p class="text-xs text-gray-300 font-bold"><?= date('d.m.Y', strtotime($e['event_date'])) ?> · <?= htmlspecialchars($e['location']) ?></p>
                        <p class="font-black text-white truncate text-sm sm:text-base"><?= htmlspecialchars($e['title']) ?></p>
                    </div>
                </div>
                <div class="p-3 sm:p-4 flex items-center justify-between gap-2">
                    <div class="text-xs text-gray-400">
                        <span class="font-bold text-emerald-400"><?= (int)$e['reg_count'] ?></span> / <?= (int)$e['seats'] ?> мест
                    </div>
                    <div class="flex gap-1.5">
                        <a href="events.php?mode=edit&id=<?= (int)$e['id'] ?>" class="btn-hover px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs font-bold inline-flex items-center gap-1">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Изм.
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$events): ?>
            <p class="col-span-full text-center text-gray-500 py-12">Событий пока нет.</p>
        <?php endif; ?>
    </div>

<?php else: // edit/new
    $regs = [];
    if ($existing) {
        $rs = $pdo->prepare(
            "SELECT r.id AS reg_id, r.created_at, u.username, u.id AS user_id, p.name AS pet_name
               FROM event_registrations r
               JOIN users u ON u.id = r.user_id
               JOIN pets p  ON p.id = r.pet_id
              WHERE r.event_id = :e
              ORDER BY r.created_at DESC"
        );
        $rs->execute([':e' => $existing['id']]);
        $regs = $rs->fetchAll();
    }
?>
    <div class="mb-5 flex items-center gap-3">
        <a href="events.php" class="btn-hover inline-flex items-center gap-2 text-sm font-bold text-gray-400 hover:text-white">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> К списку
        </a>
    </div>
    <div class="grid lg:grid-cols-3 gap-4 sm:gap-6">
        <form method="post" class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-2xl p-4 sm:p-6 space-y-4">
            <input type="hidden" name="csrf"     value="<?= htmlspecialchars(admin_csrf()) ?>">
            <input type="hidden" name="action"   value="save">
            <input type="hidden" name="event_id" value="<?= (int)($existing['id'] ?? 0) ?>">
            <input type="hidden" name="image_data" id="image-data" value="">

            <h2 class="text-lg sm:text-xl font-black"><?= $existing ? 'Редактировать событие' : 'Новое событие' ?></h2>

            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">Обложка (16:9)</label>
                <div class="flex items-center gap-3">
                    <div id="image-preview" class="w-32 sm:w-44 h-20 sm:h-24 rounded-xl bg-gray-950 border border-gray-700 overflow-hidden flex items-center justify-center text-gray-600 flex-shrink-0">
                        <?php if ($existing && !empty($existing['image_url'])): ?>
                            <img src="<?= str_starts_with($existing['image_url'],'uploads/') ? '../' . htmlspecialchars($existing['image_url']) : htmlspecialchars($existing['image_url']) ?>" class="w-full h-full object-cover" alt="">
                        <?php else: ?>
                            <i data-lucide="image" class="w-7 h-7"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0 space-y-2">
                        <button type="button" id="image-btn" class="btn-hover px-3 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 font-bold text-xs inline-flex items-center gap-2">
                            <i data-lucide="upload" class="w-4 h-4"></i> Загрузить и обрезать
                        </button>
                        <input type="file" id="image-input" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <input type="text" name="image_url" placeholder="…или URL картинки"
                               value="<?= $existing && !str_starts_with($existing['image_url'] ?? '', 'uploads/') ? htmlspecialchars($existing['image_url']) : '' ?>"
                               class="w-full px-3 py-2 rounded-lg bg-gray-950 border border-gray-700 text-xs focus:border-rose-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">Название</label>
                <input type="text" name="title" required maxlength="255" value="<?= htmlspecialchars($existing['title'] ?? '') ?>"
                       class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
            </div>
            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">Дата</label>
                    <input type="date" name="event_date" required value="<?= htmlspecialchars($existing['event_date'] ?? date('Y-m-d')) ?>"
                           class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">Место</label>
                    <input type="text" name="location" required value="<?= htmlspecialchars($existing['location'] ?? '') ?>"
                           class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">Количество мест</label>
                <input type="number" name="seats" min="0" max="9999" required value="<?= (int)($existing['seats'] ?? 20) ?>"
                       class="w-full sm:w-40 px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">Описание</label>
                <textarea name="description" rows="4"
                          class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm"><?= htmlspecialchars($existing['description'] ?? '') ?></textarea>
            </div>

            <?php if ($existing): ?>
            <div class="grid sm:grid-cols-2 gap-3 pt-2 border-t border-gray-800">
                <label class="flex items-center gap-2 cursor-pointer p-3 rounded-xl bg-gray-950 border border-gray-700 hover:border-amber-500/40">
                    <input type="checkbox" name="is_completed" value="1" <?= !empty($existing['is_completed']) ? 'checked' : '' ?> class="w-4 h-4 accent-amber-500">
                    <span class="text-sm font-bold text-gray-200">Событие прошло</span>
                </label>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">Победитель (из участников)</label>
                    <select name="winner_pet_id" class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-amber-500 focus:outline-none text-sm">
                        <option value="0">- не выбран -</option>
                        <?php
                        $candStmt = $pdo->prepare("SELECT p.id AS pet_id, p.name AS pet_name, u.username
                                                     FROM event_registrations r
                                                     JOIN pets p ON p.id = r.pet_id
                                                     JOIN users u ON u.id = r.user_id
                                                    WHERE r.event_id = :e
                                                    ORDER BY r.created_at ASC");
                        $candStmt->execute([':e' => $existing['id']]);
                        foreach ($candStmt->fetchAll() as $c):
                        ?>
                            <option value="<?= (int)$c['pet_id'] ?>" <?= ((int)($existing['winner_pet_id'] ?? 0) === (int)$c['pet_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['pet_name']) ?> · @<?= htmlspecialchars($c['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1.5">Победитель автоматически добавится в раздел «Чемпионы».</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="flex flex-wrap gap-2 pt-2 justify-between">
                <button type="submit" class="btn-hover px-5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-sm inline-flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> <?= $existing ? 'Сохранить' : 'Создать' ?>
                </button>
                <?php if ($existing): ?>
                    <button type="submit" name="action" value="delete" formnovalidate
                            onclick="return confirm('Удалить событие? Все записи будут также удалены.');"
                            class="btn-hover px-4 py-2.5 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 font-bold text-sm inline-flex items-center gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> Удалить
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($existing): ?>
        <section class="bg-gray-900 border border-gray-800 rounded-2xl p-4 sm:p-5">
            <h3 class="font-black text-base mb-3 flex items-center gap-2">
                <i data-lucide="ticket" class="w-5 h-5 text-emerald-400"></i>
                Записи (<?= count($regs) ?>)
            </h3>
            <?php if (!$regs): ?>
                <p class="text-sm text-gray-500 py-6 text-center">Записей пока нет.</p>
            <?php else: ?>
                <div class="space-y-2 max-h-[60vh] overflow-y-auto pr-1">
                    <?php foreach ($regs as $r): ?>
                        <div class="p-2.5 rounded-xl bg-gray-950 border border-gray-800 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold truncate">@<?= htmlspecialchars($r['username']) ?></p>
                                <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($r['pet_name']) ?> · <?= date('d.m.Y', strtotime($r['created_at'])) ?></p>
                            </div>
                            <form method="post" onsubmit="return confirm('Отменить запись?');">
                                <input type="hidden" name="csrf"     value="<?= htmlspecialchars(admin_csrf()) ?>">
                                <input type="hidden" name="action"   value="unregister">
                                <input type="hidden" name="event_id" value="<?= (int)$existing['id'] ?>">
                                <input type="hidden" name="reg_id"   value="<?= (int)$r['reg_id'] ?>">
                                <button type="submit" class="btn-hover w-8 h-8 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 inline-flex items-center justify-center">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

<?php include __DIR__ . '/../includes/crop_modal.php'; ?>
<script>
(function () {
    const btn   = document.getElementById('image-btn');
    const input = document.getElementById('image-input');
    const data  = document.getElementById('image-data');
    const prev  = document.getElementById('image-preview');
    if (!btn) return;
    btn.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const f = input.files[0]; if (!f) return;
        window.openCropModal(f, { aspectRatio: 16/9, outSize: 1600 }, (dataUrl) => {
            data.value = dataUrl;
            prev.innerHTML = '<img src="' + dataUrl + '" class="w-full h-full object-cover" alt="">';
        });
        input.value = '';
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
