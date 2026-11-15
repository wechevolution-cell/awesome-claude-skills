<?php
use function App\{e, setting, asset, nav_pages, published_children, icon, tel_href, address_line, socials, services, is_admin, abs_url};

/** @var array $page @var array $meta @var string $content @var string $jsonld @var bool $preview */
$site = (string) setting('site_name', 'ANV');
$logo = (string) setting('logo', '/assets/img/logo.png');
$phone = (string) setting('phone', '');
$email = (string) setting('email', '');
$ga = (string) setting('ga4_id', '');
$current = $page['path'];
$nav = nav_pages();
$admin = is_admin();
?><!doctype html>
<html lang="el" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($meta['title']) ?></title>
<meta name="description" content="<?= e($meta['description']) ?>">
<?php if ($meta['noindex']): ?><meta name="robots" content="noindex, follow">
<?php else: ?><meta name="robots" content="index, follow, max-image-preview:large">
<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<link rel="alternate" hreflang="el" href="<?= e($meta['canonical']) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e($meta['canonical']) ?>">
<?php endif; ?>
<meta property="og:type" content="<?= $page['type'] === 'home' ? 'website' : 'article' ?>">
<meta property="og:locale" content="el_GR">
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:title" content="<?= e($meta['title']) ?>">
<meta property="og:description" content="<?= e($meta['description']) ?>">
<meta property="og:url" content="<?= e($meta['canonical']) ?>">
<meta property="og:image" content="<?= e($meta['image']) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($meta['title']) ?>">
<meta name="twitter:description" content="<?= e($meta['description']) ?>">
<meta name="twitter:image" content="<?= e($meta['image']) ?>">
<?php if ($v = setting('gsc_verification')): ?><meta name="google-site-verification" content="<?= e($v) ?>">
<?php endif; ?>
<meta name="theme-color" content="#004c93">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="alternate" type="application/rss+xml" title="<?= e($site) ?>" href="/feed.xml">
<link rel="preload" href="/assets/fonts/comm-greek.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/advent-greek.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<script>document.documentElement.classList.add('js')</script>
<?= $jsonld ?>
</head>
<body class="t-<?= e($page['type']) ?><?= $phone !== '' ? ' has-callbar' : '' ?>" data-ga="<?= e($ga) ?>">
<a class="skip" href="#main">Μετάβαση στο περιεχόμενο</a>

<header class="site-header">
  <div class="wrap">
    <div class="pill">
      <a class="brand" href="/" aria-label="<?= e($site) ?> — Αρχική"><img src="<?= e($logo) ?>" alt="<?= e($site) ?>" width="306" height="107"></a>
      <nav class="nav" id="nav" aria-label="Κύρια πλοήγηση">
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="nav-list"><?= icon('menu', 18) ?><span>Μενού</span></button>
        <ul id="nav-list">
          <?php foreach ($nav as $n):
              $active = $current === $n['path'] ? 'page' : (str_starts_with($current, $n['path']) && $n['path'] !== '/' ? 'true' : '');
              $kids = $n['type'] === 'service' ? published_children((int) $n['id']) : []; ?>
            <li<?= $kids ? ' class="has-sub"' : '' ?>>
              <a href="<?= e($n['path']) ?>"<?= $active ? ' aria-current="' . $active . '"' : '' ?>><?= e($n['nav_label'] ?: $n['title']) ?></a>
              <?php if ($kids): ?>
                <ul class="sub">
                  <?php foreach ($kids as $k): ?><li><a href="<?= e($k['path']) ?>"<?= $current === $k['path'] ? ' aria-current="page"' : '' ?>><?= e($k['nav_label'] ?: $k['title']) ?></a></li><?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php if ($phone !== ''): ?>
        <a class="call-ring" href="<?= e(tel_href($phone)) ?>" aria-label="Κλήση <?= e($phone) ?>"><?= icon('phone', 18) ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main id="main" tabindex="-1">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="wrap">
    <div class="foot-grid">
      <div class="stack">
        <a class="plate" href="/" aria-label="<?= e($site) ?> — Αρχική"><img src="<?= e($logo) ?>" alt="<?= e($site) ?>" width="306" height="107" loading="lazy"></a>
        <p><?= e((string) setting('tagline', '')) ?></p>
      </div>
      <div>
        <h2>Υπηρεσίες</h2>
        <ul><?php foreach (services() as $s): ?><li><a href="<?= e($s['path']) ?>"><?= e($s['title']) ?></a></li><?php endforeach; ?></ul>
      </div>
      <div>
        <h2>Γραφείο</h2>
        <ul><?php foreach ($nav as $n): if ($n['type'] === 'service') { continue; } ?><li><a href="<?= e($n['path']) ?>"><?= e($n['nav_label'] ?: $n['title']) ?></a></li><?php endforeach; ?></ul>
      </div>
      <?php if ($phone !== '' || $email !== '' || address_line() !== '' || socials()): ?>
      <div>
        <h2>Επικοινωνία</h2>
        <ul>
          <?php if ($phone !== ''): ?><li><a href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
          <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
          <?php if (address_line() !== ''): ?><li><?= e(address_line()) ?></li><?php endif; ?>
          <?php foreach (socials() as $label => $url): ?><li><a href="<?= e($url) ?>" rel="noopener me" target="_blank"><?= e($label) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
    <div class="legal">
      <span>© <?= date('Y') ?> <?= e((string) (setting('legal_name') ?: $site)) ?></span>
      <a href="/politiki-aporritou/">Απόρρητο</a>
      <a href="/cookies/">Cookies</a>
      <a href="/oroi-xrisis/">Όροι χρήσης</a>
      <button type="button" data-consent-open>Ρυθμίσεις cookies</button>
    </div>
  </div>
</footer>

<?php if ($phone !== '' || $email !== ''): ?>
<div class="callbar" aria-label="Γρήγορη επικοινωνία">
  <?php if ($phone !== ''): ?><a class="btn btn-primary" href="<?= e(tel_href($phone)) ?>"><?= icon('phone', 18) ?>Κλήση</a><?php endif; ?>
  <?php if ($email !== ''): ?><a class="btn btn-soft" href="mailto:<?= e($email) ?>"><?= icon('mail', 18) ?>Email</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="consent" id="consent" role="dialog" aria-modal="false" aria-labelledby="consent-title" hidden>
  <h2 id="consent-title">Cookies</h2>
  <p>Χρησιμοποιούμε μόνο τα απαραίτητα cookies. Με την αποδοχή σας ενεργοποιούνται και τα στατιστικά (Google Analytics) και ο χάρτης Google. <a href="/cookies/">Περισσότερα</a></p>
  <div class="btns">
    <button class="btn btn-primary" type="button" data-consent="all">Αποδοχή</button>
    <button class="btn btn-soft" type="button" data-consent="necessary">Μόνο απαραίτητα</button>
  </div>
</div>

<?php if ($admin && ($page['id'] ?? 0)): ?>
<div class="adminbar">
  <?php if ($preview): ?><span class="warn">Προεπισκόπηση: <?= e($page['status']) ?></span><?php endif; ?>
  <?php if (!empty($page['is_placeholder'])): ?><span class="warn">Πρότυπο κείμενο</span><?php endif; ?>
  <a href="/admin/pages/edit?id=<?= (int) $page['id'] ?>">Επεξεργασία</a>
  <a href="/admin/">Admin</a>
</div>
<?php endif; ?>

<script src="<?= e(asset('js/site.js')) ?>" defer></script>
<?php if (str_contains($content, 'data-horizon')): ?><script src="<?= e(asset('js/horizon.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
