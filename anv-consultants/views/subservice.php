<?php
use function App\{e, view, page_by_id, published_children, icon, faq_items, howto_steps, sources_list, toc, lines, setting, tel_href};
/** @var array $page */
$parent = $page['parent_id'] ? page_by_id((int) $page['parent_id']) : null;
$siblings = $parent ? published_children((int) $parent['id']) : [];
$faq = faq_items($page);
$steps = howto_steps($page);
$src = sources_list($page);
$toc = toc($page['body_html']);
$points = lines($page['key_points']);
$phone = (string) setting('phone', '');
$email = (string) setting('email', '');
$tldr = trim((string) $page['tldr']);
?>
<?= view('partials/hero', ['page' => $page, 'variant' => 'page']) ?>

<section class="section">
  <div class="wrap article">
    <article class="stack">
      <?php if ($tldr !== '' && $tldr !== trim((string) $page['intro']) || $points): ?>
        <div class="tldr stack">
          <?php if ($tldr !== '' && $tldr !== trim((string) $page['intro'])): ?><div><span class="eyebrow">Με μια ματιά</span><p><?= e($tldr) ?></p></div><?php endif; ?>
          <?php if ($points): ?>
            <div><span class="eyebrow">Βασικά σημεία</span>
              <ul class="keypoints" style="margin-top:8px"><?php foreach ($points as $pt): ?><li><?= icon('check', 18) ?><span><?= e($pt) ?></span></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (count($toc) > 2): ?>
        <nav class="toc" aria-label="Περιεχόμενα"><span class="eyebrow">Περιεχόμενα</span>
          <ol><?php foreach ($toc as $t): ?><li><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?></ol>
        </nav>
      <?php endif; ?>

      <div class="prose"><?= $page['body_html'] ?></div>

      <?php if ($steps): ?>
        <div class="stack" style="margin-top:var(--s-6)">
          <h2 class="h-sec" style="font-size:var(--fs-4)"><?= e($page['howto_title'] ?: 'Πώς γίνεται') ?></h2>
          <?= view('partials/steps', ['steps' => $steps]) ?>
        </div>
      <?php endif; ?>

      <?php if ($faq): ?>
        <div class="stack" style="margin-top:var(--s-6)">
          <h2 class="h-sec" style="font-size:var(--fs-4)">Συχνές ερωτήσεις</h2>
          <?= view('partials/faq', ['items' => $faq]) ?>
        </div>
      <?php endif; ?>

      <?php if ($src): ?>
        <div class="sources" style="margin-top:var(--s-5)"><span class="eyebrow">Πηγές</span>
          <ul><?php foreach ($src as $s): ?><li><a href="<?= e($s['url']) ?>" rel="noopener" target="_blank"><?= e($s['label']) ?></a></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <p class="meta-line">
        <?php if (trim((string) $page['author']) !== ''): ?><span>Συντάκτης: <?= e($page['author']) ?></span><?php endif; ?>
        <span>Τελευταία ενημέρωση: <time datetime="<?= e(date('Y-m-d', strtotime($page['updated_at']))) ?>"><?= e(date('d/m/Y', strtotime($page['updated_at']))) ?></time></span>
      </p>
    </article>

    <aside class="aside" aria-label="Επικοινωνία και σχετικές υπηρεσίες">
      <div class="call-card">
        <h2>Ρωτήστε μας</h2>
        <p>Για τη δική σας περίπτωση, καλέστε μας ή στείλτε μας email.</p>
        <?php if ($phone !== ''): ?><a class="num" href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a><?php endif; ?>
        <div class="btns">
          <?php if ($phone !== ''): ?><a class="btn btn-primary" href="<?= e(tel_href($phone)) ?>"><?= icon('phone', 18) ?>Κλήση</a><?php endif; ?>
          <?php if ($email !== ''): ?><a class="btn btn-ghost" style="color:#fff;border-color:rgba(255,255,255,.4)" href="mailto:<?= e($email) ?>"><?= icon('mail', 18) ?>Email</a><?php endif; ?>
          <?php if ($phone === '' && $email === ''): ?><a class="btn btn-primary" href="/epikoinonia/">Επικοινωνία</a><?php endif; ?>
        </div>
      </div>
      <?php if ($parent && count($siblings) > 1): ?>
        <nav class="siblings" aria-label="<?= e($parent['title']) ?>">
          <span class="eyebrow"><a href="<?= e($parent['path']) ?>" style="color:inherit;text-decoration:none"><?= e($parent['title']) ?></a></span>
          <ul><?php foreach ($siblings as $s): ?><li><a href="<?= e($s['path']) ?>"<?= $s['id'] === $page['id'] ? ' aria-current="page"' : '' ?>><?= e($s['nav_label'] ?: $s['title']) ?></a></li><?php endforeach; ?></ul>
        </nav>
      <?php endif; ?>
    </aside>
  </div>
</section>
