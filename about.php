<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>О клубе - PetLove Club</title>
    <meta name="description" content="История, миссия и команда PetLove Club. Узнайте, как клуб развивает культуру ответственного содержания и разведения животных в Ярославле уже более 25 лет.">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="./styles/tailwind.css">
    <link rel="shortcut icon" href="./favicon.png" type="image/x-icon">
</head>
<body class="bg-amber-50 min-h-screen">

    <?php $active = 'about'; include 'includes/nav.php'; ?>

    <section class="pt-32 pb-16 px-4">
        <div class="max-w-4xl mx-auto text-center">
            <div class="inline-flex items-center space-x-2 px-4 py-2 bg-rose-100 rounded-full mb-5">
                <i data-lucide="info" class="w-4 h-4 text-rose-500"></i>
                <span class="text-sm font-bold text-rose-700">О нас</span>
            </div>
            <h1 class="text-5xl lg:text-6xl font-black text-gray-900 mb-5">О клубе PetLove</h1>
            <p class="text-xl text-gray-600 font-medium max-w-2xl mx-auto">
                Старейший клуб любителей породистых животных в Ярославле - с 1999 года.
            </p>
        </div>
    </section>

    <section class="pb-20 px-4">
        <div class="max-w-5xl mx-auto grid md:grid-cols-3 gap-6">
            <div class="bg-white rounded-3xl shadow-lg p-8 btn-hover border-2 border-gray-100 hover:border-rose-200">
                <div class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center mb-4">
                    <i data-lucide="users" class="w-7 h-7 text-rose-500"></i>
                </div>
                <div class="text-4xl font-black text-rose-500 mb-1">2500+</div>
                <p class="text-gray-600 font-medium">членов клуба со всей России</p>
            </div>
            <div class="bg-white rounded-3xl shadow-lg p-8 btn-hover border-2 border-gray-100 hover:border-rose-200">
                <div class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center mb-4">
                    <i data-lucide="calendar" class="w-7 h-7 text-rose-500"></i>
                </div>
                <div class="text-4xl font-black text-rose-500 mb-1">150+</div>
                <p class="text-gray-600 font-medium">выставок и мероприятий ежегодно</p>
            </div>
            <div class="bg-white rounded-3xl shadow-lg p-8 btn-hover border-2 border-gray-100 hover:border-rose-200">
                <div class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center mb-4">
                    <i data-lucide="award" class="w-7 h-7 text-rose-500"></i>
                </div>
                <div class="text-4xl font-black text-rose-500 mb-1">45+</div>
                <p class="text-gray-600 font-medium">пород, представленных в клубе</p>
            </div>
        </div>
    </section>

    <section class="pb-28 px-4">
        <div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-xl p-10 space-y-6 text-gray-700 leading-relaxed border-2 border-gray-100">
            <h2 class="text-3xl font-black text-gray-900">Наша история</h2>
            <p>
                PetLove Club основан в 1999 году группой ярославских заводчиков, объединённых общей идеей -
                создать профессиональное сообщество для владельцев породистых животных. За 25 лет работы клуб
                провёл сотни выставок и помог тысячам питомцев получить чемпионские титулы.
            </p>
            <h2 class="text-3xl font-black text-gray-900 pt-4">Чем мы занимаемся</h2>
            <ul class="space-y-3 list-disc pl-6">
                <li>Организация выставок и племенных смотров российского и международного уровня.</li>
                <li>Сопровождение племенной работы и оформление документов РКФ/FCI.</li>
                <li>Образовательные программы для заводчиков, хендлеров и владельцев.</li>
                <li>Партнёрство с ветеринарными клиниками и поддержка членов клуба.</li>
            </ul>
            <h2 class="text-3xl font-black text-gray-900 pt-4">Наши ценности</h2>
            <p>
                Мы верим, что забота о породистых животных - это ответственность и культура. Клуб объединяет
                людей, для которых питомцы - часть семьи, а профессиональный подход и взаимоуважение -
                основа сообщества.
            </p>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/page_scripts.php'; ?>
</body>
</html>
