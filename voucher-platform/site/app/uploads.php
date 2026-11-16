<?php
declare(strict_types=1);

const UPLOAD_MAX_BYTES = 10 * 1024 * 1024;
const UPLOAD_MAX_DIM = 2000;

/**
 * Validates and stores an uploaded image. Returns the public path (/uploads/Y/m/slug.ext) or throws with a user-facing message.
 * Allowed: JPG, PNG, WebP, GIF. Checked with getimagesize(), resized to 2000px, re-encoded (drops any embedded payload).
 */
function store_image(array $file, string $nameHint): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Η εικόνα δεν ανέβηκε. Δοκίμασε ξανά.');
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Η εικόνα είναι μεγαλύτερη από 10 MB.');
    }
    if (!is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') {
        throw new RuntimeException('Μη έγκυρο αρχείο.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('Επιτρέπονται μόνο εικόνες JPG, PNG, WebP ή GIF.');
    }
    $ext = $types[$info[2]];
    $dir = '/uploads/' . date('Y') . '/' . date('m');
    if (!is_dir(ROOT . $dir)) {
        mkdir(ROOT . $dir, 0775, true);
    }
    $base = slugify(pathinfo($nameHint, PATHINFO_FILENAME) ?: 'image');
    $name = $base . '.' . $ext;
    $i = 2;
    while (is_file(ROOT . $dir . '/' . $name)) {
        $name = $base . '-' . $i++ . '.' . $ext;
    }
    $dest = ROOT . $dir . '/' . $name;

    if (function_exists('imagecreatefromstring') && $ext !== 'gif') {
        $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
        if (!$src) {
            throw new RuntimeException('Η εικόνα δεν μπορεί να διαβαστεί.');
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, UPLOAD_MAX_DIM / max($w, $h));
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = match ($ext) {
            'jpg' => imagejpeg($dst, $dest, 84),
            'png' => imagepng($dst, $dest, 7),
            'webp' => imagewebp($dst, $dest, 82),
        };
        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok) {
            throw new RuntimeException('Η εικόνα δεν αποθηκεύτηκε.');
        }
    } else {
        if (!@move_uploaded_file($file['tmp_name'], $dest) && !@copy($file['tmp_name'], $dest)) {
            throw new RuntimeException('Η εικόνα δεν αποθηκεύτηκε.');
        }
    }
    @chmod($dest, 0644);
    return $dir . '/' . $name;
}
