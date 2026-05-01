<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/image_upload.php';

$viewer = current_user();
if (!$viewer || (int)($viewer['role'] ?? 0) < ROLE_EDITOR) {
    header('Location: ../knowledge_base.php');
    exit;
}

$slugs = $pdo->query("SELECT slug, name FROM slugs ORDER BY name")->fetchAll();

$editId = (int)($_GET['id'] ?? 0);
$existing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM knowledge_base WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $existing = $stmt->fetch() ?: null;
    if (!$existing) {
        header('Location: ../knowledge_base.php');
        exit;
    }
}

$returnTo = $_GET['return_to'] ?? ($_POST['return_to'] ?? '../knowledge_base.php');
$cancelUrl = $existing ? ('../knowledge_base.php?article=' . (int)$existing['id']) : '../knowledge_base.php';
if ($returnTo) $cancelUrl = $returnTo;

$errors = [];
$values = $existing ? [
    'title'        => $existing['title'],
    'slug'         => $existing['slug'],
    'published_at' => $existing['published_at'],
    'excerpt'      => $existing['excerpt'] ?? '',
    'content'      => $existing['content'] ?? '',
] : [
    'title'        => '',
    'slug'         => $slugs[0]['slug'] ?? '',
    'published_at' => date('Y-m-d'),
    'excerpt'      => '',
    'content'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? 'save';

    if ($action === 'delete' && $existing) {
        if (!empty($existing['image_url']) && str_starts_with($existing['image_url'], 'uploads/')) {
            delete_local_upload($existing['image_url']);
        }
        $pdo->prepare("DELETE FROM knowledge_base WHERE id = :id")->execute([':id' => $existing['id']]);
        $back = $_POST['return_to'] ?? '../knowledge_base.php';
        header('Location: ' . $back);
        exit;
    }

    $values['title']        = trim($_POST['title'] ?? '');
    $values['slug']         = trim($_POST['slug'] ?? '');
    $values['published_at'] = trim($_POST['published_at'] ?? '');
    $values['excerpt']      = trim($_POST['excerpt'] ?? '');
    $values['content']      = trim($_POST['content'] ?? '');
    $imageData              = $_POST['image_data'] ?? '';

    if ($values['title'] === '' || mb_strlen($values['title']) > 255) $errors[] = 'Заголовок обязателен (до 255 символов).';
    $slugOk = false;
    foreach ($slugs as $s) if ($s['slug'] === $values['slug']) { $slugOk = true; break; }
    if (!$slugOk) $errors[] = 'Выберите категорию (slug).';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['published_at'])) $errors[] = 'Укажите корректную дату публикации.';
    if ($values['content'] === '') $errors[] = 'Текст статьи не может быть пустым.';
    if (!$existing && $imageData === '') $errors[] = 'Загрузите и обрежьте изображение.';

    if (!$errors) {
        $imagePath = $existing['image_url'] ?? null;
        if ($imageData !== '') {
            $saved = save_base64_image($imageData, 'kb', 'a');
            if (!$saved) {
                $errors[] = 'Не удалось сохранить изображение. Поддерживаются JPG, PNG, WEBP до 5 МБ.';
            } else {
                if ($existing && !empty($existing['image_url']) && str_starts_with($existing['image_url'], 'uploads/')) {
                    delete_local_upload($existing['image_url']);
                }
                $imagePath = $saved;
            }
        }
    }

    if (!$errors) {
        if ($existing) {
            $upd = $pdo->prepare(
                "UPDATE knowledge_base SET title=:t, slug=:s, image_url=:i, excerpt=:e, content=:c, published_at=:p
                  WHERE id=:id"
            );
            $upd->execute([
                ':t' => $values['title'],
                ':s' => $values['slug'],
                ':i' => $imagePath,
                ':e' => $values['excerpt'] !== '' ? $values['excerpt'] : null,
                ':c' => $values['content'],
                ':p' => $values['published_at'],
                ':id' => $existing['id'],
            ]);
            header('Location: ../knowledge_base.php?article=' . (int)$existing['id']);
            exit;
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO knowledge_base (title, slug, image_url, excerpt, content, published_at)
                 VALUES (:t, :s, :i, :e, :c, :p)"
            );
            $ins->execute([
                ':t' => $values['title'],
                ':s' => $values['slug'],
                ':i' => $imagePath,
                ':e' => $values['excerpt'] !== '' ? $values['excerpt'] : null,
                ':c' => $values['content'],
                ':p' => $values['published_at'],
            ]);
            header('Location: ../knowledge_base.php?article=' . (int)$pdo->lastInsertId());
            exit;
        }
    }
}

$pageTitle = $existing ? 'Редактировать статью' : 'Новая статья';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - PetLove Club</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css">
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>
    <link rel="stylesheet" href="../styles/tailwind.css">
    <link rel="shortcut icon" href="../favicon.png" type="image/x-icon">
</head>
<body class="bg-amber-50 min-h-screen">

<?php $active = 'kb'; include __DIR__ . '/../includes/nav.php'; ?>

