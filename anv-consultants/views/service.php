<?php
use function App\{e, view, published_children, icon, faq_items, howto_steps, sources_list};
/** @var array $page */
$kids = published_children((int) $page['id']);
$faq = faq_items($page);
$steps = howto_steps($page);
$src = sources_list($page);
?>
<?= view('partials/hero', ['page' => $page, 'variant' => 'page', 'actions' => view('partials/actions')]) ?>

<?php if ($kids): ?>
<section class="floaters" aria-label="<?= e($page['title']) ?>: υπηρεσίες">
  <div class="wrap grid-3">
    <?php foreach ($kids as $k): ?>
      <article class="glass reveal">
        <h2 style="font-size:1.3rem"><a href="<?= e($k['path']) ?>"><?= e($k['nav_label'] ?: $k['title']) ?></a></h2>
        <p><?= e($k['intro']) ?></p>
        <span class="link-arrow">Περισσότερα <?= icon('arrow', 16) ?></span>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="wrap stack">
    <?php if (trim($page['body_html']) !== ''): ?><div class="prose reveal"><?= $page['body_html'] ?></div><?php endif; ?>
    <?php if ($steps): ?>
      <div class="stack reveal" style="margin-top:var(--s-7)">
        <h2 class="h-sec"><?= e($page['howto_title'] ?: 'Πώς γίνεται') ?></h2>
        <?= view('partials/steps', ['steps' => $steps]) ?>
      </div>
    <?php endif; ?>
    <?php if ($faq): ?>
      <div class="stack reveal" style="margin-top:var(--s-7)">
        <h2 class="h-sec">Συχνές ερωτήσεις</h2>
        <?= view('partials/faq', ['items' => $faq]) ?>
      </div>
    <?php endif; ?>
    <?php if ($src): ?>
      <div class="sources" style="margin-top:var(--s-6)"><span class="eyebrow">Πηγές</span>
        <ul><?php foreach ($src as $s): ?><li><a href="<?= e($s['url']) ?>" rel="noopener" target="_blank"><?= e($s['label']) ?></a></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
  </div>
</section>

<?= view('partials/cta') ?>
