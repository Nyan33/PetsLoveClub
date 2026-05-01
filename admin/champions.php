<?php
require_once __DIR__ . '/_init.php';

$mode   = $_GET['mode'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $action = $_POST['action'] ?? '';
    $cid    = (int)($_POST['champion_id'] ?? 0);

    if ($action === 'save') {
        $name      = trim($_POST['name']  ?? '');
        $breed     = trim($_POST['breed'] ?? '');
        $title     = trim($_POST['title'] ?? '');
        $year      = (int)($_POST['year'] ?? date('Y'));
        $petId     = (int)($_POST['pet_id'] ?? 0) ?: null;
        $imageData = $_POST['image_data']  ?? '';
        $imageUrl  = trim($_POST['image_url'] ?? '');

        if ($name === '' || $breed === '' || $title === '') {
            admin_flash('Заполните имя, породу и титул.', 'err');
            header('Location: champions.php?mode=' . ($cid ? 'edit&id='.$cid : 'new')); exit;
        }
        $existing = null;
        if ($cid) {
            $st = $pdo->prepare("SELECT * FROM champions WHERE id=:id");
            $st->execute([':id'=>$cid]);
            $existing = $st->fetch();
        }
        $finalImage = $existing['image_url'] ?? '';
        if ($imageData !== '') {
            $saved = save_base64_image($imageData, 'champions', 'c');
            if (!$saved) { admin_flash('Не удалось сохранить изображение.', 'err'); header('Location: champions.php?mode='.($cid?'edit&id='.$cid:'new')); exit; }
            if ($existing && !empty($existing['image_url']) && str_starts_with($existing['image_url'], 'uploads/')) delete_local_upload($existing['image_url']);
            $finalImage = $saved;
        } elseif ($imageUrl !== '') $finalImage = $imageUrl;
        if ($finalImage === '') { admin_flash('Загрузите картинку или укажите URL.', 'err'); header('Location: champions.php?mode='.($cid?'edit&id='.$cid:'new')); exit; }

        if ($existing) {
            $pdo->prepare("UPDATE champions SET name=:n, breed=:b, title=:t, year=:y, image_url=:i, pet_id=:p WHERE id=:id")
                ->execute([':n'=>$name, ':b'=>$breed, ':t'=>$title, ':y'=>$year, ':i'=>$finalImage, ':p'=>$petId, ':id'=>$cid]);
            admin_flash('Чемпион обновлён.');
            header('Location: champions.php?mode=edit&id='.$cid); exit;
        }
        $pdo->prepare("INSERT INTO champions (name, breed, title, image_url, year, pet_id) VALUES (:n,:b,:t,:i,:y,:p)")
            ->execute([':n'=>$name, ':b'=>$breed, ':t'=>$title, ':i'=>$finalImage, ':y'=>$year, ':p'=>$petId]);
        admin_flash('Чемпион добавлен.');
        header('Location: champions.php?mode=edit&id=' . (int)$pdo->lastInsertId()); exit;
    }

    if ($action === 'delete' && $cid) {
        $st = $pdo->prepare("SELECT image_url FROM champions WHERE id=:id");
        $st->execute([':id'=>$cid]);
        $row = $st->fetch();
        if ($row && !empty($row['image_url']) && str_starts_with($row['image_url'], 'uploads/')) delete_local_upload($row['image_url']);
        $pdo->prepare("DELETE FROM champions WHERE id=:id")->execute([':id'=>$cid]);
        admin_flash('Чемпион удалён.');
        header('Location: champions.php'); exit;
    }
}

$existing = null;
if ($mode === 'edit' && $editId) {
    $st = $pdo->prepare("SELECT * FROM champions WHERE id=:id");
    $st->execute([':id'=>$editId]);
    $existing = $st->fetch();
    if (!$existing) { header('Location: champions.php'); exit; }
}

$pageTitle  = 'Чемпионы';
$pageActive = 'champions';
include __DIR__ . '/_layout.php';

if ($mode === 'list'):
    $champions = $pdo->query(
        "SELECT c.*, p.name AS pet_name, u.username AS owner_username, e.title AS event_title
           FROM champions c
      LEFT JOIN pets   p ON p.id = c.pet_id
      LEFT JOIN users  u ON u.id = p.user_id
      LEFT JOIN events e ON e.id = c.event_id
       ORDER BY c.year DESC, c.id DESC"
    )->fetchAll();
