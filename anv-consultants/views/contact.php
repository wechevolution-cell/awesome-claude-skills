<?php
use function App\{e, view, socials};
/** @var array $page */
?>
<?= view('partials/hero', ['page' => $page, 'variant' => 'page', 'actions' => view('partials/actions')]) ?>
<section class="section visit">
  <div class="wrap stack">
    <?= view('partials/contact-panel') ?>
    <?php if (trim($page['body_html']) !== ''): ?><div class="prose" style="margin-top:var(--s-6)"><?= $page['body_html'] ?></div><?php endif; ?>
    <?php if ($soc = socials()): ?>
      <div style="margin-top:var(--s-6)"><span class="eyebrow">Βρείτε μας και στα social</span>
        <ul class="chips" style="margin-top:8px"><?php foreach ($soc as $l => $u): ?><li><a href="<?= e($u) ?>" rel="noopener me" target="_blank" style="text-decoration:none"><?= e($l) ?></a></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
  </div>
</section>
