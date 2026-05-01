<?php
/** @var string $pageTitle */
/** @var string $pageActive */
$pageTitle  = $pageTitle  ?? 'Админ-панель';
$pageActive = $pageActive ?? 'dashboard';

$navItems = [
    ['id' => 'dashboard', 'label' => 'Дашборд',     'icon' => 'layout-dashboard', 'href' => 'index.php'],
    ['id' => 'users',     'label' => 'Пользователи','icon' => 'users',            'href' => 'users.php'],
    ['id' => 'events',    'label' => 'События',     'icon' => 'calendar',         'href' => 'events.php'],
    ['id' => 'champions', 'label' => 'Чемпионы',    'icon' => 'award',            'href' => 'champions.php'],
    ['id' => 'pets',      'label' => 'Питомцы',     'icon' => 'paw-print',        'href' => 'pets.php'],
    ['id' => 'news',      'label' => 'Новости',     'icon' => 'newspaper',        'href' => 'news.php'],
    ['id' => 'kb',        'label' => 'База знаний', 'icon' => 'book-open',        'href' => 'kb.php'],
    ['id' => 'slugs',     'label' => 'Категории',   'icon' => 'tags',             'href' => 'slugs.php'],
];
$flash = admin_flash_pop();
$roleInfoMe = role_info((int)$adminUser['role']);
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Админ</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css">
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>
    <link rel="stylesheet" href="../styles/tailwind.css">
    <link rel="shortcut icon" href="../favicon.png" type="image/x-icon">
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen admin-body">

<header class="sticky top-0 z-40 bg-gray-900/90 backdrop-blur-lg border-b border-gray-800">
    <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 bg-rose-500 rounded-xl flex items-center justify-center flex-shrink-0">
                <i data-lucide="shield" class="w-5 h-5 text-white"></i>
            </div>
            <div class="min-w-0">
                <p class="font-black text-base sm:text-lg leading-tight truncate"><?= htmlspecialchars($pageTitle) ?></p>
                <p class="text-xs text-gray-400 truncate hidden sm:block">PetLove Club · Панель управления</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="hidden md:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-800 text-sm">
                <?php if ($roleInfoMe): ?>
                    <i data-lucide="<?= $roleInfoMe['icon'] ?>" class="w-4 h-4 <?= $roleInfoMe['text'] ?>"></i>
                <?php endif; ?>
                <span class="font-bold"><?= htmlspecialchars($adminUser['username']) ?></span>
            </span>
            <a href="../index.php"
               class="btn-hover inline-flex items-center gap-2 px-3 sm:px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-sm">
                <i data-lucide="external-link" class="w-4 h-4"></i>
                <span class="hidden sm:inline">На сайт</span>
            </a>
            <button id="admin-mobile-btn" type="button" class="lg:hidden btn-hover w-10 h-10 rounded-xl bg-gray-800 hover:bg-gray-700 flex items-center justify-center flex-shrink-0">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
        </div>
    </div>
</header>

<aside id="admin-side"
       class="fixed top-16 right-0 bottom-0 w-72 bg-gray-900 border-l border-gray-800 z-30 transform translate-x-full lg:translate-x-0 transition-transform duration-300 overflow-y-auto">
    <nav class="p-4 space-y-1">
        <p class="px-3 pt-2 pb-3 text-xs font-bold uppercase tracking-wider text-gray-500">Навигация</p>
        <?php foreach ($navItems as $n):
            $active = $pageActive === $n['id'];
        ?>
            <a href="<?= $n['href'] ?>"
               class="btn-hover flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-colors <?= $active
                    ? 'bg-rose-500 text-white shadow-lg'
                    : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
                <i data-lucide="<?= $n['icon'] ?>" class="w-5 h-5 flex-shrink-0"></i>
                <span><?= htmlspecialchars($n['label']) ?></span>
            </a>
        <?php endforeach; ?>
        <div class="pt-4 mt-4 border-t border-gray-800">
            <a href="../profile.php?u=<?= urlencode($adminUser['username']) ?>"
               class="btn-hover flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold text-gray-300 hover:bg-gray-800 hover:text-white">
                <i data-lucide="user" class="w-5 h-5 flex-shrink-0"></i>
                <span>Мой профиль</span>
            </a>
            <a href="../logout.php"
               class="btn-hover flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold text-gray-300 hover:bg-gray-800 hover:text-white">
                <i data-lucide="log-out" class="w-5 h-5 flex-shrink-0"></i>
                <span>Выйти</span>
            </a>
        </div>
    </nav>
</aside>

<div id="admin-overlay" class="fixed inset-0 bg-black/60 z-20 hidden lg:hidden"></div>

<main class="px-4 sm:px-6 lg:px-8 py-6 lg:pr-80">
    <?php if ($flash): ?>
        <div class="mb-5 px-4 py-3 rounded-xl border <?= $flash['type']==='err' ? 'bg-rose-950/50 border-rose-700 text-rose-200' : 'bg-emerald-950/50 border-emerald-700 text-emerald-200' ?>">
            <p class="text-sm font-semibold"><?= htmlspecialchars($flash['msg']) ?></p>
        </div>
    <?php endif; ?>