?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h1 class="text-xl sm:text-2xl font-black">Чемпионы (<?= count($champions) ?>)</h1>
        <a href="champions.php?mode=new" class="btn-hover inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-sm">
            <i data-lucide="plus" class="w-4 h-4"></i> Добавить
        </a>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($champions as $c): ?>
            <a href="champions.php?mode=edit&id=<?= (int)$c['id'] ?>" class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden btn-hover hover:border-amber-500/40 group">
                <div class="aspect-[4/5] relative">
                    <img src="<?= str_starts_with($c['image_url'],'uploads/') ? '../' . htmlspecialchars($c['image_url']) : htmlspecialchars($c['image_url']) ?>" class="w-full h-full object-cover" alt="">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                    <div class="absolute top-2 right-2 w-8 h-8 rounded-full bg-amber-400 flex items-center justify-center">
                        <i data-lucide="award" class="w-4 h-4 text-amber-900"></i>
                    </div>
                    <?php if (!empty($c['event_id'])): ?>
                        <div class="absolute top-2 left-2 px-2 py-1 rounded-md bg-rose-500/90 text-white text-[10px] font-bold inline-flex items-center gap-1">
                            <i data-lucide="calendar" class="w-3 h-3"></i>
                            <span class="truncate max-w-[8rem]"><?= htmlspecialchars($c['event_title'] ?? 'Событие') ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="absolute bottom-3 left-3 right-3">
                        <p class="text-amber-400 font-bold text-xs truncate"><?= htmlspecialchars($c['title']) ?></p>
                        <p class="text-white font-black text-sm truncate"><?= htmlspecialchars($c['name']) ?></p>
                        <p class="text-gray-300 text-xs truncate"><?= htmlspecialchars($c['breed']) ?> · <?= (int)$c['year'] ?></p>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if (!$champions): ?>
            <p class="col-span-full text-center text-gray-500 py-12">Список пуст.</p>
        <?php endif; ?>
    </div>

<?php else:
    // Pets list for the optional pet_id link, with owner name for display
    $pets = $pdo->query(
        "SELECT p.id, p.name, p.breed, u.username
           FROM pets p
      LEFT JOIN users u ON u.id = p.user_id
       ORDER BY u.username, p.name"
    )->fetchAll();
?>
    <div class="mb-5 flex items-center gap-3">
        <a href="champions.php" class="btn-hover inline-flex items-center gap-2 text-sm font-bold text-gray-400 hover:text-white">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> К списку
        </a>
    </div>
    <form method="post" class="bg-gray-900 border border-gray-800 rounded-2xl p-4 sm:p-6 space-y-4 max-w-3xl">
        <input type="hidden" name="csrf"        value="<?= htmlspecialchars(admin_csrf()) ?>">
        <input type="hidden" name="action"      value="save">
        <input type="hidden" name="champion_id" value="<?= (int)($existing['id'] ?? 0) ?>">
        <input type="hidden" name="image_data"  id="image-data" value="">
        <h2 class="text-lg sm:text-xl font-black"><?= $existing ? 'Редактировать чемпиона' : 'Новый чемпион' ?></h2>

        <div>
            <label class="block text-xs font-bold text-gray-400 mb-1.5">Фото (4:5)</label>
            <div class="flex items-center gap-3">
                <div id="image-preview" class="w-24 sm:w-32 aspect-[4/5] rounded-xl bg-gray-950 border border-gray-700 overflow-hidden flex items-center justify-center text-gray-600 flex-shrink-0">
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
                    <input type="text" name="image_url" placeholder="…или URL"
                           value="<?= $existing && !str_starts_with($existing['image_url'] ?? '','uploads/') ? htmlspecialchars($existing['image_url']) : '' ?>"
                           class="w-full px-3 py-2 rounded-lg bg-gray-950 border border-gray-700 text-xs focus:border-rose-500 focus:outline-none">
                </div>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">Кличка</label>
                <input type="text" name="name" required maxlength="100" value="<?= htmlspecialchars($existing['name'] ?? '') ?>"
                       class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">Порода</label>
                <input type="text" name="breed" required maxlength="100" value="<?= htmlspecialchars($existing['breed'] ?? '') ?>"
                       class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">Титул</label>
                <input type="text" name="title" required maxlength="150" value="<?= htmlspecialchars($existing['title'] ?? '') ?>"
                       class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">Год</label>
                <input type="number" name="year" required min="1900" max="2100" value="<?= (int)($existing['year'] ?? date('Y')) ?>"
                       class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">Связать с питомцем (необязательно)</label>
                <select name="pet_id" class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
                    <option value="0">- не связывать -</option>
                    <?php foreach ($pets as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= ($existing && (int)$existing['pet_id'] === (int)$p['id'])?'selected':'' ?>>
                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['breed']) ?>) · @<?= htmlspecialchars($p['username'] ?? '?') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 pt-2 justify-between">
            <button type="submit" class="btn-hover px-5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-sm inline-flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> <?= $existing ? 'Сохранить' : 'Создать' ?>
            </button>
            <?php if ($existing): ?>
                <button type="submit" name="action" value="delete" formnovalidate
                        onclick="return confirm('Удалить чемпиона?');"
                        class="btn-hover px-4 py-2.5 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 font-bold text-sm inline-flex items-center gap-2">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Удалить
                </button>
            <?php endif; ?>
        </div>
    </form>

<?php include __DIR__ . '/../includes/crop_modal.php'; ?>
<script>
(function () {
    const btn = document.getElementById('image-btn');
    const inp = document.getElementById('image-input');
    const dat = document.getElementById('image-data');
    const prv = document.getElementById('image-preview');
    if (!btn) return;
    btn.addEventListener('click', () => inp.click());
    inp.addEventListener('change', () => {
        const f = inp.files[0]; if (!f) return;
        window.openCropModal(f, { aspectRatio: 4/5, outSize: 1000 }, (dataUrl) => {
            dat.value = dataUrl;
            prv.innerHTML = '<img src="' + dataUrl + '" class="w-full h-full object-cover" alt="">';
        });
        inp.value = '';
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
