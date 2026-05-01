<?php
if (session_status() === PHP_SESSION_NONE) {
    // Behind a reverse proxy that terminates TLS, Apache sets HTTPS=on
    // via SetEnvIf X-Forwarded-Proto. Mark the session cookie Secure to
    // match, so browsers don't drop it when the upstream is HTTPS.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('PETLOVESID');
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $cache = $stmt->fetch() ?: null;
    if (!$cache) {
        unset($_SESSION['user_id']);
    }
    return $cache;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function display_name(array $u): string {
    $full = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $full !== '' ? $full : $u['username'];
}

function avatar_or_default(?array $u): string {
    if ($u && !empty($u['avatar_url'])) return $u['avatar_url'];
    return '';
}

function is_email_verified(?array $u): bool {
    if (!$u) return false;
    if (!mail_enabled()) return true;
    return !empty($u['email_verified_at']);
}

function is_restricted(?array $u): bool {
    if (!$u) return false;
    if (!empty($u['banned'])) return true;
    if (mail_enabled() && empty($u['email_verified_at'])) return true;
    return false;
}

function restricted_reason(?array $u): ?string {
    if (!$u) return null;
    if (!empty($u['banned'])) return 'banned';
    if (mail_enabled() && empty($u['email_verified_at'])) return 'unverified';
    return null;
}

function restricted_message(?array $u, string $action = 'это действие'): string {
    $r = restricted_reason($u);
    if ($r === 'banned')     return 'Ваш аккаунт заблокирован - ' . $action . ' недоступно.';
    if ($r === 'unverified') return 'Подтвердите email, чтобы продолжить - ' . $action . ' недоступно.';
    return '';
}

const ROLE_USER   = 0;
const ROLE_EDITOR = 1;
const ROLE_ADMIN  = 2;
const ROLE_OWNER  = 3;

function role_info(int $role): ?array {
    switch ($role) {
        case ROLE_EDITOR:
            return ['name' => 'Редактор', 'desc' => 'Редактор - публикует и редактирует статьи и новости.', 'icon' => 'pen-tool', 'text' => 'text-sky-500'];
        case ROLE_ADMIN:
            return ['name' => 'Админ',    'desc' => 'Администратор - управляет пользователями и контентом клуба.', 'icon' => 'shield',   'text' => 'text-rose-500'];
        case ROLE_OWNER:
            return ['name' => 'Владелец', 'desc' => 'Владелец клуба - полный доступ ко всем функциям сайта.', 'icon' => 'crown',    'text' => 'text-amber-500'];
        default:
            return null;
    }
}

function nav_base(): string {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#/(edit|admin)/[^/]+\.php$#', $script)) return '../';
    return '';
}

function asset_url(?string $path): string {
    $path = (string)$path;
    if ($path === '') return '';
    if (preg_match('#^(https?:)?//#i', $path)) return $path;
    if ($path[0] === '/') return $path;
    return nav_base() . $path;
}

function pet_type_label(string $type): string {
    if ($type === 'dog')   return '🐶 Собака';
    if ($type === 'cat')   return '🐱 Кошка';
    if ($type === 'other') return '🐾 Другое';
    return $type;
}

function role_badge(?array $u, string $size = 'md'): string {
    if (!$u) return '';
    $info = role_info((int)($u['role'] ?? 0));
    if (!$info) return '';
    $iconCls = $size === 'sm' ? 'w-4 h-4' : ($size === 'lg' ? 'w-6 h-6' : 'w-5 h-5');
    return '<span class="role-badge inline-flex items-center justify-center" data-role-tip="' . htmlspecialchars($info['desc']) . '">'
         . '<i data-lucide="' . $info['icon'] . '" class="' . $iconCls . ' ' . $info['text'] . '"></i>'
         . '</span>';
}
