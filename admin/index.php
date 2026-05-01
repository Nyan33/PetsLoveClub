<?php
require_once __DIR__ . '/_init.php';

$stats = [
    'users'         => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'users_banned'  => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE banned = 1")->fetchColumn(),
    'users_editors' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = " . ROLE_EDITOR)->fetchColumn(),
    'users_admins'  => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role >= " . ROLE_ADMIN)->fetchColumn(),
    'pets'          => (int)$pdo->query("SELECT COUNT(*) FROM pets")->fetchColumn(),
    'events'        => (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'events_upcoming' => (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()")->fetchColumn(),
    'champions'     => (int)$pdo->query("SELECT COUNT(*) FROM champions")->fetchColumn(),
    'news'          => (int)$pdo->query("SELECT COUNT(*) FROM news")->fetchColumn(),
    'kb'            => (int)$pdo->query("SELECT COUNT(*) FROM knowledge_base")->fetchColumn(),
    'slugs'         => (int)$pdo->query("SELECT COUNT(*) FROM slugs")->fetchColumn(),
    'registrations' => (int)$pdo->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn(),
];

$recentUsers = $pdo->query(
    "SELECT id, username, role, banned, created_at, avatar_url
       FROM users
      WHERE role <= " . admin_visible_max_role() . "
        AND id <> " . (int)$adminUser['id'] . "
      ORDER BY created_at DESC
      LIMIT 5"
)->fetchAll();

$recentRegs = $pdo->query(
    "SELECT r.created_at, u.username, e.title AS event_title, p.name AS pet_name
       FROM event_registrations r
       JOIN users u ON u.id = r.user_id
       JOIN events e ON e.id = r.event_id
       JOIN pets p   ON p.id = r.pet_id
      ORDER BY r.created_at DESC
      LIMIT 5"
)->fetchAll();

$pageTitle  = 'Дашборд';
$pageActive = 'dashboard';
include __DIR__ . '/_layout.php';
?>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <?php
    $palette = [
        'rose'    => ['border' => 'hover:border-rose-500/60',    'bg' => 'bg-rose-500/20',    'text' => 'text-rose-400',    'sub' => 'text-rose-400'],
        'sky'     => ['border' => 'hover:border-sky-500/60',     'bg' => 'bg-sky-500/20',     'text' => 'text-sky-400',     'sub' => 'text-sky-400'],
        'amber'   => ['border' => 'hover:border-amber-500/60',   'bg' => 'bg-amber-500/20',   'text' => 'text-amber-400',   'sub' => 'text-amber-400'],
        'fuchsia' => ['border' => 'hover:border-fuchsia-500/60', 'bg' => 'bg-fuchsia-500/20', 'text' => 'text-fuchsia-400', 'sub' => 'text-fuchsia-400'],
        'emerald' => ['border' => 'hover:border-emerald-500/60', 'bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-400', 'sub' => 'text-emerald-400'],
        'violet'  => ['border' => 'hover:border-violet-500/60',  'bg' => 'bg-violet-500/20',  'text' => 'text-violet-400',  'sub' => 'text-violet-400'],
    ];
    $cards = [
        ['Пользователи', $stats['users'],         'users',     'rose',    'users.php',     $stats['users_banned'].' заблок.'],
        ['Редакторы',    $stats['users_editors'], 'pen-tool',  'sky',     'users.php',     null],
        ['Админы',       $stats['users_admins'],  'shield',    'amber',   'users.php',     null],
        ['Питомцы',      $stats['pets'],          'paw-print', 'fuchsia', 'pets.php',      null],
        ['События',      $stats['events'],        'calendar',  'emerald', 'events.php',    $stats['events_upcoming'].' предстоит'],
        ['Чемпионы',     $stats['champions'],     'award',     'amber',   'champions.php', null],
        ['Новости',      $stats['news'],          'newspaper', 'sky',     'news.php',      null],
        ['База знаний',  $stats['kb'],            'book-open', 'violet',  'kb.php',        null],
        ['Категории',    $stats['slugs'],         'tags',      'emerald', 'slugs.php',     null],
        ['Записи',       $stats['registrations'], 'ticket',    'rose',    'events.php',    null],
    ];
    foreach ($cards as $c):
        [$label, $value, $icon, $color, $href, $sub] = $c;
        $p = $palette[$color];
    ?>
        <a href="<?= $href ?>"
           class="btn-hover block p-4 sm:p-5 rounded-2xl bg-gray-900 border border-gray-800 <?= $p['border'] ?>">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl <?= $p['bg'] ?> flex items-center justify-center">
                    <i data-lucide="<?= $icon ?>" class="w-5 h-5 <?= $p['text'] ?>"></i>
                </div>
                <i data-lucide="arrow-up-right" class="w-4 h-4 text-gray-600"></i>
            </div>
            <p class="text-3xl sm:text-4xl font-black text-white leading-none"><?= $value ?></p>
            <p class="text-xs sm:text-sm text-gray-400 font-semibold mt-1"><?= htmlspecialchars($label) ?></p>
            <?php if ($sub): ?>
                <p class="text-[11px] <?= $p['sub'] ?> font-bold mt-1"><?= htmlspecialchars($sub) ?></p>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-2 gap-4 sm:gap-6">
    <section class="bg-gray-900 border border-gray-800 rounded-2xl p-4 sm:p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base sm:text-lg font-black text-white flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-rose-400"></i>
                Новые пользователи
            </h2>
            <a href="users.php" class="text-xs font-bold text-rose-400 hover:text-rose-300">Все →</a>
        </div>
        <?php if (!$recentUsers): ?>
            <p class="text-sm text-gray-500 py-6 text-center">Список пуст.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($recentUsers as $u):
                    $ri = role_info((int)$u['role']);
                ?>
                <a href="users.php?id=<?= (int)$u['id'] ?>"
                   class="btn-hover flex items-center gap-3 p-2 sm:p-3 rounded-xl hover:bg-gray-800">
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
                        </p>
                        <p class="text-xs text-gray-500"><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></p>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gray-600"></i>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="bg-gray-900 border border-gray-800 rounded-2xl p-4 sm:p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base sm:text-lg font-black text-white flex items-center gap-2">
                <i data-lucide="ticket" class="w-5 h-5 text-emerald-400"></i>
                Последние записи на события
            </h2>
            <a href="events.php" class="text-xs font-bold text-emerald-400 hover:text-emerald-300">Все →</a>
        </div>
        <?php if (!$recentRegs): ?>
            <p class="text-sm text-gray-500 py-6 text-center">Записей пока нет.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($recentRegs as $r): ?>
                    <div class="p-2 sm:p-3 rounded-xl bg-gray-950/60 border border-gray-800">
                        <p class="font-bold text-sm text-white truncate">
                            @<?= htmlspecialchars($r['username']) ?>
                            <span class="text-gray-500 font-medium">·</span>
                            <span class="text-gray-300"><?= htmlspecialchars($r['pet_name']) ?></span>
                        </p>
                        <p class="text-xs text-gray-400 truncate">→ <?= htmlspecialchars($r['event_title']) ?></p>
                        <p class="text-[11px] text-gray-600 mt-0.5"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
