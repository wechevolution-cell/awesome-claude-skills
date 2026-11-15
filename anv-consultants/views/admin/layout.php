<?php
use function App\{e, asset, setting, csrf_token, csrf_field};
/** @var string $title @var string $content @var ?array $flash @var bool $bare */
$bare = $bare ?? false;
$sub = trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), 7), '/');
$items = ['' => 'Πίνακας', 'pages' => 'Σελίδες', 'import' => 'Εισαγωγή .md', 'media' => 'Πολυμέσα', 'redirects' => 'Ανακατευθύνσεις', '404' => '404 Monitor', 'settings' => 'Ρυθμίσεις', 'account' => 'Λογαριασμός'];
?><!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($title ?? 'Admin') ?> · Admin <?= e((string) setting('site_name', 'ANV')) ?></title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="<?= $bare ? 'bare' : '' ?>">
<?php if (!$bare): ?>
<header class="top">
  <a class="logo" href="/admin/"><img src="<?= e((string) setting('logo', '/assets/img/logo.png')) ?>" alt="" width="306" height="107"><span>Admin</span></a>
  <nav aria-label="Admin">
    <?php foreach ($items as $k => $l): ?>
      <a href="/admin/<?= e($k) ?>"<?= ($sub === $k || ($k !== '' && str_starts_with($sub, $k . '/'))) ? ' aria-current="page"' : '' ?>><?= e($l) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="right">
    <a href="/" target="_blank" rel="noopener">Προβολή site ↗</a>
    <form method="post" action="/admin/logout"><?= csrf_field() ?><button type="submit" class="linkbtn">Αποσύνδεση</button></form>
  </div>
</header>
<?php endif; ?>
<main class="<?= $bare ? 'auth' : 'main' ?>">
  <?php if (!empty($flash)): ?><div class="flash <?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
  <?= $content ?>
</main>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
