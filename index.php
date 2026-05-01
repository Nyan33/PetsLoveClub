<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetLove Club - Клуб любителей животных</title>
    <meta name="description" content="PetLove Club - ярославский клуб любителей породистых собак и кошек. 25 лет опыта, выставки, племенная работа, обучение и сообщество увлечённых владельцев.">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="./styles/tailwind.css">
    <link rel="shortcut icon" href="./favicon.png" type="image/x-icon">
</head>
<body class="bg-amber-50 min-h-screen">

    <?php
    $monthsFull = [
        1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',
        7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'
    ];
    $monthsShort = [
        1=>'ЯНВ',2=>'ФЕВ',3=>'МАР',4=>'АПР',5=>'МАЙ',6=>'ИЮН',
        7=>'ИЮЛ',8=>'АВГ',9=>'СЕН',10=>'ОКТ',11=>'НОЯ',12=>'ДЕК'
    ];

    $newsItems = $pdo->query(
        "SELECT n.*, s.name AS category
           FROM news n
           JOIN slugs s ON s.slug = n.slug
          ORDER BY n.published_at DESC
          LIMIT 3"
    )->fetchAll();

    $events = $pdo->query(
        "SELECT * FROM events ORDER BY
            CASE WHEN event_date >= CURDATE() THEN 0 ELSE 1 END,
            event_date ASC
         LIMIT 4"
    )->fetchAll();

    $champions = $pdo->query("SELECT * FROM champions ORDER BY year DESC LIMIT 3")->fetchAll();

    $viewer = current_user();
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
        foreach ($rs->fetchAll() as $r) $myRegistrations[(int)$r['event_id']] = $r['pet_name'];
    }
    ?>

    <?php $active = 'home'; include 'includes/nav.php'; ?>

    <!-- ===== HERO ===== -->
    <section id="home" class="relative pt-32 pb-20 px-4 overflow-hidden">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-8">
                    <div class="inline-flex items-center space-x-2 px-4 py-2 bg-rose-100 rounded-full">
                        <i data-lucide="star" class="w-4 h-4 text-rose-500 fill-rose-500"></i>
                        <span class="text-sm font-bold text-rose-700">25 лет с вами</span>
                    </div>

                    <h1 class="text-5xl lg:text-7xl font-black leading-tight">
                        <span class="text-rose-500">Ваш путь</span><br>
                        <span class="text-gray-900">к идеальному</span><br>
                        <span class="text-gray-900">питомцу</span>
                    </h1>

                    <p class="text-xl text-gray-600 leading-relaxed max-w-xl font-medium">
                        Профессиональное сопровождение в мире породистых животных. Выставки, племенная работа, обучение и дружное сообщество настоящих любителей.
                    </p>

                    <div class="flex flex-wrap gap-4">
                        <a href="events.php" class="btn-hover px-8 py-4 bg-rose-400 text-white font-bold rounded-2xl flex items-center space-x-2 hover:bg-rose-500">
                            <span>Записаться на выставку</span>
                            <i data-lucide="chevron-right" class="w-5 h-5"></i>
                        </a>
                        <a href="about.php" class="btn-hover px-8 py-4 bg-white text-gray-800 font-bold rounded-2xl border-2 border-gray-200 hover:bg-gray-100">
                            Узнать больше
                        </a>
                    </div>

                    <div class="grid grid-cols-3 gap-6 pt-8">
                        <div class="text-center">
                            <div class="text-3xl font-black text-rose-500">2500+</div>
                            <div class="text-sm text-gray-600 font-semibold mt-1">Членов клуба</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-black text-rose-500">150+</div>
                            <div class="text-sm text-gray-600 font-semibold mt-1">Выставок в год</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-black text-rose-500">45+</div>
                            <div class="text-sm text-gray-600 font-semibold mt-1">Пород</div>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <?php
                    $heroEvent = $pdo->query(
                        "SELECT * FROM events ORDER BY
                            CASE WHEN event_date >= CURDATE() THEN 0 ELSE 1 END,
                            event_date ASC
                         LIMIT 1"
                    )->fetch();
                    $heroImage = $heroEvent && !empty($heroEvent['image_url'])
                        ? $heroEvent['image_url']
                        : 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?w=800&h=1000&fit=crop';
                    ?>
                    <div class="relative rounded-3xl overflow-hidden shadow-2xl">
                        <img
                            src="<?= htmlspecialchars($heroImage) ?>"
                            alt="<?= htmlspecialchars($heroEvent['title'] ?? 'Счастливые собаки') ?>"
                            class="w-full h-[600px] object-cover"
                        >
                        <div class="absolute inset-0 bg-black/40"></div>

                        <?php
                        if ($heroEvent):
                            $ts = strtotime($heroEvent['event_date']);
                            $heroDate = date('j', $ts) . ' ' . $monthsFull[(int)date('n', $ts)] . ' • ' . htmlspecialchars($heroEvent['location']);
                        ?>
                        <a href="events.php" class="btn-hover absolute bottom-8 left-8 right-8 bg-white/95 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/40 block">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-rose-400 rounded-2xl flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="award" class="w-8 h-8 text-white"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold text-gray-900 text-lg truncate"><?= htmlspecialchars($heroEvent['title']) ?></h3>
                                    <p class="text-gray-600 font-medium truncate"><?= $heroDate ?></p>
                                </div>
                                <i data-lucide="chevron-right" class="w-6 h-6 text-rose-500 flex-shrink-0"></i>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== НОВОСТИ ===== -->
    <section class="py-20 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <h2 class="text-4xl lg:text-5xl font-black text-gray-900 mb-3">Новости клуба</h2>
                    <p class="text-gray-600 font-medium text-lg">Последние события и анонсы</p>
                </div>
                <a href="news.php" class="btn-hover hidden md:flex items-center space-x-2 text-rose-500 font-bold hover:text-rose-600">
                    <span>Все новости</span>
                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                </a>
            </div>

            <div class="grid lg:grid-cols-3 gap-8">
                <?php foreach ($newsItems as $item):
                    $ts = strtotime($item['published_at']);
                    $dateStr = date('j', $ts) . ' ' . $monthsFull[(int)date('n', $ts)] . ' ' . date('Y', $ts);
                ?>
                <a href="news.php?article=<?= (int)$item['id'] ?>"
                   class="group bg-white rounded-3xl overflow-hidden shadow-lg btn-hover cursor-pointer border-2 border-gray-100 hover:border-rose-200 block">
                    <div class="relative h-64 overflow-hidden">
                        <img
                            src="<?= htmlspecialchars($item['image_url']) ?>"
                            alt="<?= htmlspecialchars($item['title']) ?>"
                            class="w-full h-full object-cover"
                        >
                        <div class="absolute inset-0 bg-black/50"></div>
                        <div class="absolute top-4 left-4">
                            <span class="px-4 py-2 bg-rose-400 text-white text-xs font-bold rounded-full">
                                <?= htmlspecialchars($item['category']) ?>
                            </span>
                        </div>
                        <div class="absolute bottom-4 left-4 right-4">
                            <h3 class="text-white font-bold text-xl mb-2"><?= htmlspecialchars($item['title']) ?></h3>
                            <p class="text-white/80 text-sm font-medium"><?= $dateStr ?></p>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== СОБЫТИЯ ===== -->
    <section id="events" class="py-20 px-4 bg-white/60 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <h2 class="text-4xl lg:text-5xl font-black text-gray-900 mb-3">Ближайшие события</h2>
                    <p class="text-gray-600 font-medium text-lg">Зарегистрируйтесь прямо сейчас</p>
                </div>
                <a href="events.php" class="btn-hover hidden md:flex items-center space-x-2 text-rose-500 font-bold hover:text-rose-600">
                    <span>Все события</span>
                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                </a>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($events as $event) include 'includes/event_card.php'; ?>
            </div>
        </div>
    </section>

    <!-- ===== ЧЕМПИОНЫ ===== -->
    <section id="champions" class="py-20 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <h2 class="text-4xl lg:text-5xl font-black text-gray-900 mb-3">Наши чемпионы</h2>
                    <p class="text-gray-600 font-medium text-lg">Гордость клуба PetLove</p>
                </div>
                <a href="champions.php" class="btn-hover hidden md:flex items-center space-x-2 text-rose-500 font-bold hover:text-rose-600">
                    <span>Все чемпионы</span>
                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                </a>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($champions as $champion): ?>
                <div class="group relative rounded-3xl overflow-hidden shadow-xl btn-hover cursor-pointer">
                    <div class="relative h-96">
                        <img
                            src="<?= htmlspecialchars($champion['image_url']) ?>"
                            alt="<?= htmlspecialchars($champion['name']) ?>"
                            class="w-full h-full object-cover"
                        >
                        <div class="absolute inset-0 bg-black/60"></div>

                        <div class="absolute top-4 right-4">
                            <div class="w-12 h-12 bg-amber-400 rounded-full flex items-center justify-center shadow-lg">
                                <i data-lucide="award" class="w-6 h-6 text-amber-900"></i>
                            </div>
                        </div>

                        <div class="absolute bottom-0 left-0 right-0 p-6">
                            <div class="text-amber-400 font-bold text-sm mb-2"><?= htmlspecialchars($champion['title']) ?></div>
                            <h3 class="text-white font-black text-2xl mb-1"><?= htmlspecialchars($champion['name']) ?></h3>
                            <p class="text-white/80 font-medium"><?= htmlspecialchars($champion['breed']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-12">
                <a href="champions.php" class="btn-hover inline-block px-8 py-4 bg-white text-gray-800 font-bold rounded-2xl border-2 border-gray-200 hover:bg-gray-100">
                    Посмотреть всех чемпионов
                </a>
            </div>
        </div>
    </section>

    <!-- ===== УСЛУГИ ===== -->
    <section class="py-20 px-4 bg-rose-400 text-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-4xl lg:text-5xl font-black mb-3">Наши услуги</h2>
                <p class="text-white/90 font-medium text-lg">Всё для вас и ваших питомцев</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <a href="knowledge_base.php" class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 btn-hover hover:bg-white/20 border border-white/20 block">
                    <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mb-4">
                        <i data-lucide="book-open" class="w-7 h-7 text-white"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">База знаний</h3>
                    <p class="text-white/80 font-medium">Статьи, гайды, советы</p>
                </a>
                <a href="events.php" class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 btn-hover hover:bg-white/20 border border-white/20 block">
                    <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mb-4">
                        <i data-lucide="calendar" class="w-7 h-7 text-white"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Выставки</h3>
                    <p class="text-white/80 font-medium">Регистрация онлайн</p>
                </a>
                <a href="knowledge_base.php?slug=breeders" class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 btn-hover hover:bg-white/20 border border-white/20 block">
                    <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mb-4">
                        <i data-lucide="users" class="w-7 h-7 text-white"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Питомники</h3>
                    <p class="text-white/80 font-medium">Проверенные заводчики</p>
                </a>
                <a href="knowledge_base.php?slug=training" class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 btn-hover hover:bg-white/20 border border-white/20 block">
                    <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mb-4">
                        <i data-lucide="award" class="w-7 h-7 text-white"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Обучение</h3>
                    <p class="text-white/80 font-medium">Курсы и семинары</p>
                </a>
            </div>
        </div>
    </section>

    <?php include 'includes/event_modals.php'; ?>
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/page_scripts.php'; ?>
</body>
</html>