<section class="pt-32 pb-20 px-4">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn-hover inline-flex items-center gap-2 text-gray-600 hover:text-rose-500 font-bold text-sm">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Назад</span>
            </a>
        </div>

        <h1 class="text-4xl font-black text-gray-900 mb-2"><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="text-gray-600 mb-8">Загрузите обложку, выберите категорию и напишите текст. Поддерживается Markdown.</p>

        <?php if ($errors): ?>
            <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                <?php foreach ($errors as $e): ?>
                    <p class="text-rose-600 text-sm font-medium"><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" id="content-form" class="bg-white rounded-3xl shadow-xl p-6 sm:p-8 border-2 border-gray-100 space-y-5">
            <input type="hidden" name="image_data"  id="image-data" value="">
            <input type="hidden" name="form_action" id="form-action" value="save">
            <input type="hidden" name="return_to"   value="<?= htmlspecialchars($returnTo) ?>">

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Обложка (16:9)</label>
                <div class="flex items-center gap-4">
                    <div id="image-preview" class="w-48 h-28 rounded-xl bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 overflow-hidden flex-shrink-0">
                        <?php if ($existing && !empty($existing['image_url'])): ?>
                            <img src="<?= htmlspecialchars($existing['image_url']) ?>" class="w-full h-full object-cover" alt="">
                        <?php else: ?>
                            <i data-lucide="image" class="w-8 h-8"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <button type="button" id="image-btn" class="btn-hover px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-sm inline-flex items-center gap-2">
                            <i data-lucide="upload" class="w-4 h-4"></i>
                            <span><?= $existing ? 'Заменить обложку' : 'Загрузить и обрезать' ?></span>
                        </button>
                        <input type="file" id="image-input" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <p class="text-xs text-gray-500 mt-1.5">JPG, PNG или WEBP, до 5 МБ.</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Заголовок</label>
                <input type="text" name="title" required maxlength="255" value="<?= htmlspecialchars($values['title']) ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Категория</label>
                    <select name="slug" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors bg-white">
                        <?php foreach ($slugs as $s): ?>
                            <option value="<?= htmlspecialchars($s['slug']) ?>" <?= $s['slug']===$values['slug']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Дата публикации</label>
                    <input type="date" name="published_at" required value="<?= htmlspecialchars($values['published_at']) ?>"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Краткое описание (необязательно)</label>
                <textarea name="excerpt" rows="2" maxlength="500"
                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors"><?= htmlspecialchars($values['excerpt']) ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Текст статьи (Markdown)</label>
                <textarea name="content" rows="16" required
                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors font-mono text-sm"><?= htmlspecialchars($values['content']) ?></textarea>
                <details class="mt-2 text-xs text-gray-600">
                    <summary class="cursor-pointer font-bold text-gray-700 hover:text-rose-500 select-none">Шпаргалка по Markdown</summary>
                    <div class="mt-2 p-3 bg-gray-50 border border-gray-200 rounded-xl space-y-2 font-mono leading-relaxed">
                        <p><span class="text-gray-500"># Заголовок 1 уровня</span> · <span class="text-gray-500">## Заголовок 2 уровня</span></p>
                        <p>**жирный** · *курсив* · ~~зачёркнутый~~</p>
                        <p>
                            <span class="block text-gray-500">Ссылка - текст в квадратных скобках, URL в круглых:</span>
                            [PetLove Club](https://petloveclub.ru)
                        </p>
                        <p>
                            <span class="block text-gray-500">Маркированный список - дефис и пробел в начале каждой строки:</span>
                            - первый пункт<br>
                            - второй пункт<br>
                            - третий пункт
                        </p>
                        <p>
                            <span class="block text-gray-500">Нумерованный список - число с точкой:</span>
                            1. первый шаг<br>
                            2. второй шаг<br>
                            3. третий шаг
                        </p>
                        <p>
                            <span class="block text-gray-500">Вложенный пункт - 2 пробела перед дефисом:</span>
                            - родитель<br>
                            &nbsp;&nbsp;- ребёнок<br>
                            &nbsp;&nbsp;- ребёнок
                        </p>
                        <p><span class="text-gray-500">Цитата:</span> &gt; текст цитаты</p>
                        <p><span class="text-gray-500">Картинка:</span> ![подпись](https://example.com/image.jpg)</p>
                        <p class="text-gray-500 not-italic">Между абзацами оставляйте пустую строку, иначе они склеиваются в один.</p>
                    </div>
                </details>
            </div>

            <div class="flex flex-wrap gap-3 justify-between">
                <div class="flex gap-3">
                    <button type="submit" class="btn-hover px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500"><?= $existing ? 'Сохранить' : 'Опубликовать' ?></button>
                    <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn-hover px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl">Отмена</a>
                </div>
                <?php if ($existing): ?>
                <button type="button" id="delete-btn" class="btn-hover px-6 py-3 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl inline-flex items-center gap-2">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span>Удалить</span>
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>

<?php include __DIR__ . '/../includes/crop_modal.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/page_scripts.php'; ?>

<script>
(function () {
    const imgBtn   = document.getElementById('image-btn');
    const imgInput = document.getElementById('image-input');
    const imgData  = document.getElementById('image-data');
    const imgPrev  = document.getElementById('image-preview');
    const formEl   = document.getElementById('content-form');
    const formAct  = document.getElementById('form-action');
    const delBtn   = document.getElementById('delete-btn');
    const isEdit   = <?= $existing ? 'true' : 'false' ?>;

    imgBtn.addEventListener('click', () => imgInput.click());
    imgInput.addEventListener('change', () => {
        const f = imgInput.files[0];
        if (!f) return;
        window.openCropModal(f, { aspectRatio: 16 / 9, outSize: 1600 }, (dataUrl) => {
            imgData.value = dataUrl;
            imgPrev.innerHTML = '<img src="' + dataUrl + '" class="w-full h-full object-cover" alt="">';
        });
        imgInput.value = '';
    });

    formEl.addEventListener('submit', (e) => {
        if (formAct.value === 'delete') return;
        if (!isEdit && !imgData.value) {
            e.preventDefault();
            alert('Сначала загрузите и обрежьте обложку.');
        }
    });

    if (delBtn) {
        delBtn.addEventListener('click', () => {
            if (!confirm('Удалить статью? Это действие нельзя отменить.')) return;
            formAct.value = 'delete';
            formEl.submit();
        });
    }
})();
</script>
</body>
</html>
