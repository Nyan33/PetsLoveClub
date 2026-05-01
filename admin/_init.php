<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/image_upload.php';

$adminUser = current_user();
if (!$adminUser || (int)($adminUser['role'] ?? 0) < ROLE_ADMIN) {
    header('Location: ../index.php');
    exit;
}

const ADMIN_ROLE_LIMIT = ROLE_ADMIN;
$adminRole  = (int)$adminUser['role'];
$isOwner    = $adminRole >= ROLE_OWNER;

/**
 * Visible role ceiling for the dashboard:
 * - admin sees only role 0 and 1 (and themselves)
 * - owner sees 0, 1, 2 (everyone except other owners and themselves)
 */
function admin_visible_max_role(): int {
    global $adminRole;
    return $adminRole >= ROLE_OWNER ? ROLE_ADMIN : ROLE_EDITOR;
}

/**
 * Maximum role this admin can assign.
 * - owner can assign 0..2 (admin) but never owner
 * - admin can assign 0..1 (editor) only
 */
function admin_assignable_max_role(): int {
    global $adminRole;
    return $adminRole >= ROLE_OWNER ? ROLE_ADMIN : ROLE_EDITOR;
}

function admin_can_target_user(array $target): bool {
    global $adminUser, $adminRole;
    if ((int)$target['id'] === (int)$adminUser['id']) return false; // never act on self
    $tRole = (int)($target['role'] ?? 0);
    if ($adminRole >= ROLE_OWNER) {
        return $tRole < ROLE_OWNER;
    }
    return $tRole < ROLE_ADMIN;
}

function admin_flash(string $msg, string $type = 'ok'): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['admin_flash'] = ['msg' => $msg, 'type' => $type];
}
function admin_flash_pop(): ?array {
    if (empty($_SESSION['admin_flash'])) return null;
    $f = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    return $f;
}

function admin_csrf(): string {
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['admin_csrf'];
}
function admin_csrf_check(): void {
    $t = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf'] ?? '', $t)) {
        http_response_code(400);
        die('CSRF mismatch');
    }
}
