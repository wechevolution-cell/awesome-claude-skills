<?php
use function App\{e, view, faq_items, sources_list};
/** @var array $page */
$faq = faq_items($page);
$src = sources_list($page);
$legal = $page['type'] === 'legal';
?>
<?= view('partials/hero', ['page' => $page, 'variant' => 'page']) ?>
<section class="section">
  <div class="wrap stack">
    <div class="prose"><?= $page['body_html'] ?></div>
    <?php if ($faq): ?>
      <div class="stack" style="margin-top:var(--s-6)"><h2 class="h-sec" style="font-size:var(--fs-4)">Συχνές ερωτήσεις</h2><?= view('partials/faq', ['items' => $faq]) ?></div>
    <?php endif; ?>
    <?php if ($src): ?>
      <div class="sources"><span class="eyebrow">Πηγές</span><ul><?php foreach ($src as $s): ?><li><a href="<?= e($s['url']) ?>" rel="noopener" target="_blank"><?= e($s['label']) ?></a></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($legal): ?><p class="meta-line"><span>Τελευταία ενημέρωση: <?= e(date('d/m/Y', strtotime($page['updated_at']))) ?></span></p><?php endif; ?>
  </div>
</section>
<?php if (!$legal): ?><?= view('partials/cta') ?><?php endif; ?>
