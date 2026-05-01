<?php
require_once __DIR__ . '/auth.php';
$__user = current_user();
$__active = $active ?? '';
$__base = nav_base();
?>
<nav id="navbar" class="fixed w-full z-50 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <a href="<?= $__base ?>index.php" class="flex items-center space-x-3 btn-hover">
                <div class="w-12 h-12 bg-rose-400 rounded-2xl flex items-center justify-center">
                    <i data-lucide="heart" class="w-7 h-7 text-white fill-white"></i>
                </div>
                <div>
                    <p class="text-2xl font-black tracking-tight text-rose-500">PetLove Club</p>
                    <p class="text-xs text-gray-600 font-medium">Клуб любителей животных</p>
                </div>
            </a>
            <div class="hidden lg:flex items-center space-x-1">
                <a href="<?= $__base ?>index.php"     class="btn-hover px-4 py-2 font-semibold rounded-xl transition-all duration-300 text-sm <?= $__active==='home'      ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:text-rose-500 hover:bg-rose-50' ?>">Главная</a>
                <a href="<?= $__base ?>about.php" class="btn-hover px-4 py-2 font-semibold rounded-xl transition-all duration-300 text-sm <?= $__active==='about'     ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:text-rose-500 hover:bg-rose-50' ?>">О клубе</a>
                <a href="<?= $__base ?>events.php"    class="btn-hover px-4 py-2 font-semibold rounded-xl transition-all duration-300 text-sm <?= $__active==='events'    ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:text-rose-500 hover:bg-rose-50' ?>">События</a>
                <a href="<?= $__base ?>champions.php" class="btn-hover px-4 py-2 font-semibold rounded-xl transition-all duration-300 text-sm <?= $__active==='champions' ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:text-rose-500 hover:bg-rose-50' ?>">Чемпионы</a>
                <a href="<?= $__base ?>news.php"      class="btn-hover px-4 py-2 font-semibold rounded-xl transition-all duration-300 text-sm <?= $__active==='news'      ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:text-rose-500 hover:bg-rose-50' ?>">Новости</a>
                <a href="<?= $__base ?>knowledge_base.php" class="btn-hover px-4 py-2 font-semibold rounded-xl transition-all duration-300 text-sm <?= $__active==='kb'        ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:text-rose-500 hover:bg-rose-50' ?>">База знаний</a>

                <?php if ($__user): ?>
                    <?php if (mail_enabled() && empty($__user['email_verified_at'])): ?>
                        <a href="<?= $__base ?>verify.php" title="Email не подтверждён"
                           class="btn-hover ml-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-100 border-2 border-amber-300 text-amber-800 hover:bg-amber-200 shadow-sm text-sm font-bold">
                            <i data-lucide="mail-warning" class="w-4 h-4"></i>
                            <span>Подтвердите email</span>
                        </a>
                    <?php endif; ?>
                    <a href="<?= $__base ?>profile.php?u=<?= urlencode($__user['username']) ?>" class="btn-hover ml-4 flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-white border-2 border-gray-200 hover:border-rose-300 shadow-sm transition-all duration-300">
                        <?php if (!empty($__user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars(asset_url($__user['avatar_url'])) ?>" alt="" class="w-8 h-8 rounded-full object-cover">
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-rose-400 flex items-center justify-center text-white text-sm font-bold">
                                <?= htmlspecialchars(mb_strtoupper(mb_substr($__user['username'],0,1))) ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-sm font-bold text-gray-700"><?= htmlspecialchars($__user['username']) ?></span>
                        <?= role_badge($__user, 'sm') ?>
                    </a>
                    <?php if ((int)($__user['role'] ?? 0) >= ROLE_ADMIN): ?>
                        <a href="<?= $__base ?>admin/index.php" title="Админ-панель"
                           class="btn-hover ml-2 inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gray-900 text-white hover:bg-gray-800 shadow-sm">
                            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= $__base ?>login.php" class="btn-hover ml-4 px-6 py-2.5 bg-rose-400 text-white font-bold rounded-xl transition-all duration-300 hover:bg-rose-500">
                        Войти
                    </a>
                <?php endif; ?>
            </div>
            <button id="mobile-menu-btn" class="btn-hover lg:hidden p-2 rounded-xl hover:bg-rose-50 transition-colors">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
        </div>
    </div>
</nav>

<div id="mobile-overlay" class="fixed inset-0 bg-black/40 z-40 opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>
<aside id="mobile-menu"
       class="fixed top-0 right-0 bottom-0 z-50 w-80 max-w-[85vw] bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-out lg:hidden flex flex-col">
    <div class="flex items-center justify-between px-5 h-20 border-b border-gray-100 flex-shrink-0">
        <span class="text-lg font-black text-rose-500">Меню</span>
        <button id="mobile-close-btn" class="btn-hover w-10 h-10 rounded-xl bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
            <i data-lucide="x" class="w-5 h-5 text-gray-700"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-2">
        <a href="<?= $__base ?>index.php"     class="block px-4 py-3 rounded-xl font-semibold transition-colors <?= $__active==='home'      ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:bg-rose-50' ?>">Главная</a>
        <a href="<?= $__base ?>about.php" class="block px-4 py-3 rounded-xl font-semibold transition-colors <?= $__active==='about'     ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:bg-rose-50' ?>">О клубе</a>
        <a href="<?= $__base ?>events.php"    class="block px-4 py-3 rounded-xl font-semibold transition-colors <?= $__active==='events'    ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:bg-rose-50' ?>">События</a>
        <a href="<?= $__base ?>champions.php" class="block px-4 py-3 rounded-xl font-semibold transition-colors <?= $__active==='champions' ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:bg-rose-50' ?>">Чемпионы</a>
        <a href="<?= $__base ?>news.php"      class="block px-4 py-3 rounded-xl font-semibold transition-colors <?= $__active==='news'      ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:bg-rose-50' ?>">Новости</a>
        <a href="<?= $__base ?>knowledge_base.php" class="block px-4 py-3 rounded-xl font-semibold transition-colors <?= $__active==='kb'        ? 'text-rose-500 bg-rose-50' : 'text-gray-700 hover:bg-rose-50' ?>">База знаний</a>
        <?php if ($__user): ?>
            <?php if (mail_enabled() && empty($__user['email_verified_at'])): ?>
                <a href="<?= $__base ?>verify.php" class="block px-4 py-3 rounded-xl font-bold transition-colors text-amber-800 bg-amber-100 hover:bg-amber-200">
                    ✉ Подтвердите email
                </a>
            <?php endif; ?>
            <a href="<?= $__base ?>profile.php?u=<?= urlencode($__user['username']) ?>" class="block px-4 py-3 rounded-xl font-semibold transition-colors text-gray-700 hover:bg-rose-50">Профиль (<?= htmlspecialchars($__user['username']) ?>)</a>
            <?php if ((int)($__user['role'] ?? 0) >= ROLE_ADMIN): ?>
                <a href="<?= $__base ?>admin/index.php" class="block px-4 py-3 rounded-xl font-semibold transition-colors text-white bg-gray-900 hover:bg-gray-800">Админ-панель</a>
            <?php endif; ?>
            <a href="<?= $__base ?>logout.php" class="block px-4 py-3 rounded-xl font-semibold transition-colors text-gray-700 hover:bg-rose-50">Выйти</a>
        <?php else: ?>
            <a href="<?= $__base ?>login.php" class="block w-full text-center px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">Войти</a>
        <?php endif; ?>
    </div>
</aside>
