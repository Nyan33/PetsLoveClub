<?php
require_once __DIR__ . '/_init.php';

$items = $pdo->query(
    "SELECT k.*, s.name AS category
       FROM knowledge_base k
       JOIN slugs s ON s.slug = k.slug
      ORDER BY k.published_at DESC"
)->fetchAll();

$pageTitle  = 'База знаний';
$pageActive = 'kb';
include __DIR__ . '/_layout.php';

$returnTo = urlencode('../admin/kb.php');
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-xl sm:text-2xl font-black">Статьи (<?= count($items) ?>)</h1>
    <a href="../edit/kb.php?return_to=<?= $returnTo ?>" class="btn-hover inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-sm">
        <i data-lucide="plus" class="w-4 h-4"></i> Новая статья
    </a>
</div>

<div class="space-y-2.5">
    <?php foreach ($items as $i): ?>
        <a href="../edit/kb.php?id=<?= (int)$i['id'] ?>&return_to=<?= $returnTo ?>"
           class="btn-hover flex items-center gap-3 sm:gap-4 p-3 rounded-2xl bg-gray-900 border border-gray-800 hover:border-rose-500/40">
            <img src="<?= str_starts_with($i['image_url'],'uploads/') ? '../' . htmlspecialchars($i['image_url']) : htmlspecialchars($i['image_url']) ?>"
                 class="w-16 h-16 sm:w-20 sm:h-14 rounded-xl object-cover flex-shrink-0" alt="">
            <div class="flex-1 min-w-0">
                <p class="text-[11px] font-bold text-rose-400 uppercase tracking-wider"><?= htmlspecialchars($i['category']) ?></p>
                <p class="font-black text-sm sm:text-base text-white truncate"><?= htmlspecialchars($i['title']) ?></p>
                <p class="text-xs text-gray-500"><?= date('d.m.Y', strtotime($i['published_at'])) ?></p>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-gray-600 flex-shrink-0"></i>
        </a>
    <?php endforeach; ?>
    <?php if (!$items): ?>
        <p class="text-center text-gray-500 py-12">Статей пока нет.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
