<?php
use function App\{e, view, services, icon, tags_of, howto_steps};
/** @var array $page */
$h1 = e($page['h1'] ?: $page['title']);
// Emphasise the three service names inside the H1 (brand typographic treatment).
foreach (services() as $s) {
    $h1 = preg_replace('/(' . preg_quote(e(mb_strtolower($s['title'])), '/') . ')/iu', '<b>$1</b>', $h1, 1) ?? $h1;
}
$steps = howto_steps($page);
?>
<?= view('partials/hero', ['page' => $page, 'variant' => 'home', 'h1Html' => $h1, 'actions' => view('partials/actions')]) ?>

<section class="floaters" aria-label="Υπηρεσίες">
  <div class="wrap grid-3">
    <?php foreach (services() as $s): ?>
      <article class="glass reveal">
        <span class="ic"><?= icon($s['icon'] ?: 'ledger', 22) ?></span>
        <h2><a href="<?= e($s['path']) ?>"><?= e($s['title']) ?></a></h2>
        <p><?= e($s['intro']) ?></p>
        <?php if ($tags = tags_of($s)): ?><ul class="chips"><?php foreach ($tags as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <span class="link-arrow">Περισσότερα <?= icon('arrow', 16) ?></span>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?php if (trim($page['body_html']) !== ''): ?>
<section class="section">
  <div class="wrap">
    <div class="prose lead reveal"><?= $page['body_html'] ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($steps): ?>
<section class="section" style="padding-top:0">
  <div class="wrap stack reveal">
    <h2 class="h-sec"><?= e($page['howto_title'] ?: 'Πώς δουλεύουμε') ?></h2>
    <?= view('partials/steps', ['steps' => $steps]) ?>
  </div>
</section>
<?php endif; ?>

<section class="section visit" aria-labelledby="contact-h">
  <div class="wrap stack">
    <h2 class="h-sec" id="contact-h">Επικοινωνία</h2>
    <p class="muted">Καλέστε μας ή στείλτε μας email. Μπορείτε επίσης να έρθετε στο γραφείο.</p>
    <div style="margin-top:var(--s-5)"><?= view('partials/contact-panel') ?></div>
  </div>
</section>
