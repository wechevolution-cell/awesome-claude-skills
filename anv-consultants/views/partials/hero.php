<?php
use function App\{e, picture, breadcrumbs, setting};
/** @var array $page @var string $variant home|page @var ?string $h1Html @var ?string $actions */
$variant = $variant ?? 'page';
$img = $variant === 'home' ? (string) setting('home_hero_image', '') : (string) $page['hero_image'];
$alt = $variant === 'home' ? (string) setting('home_hero_alt', '') : (string) $page['hero_alt'];
$seed = $variant === 'home' ? 41 : 41 + (int) $page['id'] * 7;
$crumbs = $variant === 'home' ? [] : breadcrumbs($page);
?>
<section class="hero hero-<?= e($variant) ?><?= $img !== '' ? ' has-photo' : '' ?>" aria-labelledby="h1">
  <?php if ($img !== ''): ?>
    <div class="hero-media" data-parallax><?= picture($img, $alt, 'fetchpriority="high" decoding="async"') ?></div>
  <?php else: ?>
    <canvas class="horizon" data-horizon="<?= $variant === 'home' ? 'live' : 'still' ?>" data-seed="<?= (int) $seed ?>" aria-hidden="true"></canvas>
  <?php endif; ?>
  <div class="hero-copy wrap">
    <div class="inner">
      <?php if (count($crumbs) > 1): ?>
        <nav class="crumbs" aria-label="Διαδρομή"><ol>
          <?php foreach ($crumbs as $i => $c): ?>
            <li><?php if ($i < count($crumbs) - 1): ?><a href="<?= e($c['path']) ?>"><?= e($c['name']) ?></a><?php else: ?><span aria-current="page"><?= e($c['name']) ?></span><?php endif; ?></li>
          <?php endforeach; ?>
        </ol></nav>
      <?php endif; ?>
      <h1 id="h1" class="display"><?= $h1Html ?? e($page['h1'] ?: $page['title']) ?></h1>
      <?php if (trim((string) $page['intro']) !== ''): ?><p class="lead"><?= e($page['intro']) ?></p><?php endif; ?>
      <?= $actions ?? '' ?>
    </div>
  </div>
</section>
