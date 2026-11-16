<!doctype html>
<html lang="el">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? '') ?> · <?= e(site_name()) ?></title><meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/icon-192.png"><link rel="stylesheet" href="<?= asset('css/fonts.css') ?>"><link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body><main id="main" class="wrap"><div class="auth">
<?php foreach (take_flash() as $f): ?><div class="alert <?= e($f['type']) ?>" style="margin-bottom:12px"><?= e($f['msg']) ?></div><?php endforeach ?>
<?= $content ?>
</div></main></body></html>
