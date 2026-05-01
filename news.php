<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новости - PetLove Club</title>
    <meta name="description" content="Новости PetLove Club: анонсы выставок, отчёты с мероприятий, достижения членов клуба и важные объявления для заводчиков и владельцев.">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="./vendor/marked.min.js"></script>
    <link rel="stylesheet" href="./styles/tailwind.css">
    <link rel="shortcut icon" href="./favicon.png" type="image/x-icon">
</head>
<body class="bg-amber-50 min-h-screen">

    <?php $active = 'news'; include 'includes/nav.php'; ?>

    <section class="pt-32 pb-16 px-4">
        <div class="max-w-7xl mx-auto text-center">
            <div class="inline-flex items-center space-x-2 px-4 py-2 bg-rose-100 rounded-full mb-5 border border-rose-200">
                <i data-lucide="newspaper" class="w-4 h-4 text-rose-500"></i>
                <span class="text-sm font-bold text-rose-700">Актуально</span>
            </div>
            <h1 class="text-5xl lg:text-6xl font-black text-gray-900 mb-5">Новости клуба</h1>
            <p class="text-xl text-gray-600 font-medium max-w-2xl mx-auto">
                Последние события, анонсы мероприятий и достижения членов PetLove Club
            </p>
        </div>
    </section>

    <section class="pb-28 px-4">
        <div class="max-w-7xl mx-auto">
            <?php
            $months = [
                1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',
                7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'
            ];

            $newsItems = $pdo->query(
                "SELECT n.*, s.name AS category
                   FROM news n
                   JOIN slugs s ON s.slug = n.slug
                  ORDER BY n.published_at DESC"
            )->fetchAll();

            $articleParam = (int)($_GET['article'] ?? 0);
            $canEdit = ($viewer = current_user()) && (int)($viewer['role'] ?? 0) >= ROLE_EDITOR;
            ?>
            <div class="grid lg:grid-cols-3 gap-8">
                <?php if ($canEdit): ?>
                <a href="edit/news.php"
                   class="group bg-white rounded-3xl overflow-hidden shadow-lg btn-hover border-2 border-dashed border-rose-300 hover:border-rose-500 hover:bg-rose-50 flex flex-col items-center justify-center text-center min-h-[28rem] p-8">
                    <div class="w-20 h-20 rounded-2xl bg-rose-100 group-hover:bg-rose-200 flex items-center justify-center mb-4 transition-colors">
                        <i data-lucide="plus" class="w-10 h-10 text-rose-500"></i>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 mb-1">Добавить новость</h3>
                    <p class="text-sm text-gray-500 font-medium">Создать новый материал</p>
                </a>
                <?php endif; ?>
            <?php
            if (empty($newsItems)) {
                if (!$canEdit) {
                    echo '<p class="col-span-full text-center text-gray-400 text-lg py-20">Новостей пока нет.</p>';
                }
            } else {
                foreach ($newsItems as $item):
                    $ts      = strtotime($item['published_at']);
                    $dateStr = date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
            ?>
            <div class="group bg-white rounded-3xl overflow-hidden shadow-lg btn-hover border-2 border-gray-100 hover:border-rose-200 flex flex-col">
                <div class="relative h-64 overflow-hidden">
                    <img src="<?= htmlspecialchars($item['image_url']) ?>"
                         alt="<?= htmlspecialchars($item['title']) ?>"
                         class="w-full h-full object-cover">
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
                <div class="p-6 flex flex-col flex-1">
                    <?php if (!empty($item['description'])): ?>
                        <p class="text-gray-600 text-sm leading-relaxed mb-4"><?= htmlspecialchars($item['description']) ?></p>
                    <?php endif; ?>
                    <div class="mt-auto flex flex-wrap gap-2">
                        <button type="button"
                                onclick="openNews(<?= (int)$item['id'] ?>)"
                                class="btn-hover inline-flex items-center gap-2 px-5 py-2.5 bg-rose-400 text-white font-bold text-sm rounded-xl hover:bg-rose-500">
                            <i data-lucide="book-open-text" class="w-4 h-4"></i>
                            <span>Читать</span>
                        </button>
                        <?php if ($canEdit): ?>
                            <a href="edit/news.php?id=<?= (int)$item['id'] ?>"
                               class="btn-hover inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-700 font-bold text-sm rounded-xl hover:bg-gray-200">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                <span>Редактировать</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
                endforeach;
            }
            ?>
            </div>
        </div>
    </section>

    <!-- News modal -->
    <div id="news-modal" class="hidden fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm items-center justify-center p-3 sm:p-6">
        <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full relative animate-fade-in border-2 border-gray-100 flex flex-col overflow-hidden" style="max-height: calc(100vh - 1.5rem); max-height: calc(100dvh - 1.5rem);">
            <button type="button" onclick="closeNews()"
                    class="btn-hover absolute top-4 right-4 z-10 w-10 h-10 rounded-full bg-white/90 hover:bg-white border border-gray-200 flex items-center justify-center shadow-lg">
                <i data-lucide="x" class="w-5 h-5 text-gray-700"></i>
            </button>
            <div class="relative h-40 sm:h-56 flex-shrink-0">
                <img id="news-image" src="" alt="" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                <div class="absolute top-4 left-4">
                    <span id="news-category" class="px-4 py-2 bg-rose-400 text-white text-xs font-bold rounded-full shadow"></span>
                </div>
                <div class="absolute bottom-4 left-6 right-16">
                    <h2 id="news-title" class="text-white font-black text-lg sm:text-2xl lg:text-3xl mb-1 line-clamp-2"></h2>
                    <p id="news-date" class="text-white/80 text-sm font-medium"></p>
                </div>
            </div>
            <div class="p-6 sm:p-8 overflow-y-auto flex-1 min-h-0">
                <article id="news-content" class="prose-content text-gray-700 leading-relaxed text-base"></article>
            </div>
        </div>
    </div>

    <?php
    $newsJs = [];
    foreach ($newsItems as $n) {
        $ts = strtotime($n['published_at']);
        $newsJs[(int)$n['id']] = [
            'title'    => $n['title'],
            'category' => $n['category'],
            'image'    => $n['image_url'],
            'content'  => $n['content'] ?? ($n['description'] ?? ''),
            'date'     => date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts),
        ];
    }
    ?>
    <script>
        const NEWS_ITEMS = <?= json_encode($newsJs, JSON_UNESCAPED_UNICODE) ?>;
        const PRELOAD_NEWS = <?= $articleParam ?: 'null' ?>;

        function openNews(id) {
            const n = NEWS_ITEMS[id];
            if (!n) return;
            document.getElementById('news-image').src = n.image;
            document.getElementById('news-image').alt = n.title;
            document.getElementById('news-title').textContent = n.title;
            document.getElementById('news-category').textContent = n.category;
            document.getElementById('news-date').textContent = n.date;
            const contentEl = document.getElementById('news-content');
            if (window.marked) {
                marked.setOptions({ breaks: true, gfm: true });
                contentEl.innerHTML = marked.parse(n.content || '');
            } else {
                contentEl.textContent = n.content || '';
            }

            const m = document.getElementById('news-modal');
            m.classList.remove('hidden');
            m.classList.add('flex');
            document.body.style.overflow = 'hidden';
            if (window.lucide) lucide.createIcons();
        }
        function closeNews() {
            const m = document.getElementById('news-modal');
            m.classList.add('hidden');
            m.classList.remove('flex');
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeNews(); });
        document.getElementById('news-modal').addEventListener('click', e => {
            if (e.target.id === 'news-modal') closeNews();
        });

        if (PRELOAD_NEWS && NEWS_ITEMS[PRELOAD_NEWS]) {
            openNews(PRELOAD_NEWS);
        }
    </script>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/page_scripts.php'; ?>
</body>
</html>
