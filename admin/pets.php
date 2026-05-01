<?php
require_once __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $action = $_POST['action'] ?? '';
    $pid    = (int)($_POST['pet_id'] ?? 0);

    if ($action === 'delete' && $pid) {
        $st = $pdo->prepare("SELECT photo_url FROM pets WHERE id=:id");
        $st->execute([':id'=>$pid]);
        $row = $st->fetch();
        if ($row && !empty($row['photo_url']) && str_starts_with($row['photo_url'], 'uploads/')) {
            delete_local_upload($row['photo_url']);
        }
        $pdo->prepare("DELETE FROM pets WHERE id=:id")->execute([':id'=>$pid]);
        admin_flash('Питомец удалён.');
        header('Location: pets.php'); exit;
    }

    if ($action === 'update' && $pid) {
        $name  = trim($_POST['name']  ?? '');
        $breed = trim($_POST['breed'] ?? '');
        $type  = $_POST['type'] ?? '';
        if ($name === '' || $breed === '' || !in_array($type, ['dog','cat','other'], true)) {
            admin_flash('Заполните поля корректно.', 'err');
        } else {
            $pdo->prepare("UPDATE pets SET name=:n, breed=:b, type=:t WHERE id=:id")
                ->execute([':n'=>$name, ':b'=>$breed, ':t'=>$type, ':id'=>$pid]);
            admin_flash('Питомец обновлён.');
        }
        header('Location: pets.php'); exit;
    }
}

$query = trim($_GET['q'] ?? '');
$where = [];
$params = [];
if ($query !== '') {
    $where[] = '(p.name LIKE :q OR p.breed LIKE :q OR u.username LIKE :q)';
    $params[':q'] = '%' . $query . '%';
}
$sql = "SELECT p.*, u.username
          FROM pets p
     LEFT JOIN users u ON u.id = p.user_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY p.created_at DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$pets = $st->fetchAll();

$pageTitle  = 'Питомцы';
$pageActive = 'pets';
include __DIR__ . '/_layout.php';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-xl sm:text-2xl font-black">Все питомцы (<?= count($pets) ?>)</h1>
    <form method="get" class="flex gap-2">
        <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Поиск…"
               class="px-3 py-2 rounded-xl bg-gray-900 border border-gray-700 focus:border-rose-500 focus:outline-none text-sm">
        <button type="submit" class="btn-hover px-3 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-sm">
            <i data-lucide="search" class="w-4 h-4"></i>
        </button>
    </form>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php foreach ($pets as $p): ?>
        <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
            <div class="aspect-[4/3] relative">
                <img src="<?= str_starts_with($p['photo_url'],'uploads/') ? '../' . htmlspecialchars($p['photo_url']) : htmlspecialchars($p['photo_url']) ?>" class="w-full h-full object-cover" alt="">
                <span class="absolute top-2 left-2 px-2 py-1 rounded-lg text-[10px] font-bold bg-black/60 text-white"><?= htmlspecialchars(pet_type_label($p['type'])) ?></span>
            </div>
            <form method="post" class="p-3 space-y-2">
                <input type="hidden" name="csrf"   value="<?= htmlspecialchars(admin_csrf()) ?>">
                <input type="hidden" name="pet_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="action" value="update">
                <input type="text" name="name"  required value="<?= htmlspecialchars($p['name']) ?>"  class="w-full px-2.5 py-1.5 rounded-lg bg-gray-950 border border-gray-700 text-sm font-bold focus:border-rose-500 focus:outline-none">
                <input type="text" name="breed" required value="<?= htmlspecialchars($p['breed']) ?>" class="w-full px-2.5 py-1.5 rounded-lg bg-gray-950 border border-gray-700 text-xs focus:border-rose-500 focus:outline-none">
                <select name="type" class="w-full px-2.5 py-1.5 rounded-lg bg-gray-950 border border-gray-700 text-xs focus:border-rose-500 focus:outline-none">
                    <option value="dog"   <?= $p['type']==='dog'?'selected':'' ?>>Собака</option>
                    <option value="cat"   <?= $p['type']==='cat'?'selected':'' ?>>Кошка</option>
                    <option value="other" <?= $p['type']==='other'?'selected':'' ?>>Другое</option>
                </select>
                <p class="text-[11px] text-gray-500 truncate">Владелец: @<?= htmlspecialchars($p['username'] ?? '-') ?></p>
                <div class="flex gap-1.5">
                    <button type="submit" class="btn-hover flex-1 px-2 py-1.5 rounded-lg bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold">Сохранить</button>
                    <button type="submit" name="action" value="delete" formnovalidate
                            onclick="return confirm('Удалить питомца?');"
                            class="btn-hover px-2 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs font-bold">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if (!$pets): ?>
        <p class="col-span-full text-center text-gray-500 py-12">Питомцы не найдены.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
