<?php
use function App\{e, view, faq_items};
/** @var array $page @var array $groups */
$own = faq_items($page);
?>
<?= view('partials/hero', ['page' => $page, 'variant' => 'page']) ?>
<section class="section">
  <div class="wrap stack">
    <?php if (count($groups) > 1): ?>
      <nav class="chips" aria-label="Κατηγορίες ερωτήσεων" style="margin-bottom:var(--s-5)">
        <?php foreach ($groups as $i => $g): ?><a class="chip" href="#faq-<?= $i ?>" style="text-decoration:none"><?= e($g['title']) ?></a><?php endforeach; ?>
        <?php if ($own): ?><a class="chip" href="#faq-general" style="text-decoration:none">Γενικά</a><?php endif; ?>
      </nav>
    <?php endif; ?>
    <?php foreach ($groups as $i => $g): ?>
      <div class="stack" id="faq-<?= $i ?>" style="margin-bottom:var(--s-7)">
        <h2 class="h-sec" style="font-size:var(--fs-4)"><a href="<?= e($g['path']) ?>" style="color:inherit;text-decoration:none"><?= e($g['title']) ?></a></h2>
        <?= view('partials/faq', ['items' => $g['items']]) ?>
      </div>
    <?php endforeach; ?>
    <?php if ($own): ?>
      <div class="stack" id="faq-general">
        <h2 class="h-sec" style="font-size:var(--fs-4)">Γενικά</h2>
        <?= view('partials/faq', ['items' => $own]) ?>
      </div>
    <?php endif; ?>
    <?php if (trim($page['body_html']) !== ''): ?><div class="prose"><?= $page['body_html'] ?></div><?php endif; ?>
  </div>
</section>
<?= view('partials/cta') ?>
