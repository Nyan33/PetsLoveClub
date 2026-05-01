<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чемпионы - PetLove Club</title>
    <meta name="description" content="Гордость PetLove Club - лучшие питомцы клуба, победители российских и международных выставок. Истории, награды и фото чемпионов разных пород.">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="./styles/tailwind.css">
    <link rel="shortcut icon" href="./favicon.png" type="image/x-icon">
</head>
<body class="bg-amber-50 min-h-screen">

    <?php $active = 'champions'; include 'includes/nav.php'; ?>

    <section class="pt-32 pb-16 px-4">
        <div class="max-w-7xl mx-auto text-center">
            <div class="inline-flex items-center space-x-2 px-4 py-2 bg-rose-100 rounded-full mb-5">
                <i data-lucide="award" class="w-4 h-4 text-rose-500 fill-rose-500"></i>
                <span class="text-sm font-bold text-rose-700">Зал славы</span>
            </div>
            <h1 class="text-5xl lg:text-6xl font-black text-gray-900 mb-5">Наши чемпионы</h1>
            <p class="text-xl text-gray-600 font-medium max-w-2xl mx-auto">
                Гордость клуба PetLove - питомцы, завоевавшие высшие титулы на российских и международных выставках
            </p>
        </div>
    </section>

    <section class="pb-28 px-4">
        <div class="max-w-7xl mx-auto">
            <?php
            $champions = $pdo->query(
                "SELECT c.*, u.username AS owner_username, e.title AS event_title
                   FROM champions c
                   LEFT JOIN pets   p ON p.id = c.pet_id
                   LEFT JOIN users  u ON u.id = p.user_id
                   LEFT JOIN events e ON e.id = c.event_id
                  ORDER BY c.year DESC, c.id ASC"
            )->fetchAll();

            if (empty($champions)) {
                echo '<p class="text-center text-gray-400 text-lg py-20">Чемпионы пока не добавлены.</p>';
            } else {
                echo '<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">';
                foreach ($champions as $c):
            ?>
            <div class="group relative rounded-3xl overflow-hidden shadow-xl btn-hover cursor-pointer">
                <div class="relative h-96">
                    <img src="<?= htmlspecialchars($c['image_url']) ?>"
                         alt="<?= htmlspecialchars($c['name']) ?>"
                         class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>

                    <div class="absolute top-4 right-4">
                        <div class="w-12 h-12 bg-amber-400 rounded-full flex items-center justify-center shadow-lg">
                            <i data-lucide="award" class="w-6 h-6 text-amber-900"></i>
                        </div>
                    </div>

                    <div class="absolute bottom-0 left-0 right-0 p-6">
                        <p class="text-amber-400 font-bold text-sm mb-2"><?= htmlspecialchars($c['title']) ?></p>
                        <h3 class="text-white font-black text-2xl mb-1"><?= htmlspecialchars($c['name']) ?></h3>
                        <p class="text-white/70 font-medium text-sm"><?= htmlspecialchars($c['breed']) ?></p>
                        <?php if (!empty($c['event_title'])): ?>
                            <p class="text-white/60 text-xs font-medium mt-1 inline-flex items-center gap-1">
                                <i data-lucide="calendar" class="w-3 h-3"></i>
                                <?= htmlspecialchars($c['event_title']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($c['owner_username'])): ?>
                            <a href="profile.php?u=<?= urlencode($c['owner_username']) ?>"
                               class="btn-hover inline-block mt-2 text-xs font-bold text-white/90 hover:text-amber-300">
                                @<?= htmlspecialchars($c['owner_username']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
                endforeach;
                echo '</div>';
            }
            ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/page_scripts.php'; ?>
</body>
</html>
