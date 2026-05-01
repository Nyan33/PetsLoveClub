<?php
require_once __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $slug = trim((string)($_POST['slug'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        if (!preg_match('/^[a-z0-9_-]{2,40}$/', $slug)) {
            admin_flash('Slug: 2-40 символов, латиница в нижнем регистре, цифры, _ и -.', 'err');
        } elseif ($name === '' || mb_strlen($name) > 80) {
            admin_flash('Название обязательно (до 80 символов).', 'err');
        } else {
            try {
                $pdo->prepare("INSERT INTO slugs (slug, name) VALUES (:s, :n)")
                    ->execute([':s' => $slug, ':n' => $name]);
                admin_flash('Категория добавлена.');
            } catch (PDOException $e) {
                admin_flash('Не удалось добавить: ' . $e->getMessage(), 'err');
            }
        }
        header('Location: slugs.php'); exit;
    }

    if ($action === 'update') {
        $oldSlug = (string)($_POST['old_slug'] ?? '');
        $newSlug = trim((string)($_POST['slug'] ?? ''));
        $newName = trim((string)($_POST['name'] ?? ''));
        if (!preg_match('/^[a-z0-9_-]{2,40}$/', $newSlug)) {
            admin_flash('Slug: 2-40 символов, латиница в нижнем регистре, цифры, _ и -.', 'err');
        } elseif ($newName === '' || mb_strlen($newName) > 80) {
            admin_flash('Название обязательно (до 80 символов).', 'err');
        } else {
            try {
                $pdo->prepare("UPDATE slugs SET slug = :ns, name = :nn WHERE slug = :os")
                    ->execute([':ns' => $newSlug, ':nn' => $newName, ':os' => $oldSlug]);
                admin_flash('Категория обновлена.');
            } catch (PDOException $e) {
                admin_flash('Не удалось обновить: ' . $e->getMessage(), 'err');
            }
        }
        header('Location: slugs.php'); exit;
    }

    if ($action === 'delete') {
        $slug = (string)($_POST['slug'] ?? '');
        $st = $pdo->prepare("SELECT COUNT(*) FROM news WHERE slug = :s");           $st->execute([':s' => $slug]); $usedNews = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM knowledge_base WHERE slug = :s"); $st->execute([':s' => $slug]); $usedKb   = (int)$st->fetchColumn();
        if ($usedNews + $usedKb > 0) {
            admin_flash("Нельзя удалить: используется в {$usedNews} новостях и {$usedKb} статьях базы знаний.", 'err');
        } else {
            try {
                $pdo->prepare("DELETE FROM slugs WHERE slug = :s")->execute([':s' => $slug]);
                admin_flash('Категория удалена.');
            } catch (PDOException $e) {
                admin_flash('Не удалось удалить: ' . $e->getMessage(), 'err');
            }
        }
        header('Location: slugs.php'); exit;
    }
}

$rows = $pdo->query(
    "SELECT s.slug, s.name,
            (SELECT COUNT(*) FROM news n WHERE n.slug = s.slug) AS news_count,
            (SELECT COUNT(*) FROM knowledge_base k WHERE k.slug = s.slug) AS kb_count
       FROM slugs s
      ORDER BY s.name"
)->fetchAll();

$pageTitle  = 'Категории';
$pageActive = 'slugs';
include __DIR__ . '/_layout.php';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl sm:text-2xl font-black">Категории (slugs)</h1>
        <p class="text-sm text-gray-400 mt-1">Используются для группировки новостей и статей базы знаний.</p>
    </div>
    <p class="text-sm text-gray-400">Всего: <span class="font-bold text-gray-200"><?= count($rows) ?></span></p>
</div>

<details class="mb-6 bg-gray-900 border border-gray-800 rounded-2xl" <?= empty($rows) ? 'open' : '' ?>>
    <summary class="cursor-pointer px-4 py-3 font-bold text-sm flex items-center gap-2 select-none">
        <i data-lucide="plus" class="w-4 h-4 text-emerald-400"></i>
        Добавить категорию
    </summary>
    <form method="post" class="p-4 border-t border-gray-800 grid sm:grid-cols-[1fr_1fr_auto] gap-3 items-end">
        <input type="hidden" name="csrf" value="<?= admin_csrf() ?>">
        <input type="hidden" name="action" value="create">
        <label class="block">
            <span class="block text-xs font-bold text-gray-400 mb-1">Slug</span>
            <input type="text" name="slug" required pattern="[a-z0-9_-]{2,40}" maxlength="40"
                   placeholder="например, breeding"
                   class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm font-mono focus:border-rose-500 outline-none">
            <span class="block text-[11px] text-gray-500 mt-1">2-40 символов: a-z, 0-9, _, -</span>
        </label>
        <label class="block">
            <span class="block text-xs font-bold text-gray-400 mb-1">Название</span>
            <input type="text" name="name" required maxlength="80"
                   placeholder="например, Племенная работа"
                   class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm focus:border-rose-500 outline-none">
        </label>
        <button type="submit" class="btn-hover inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm justify-center">
            <i data-lucide="check" class="w-4 h-4"></i> Добавить
        </button>
    </form>
</details>

<div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-800/60 text-gray-300">
                <tr>
                    <th class="px-3 py-2 text-left font-bold">Slug</th>
                    <th class="px-3 py-2 text-left font-bold">Название</th>
                    <th class="px-3 py-2 text-left font-bold">Использование</th>
                    <th class="px-3 py-2 text-right font-bold">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $key = preg_replace('/[^a-zA-Z0-9]/', '', (string)$r['slug']);
                $inUse = ((int)$r['news_count'] + (int)$r['kb_count']) > 0;
            ?>
                <tr class="border-t border-gray-800 align-top">
                    <td class="px-3 py-2 min-w-[12rem]">
                        <input type="text" name="slug" value="<?= htmlspecialchars($r['slug']) ?>"
                               form="slugupd_<?= $key ?>" required pattern="[a-z0-9_-]{2,40}" maxlength="40"
                               class="w-full px-2 py-1 bg-gray-950 border border-gray-800 rounded-md text-xs font-mono focus:border-rose-500 outline-none">
                    </td>
                    <td class="px-3 py-2 min-w-[14rem]">
                        <input type="text" name="name" value="<?= htmlspecialchars($r['name']) ?>"
                               form="slugupd_<?= $key ?>" required maxlength="80"
                               class="w-full px-2 py-1 bg-gray-950 border border-gray-800 rounded-md text-xs focus:border-rose-500 outline-none">
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-sky-500/15 text-sky-300 text-[11px] font-bold">
                            <i data-lucide="newspaper" class="w-3 h-3"></i><?= (int)$r['news_count'] ?>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-violet-500/15 text-violet-300 text-[11px] font-bold ml-1">
                            <i data-lucide="book-open" class="w-3 h-3"></i><?= (int)$r['kb_count'] ?>
                        </span>
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        <div class="inline-flex gap-1.5">
                            <button type="submit" form="slugupd_<?= $key ?>"
                                    class="btn-hover inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold">
                                <i data-lucide="save" class="w-3.5 h-3.5"></i> Сохр.
                            </button>
                            <button type="submit" form="slugdel_<?= $key ?>" <?= $inUse ? 'disabled title="Категория используется"' : '' ?>
                                    class="btn-hover inline-flex items-center px-2.5 py-1.5 rounded-lg <?= $inUse ? 'bg-gray-700 text-gray-400 cursor-not-allowed' : 'bg-rose-600 hover:bg-rose-700 text-white' ?> text-xs font-bold">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">Категорий нет.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($rows as $r):
    $key = preg_replace('/[^a-zA-Z0-9]/', '', (string)$r['slug']);
?>
    <form method="post" id="slugupd_<?= $key ?>" class="hidden">
        <input type="hidden" name="csrf" value="<?= admin_csrf() ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="old_slug" value="<?= htmlspecialchars($r['slug']) ?>">
    </form>
    <form method="post" id="slugdel_<?= $key ?>" class="hidden" onsubmit="return confirm('Удалить категорию <?= htmlspecialchars($r['slug']) ?>?');">
        <input type="hidden" name="csrf" value="<?= admin_csrf() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($r['slug']) ?>">
    </form>
<?php endforeach; ?>

<?php include __DIR__ . '/_footer.php'; ?>
