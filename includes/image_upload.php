<?php
/**
 * Save a base64-encoded data URL (data:image/...;base64,XXX) to /uploads/<subdir>/.
 * Returns the public path (e.g. "uploads/avatars/12_a1b2.jpg") or null on failure.
 */
function save_base64_image(string $dataUrl, string $subdir, string $namePrefix): ?string {
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#', $dataUrl, $m)) {
        return null;
    }
    $ext  = $m[1] === 'jpeg' ? 'jpg' : $m[1];
    $bin  = base64_decode($m[2], true);
    if ($bin === false) return null;
    if (strlen($bin) > 5 * 1024 * 1024) return null;

    $dir  = dirname(__DIR__) . '/uploads/' . $subdir;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $name = $namePrefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $path = $dir . '/' . $name;
    if (file_put_contents($path, $bin) === false) return null;

    return 'uploads/' . $subdir . '/' . $name;
}

function delete_local_upload(?string $publicPath): void {
    if (!$publicPath) return;
    if (!str_starts_with($publicPath, 'uploads/')) return;
    $abs = dirname(__DIR__) . '/' . $publicPath;
    if (is_file($abs)) @unlink($abs);
}
