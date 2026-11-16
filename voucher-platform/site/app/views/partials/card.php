<?php
/** Offer card. Needs: $s (service_cards row), $favs (int[]). */
$isFree = (int)$s['price_cents'] === 0;
$fav = in_array((int)$s['id'], $favs ?? [], true);
$leftPct = ($s['left'] !== null && $s['max_codes']) ? max(4, (int)round($s['left'] / max(1, (int)$s['max_codes']) * 100)) : null;
?>
<article class="card">
  <?php if ($isFree): ?><div class="stick free">Δωρεάν</div><?php elseif ($s['pct'] > 0): ?><div class="stick">−<?= (int)$s['pct'] ?>%</div><?php endif ?>
  <div class="media"><?php if ($s['image']): ?><img src="<?= e($s['image']) ?>" alt="<?= e($s['title']) ?>" loading="lazy" decoding="async" width="600" height="600"><?php else: partial('art', ['id' => $s['id'], 'label' => $s['title']]); endif ?></div>
  <div class="meta"><?= e($s['business_name']) ?><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></div>
  <h3><a href="/prosfora/<?= e($s['slug']) ?>"><?= e($s['title']) ?></a></h3>
  <div class="price"><?php if ($isFree): ?><b>0 €</b><?php if ($s['regular_cents'] > 0): ?><s><?= money_short((int)$s['regular_cents']) ?></s><?php endif ?><?php else: ?><?php if ($s['regular_cents'] > $s['price_cents']): ?><s><?= money_short((int)$s['regular_cents']) ?></s><?php endif ?><b><?= ($s['n_variants'] ?? 0) > 1 ? '<small style="font-size:.5em">από </small>' : '' ?><?= money_short((int)$s['price_cents']) ?></b><?php endif ?></div>
  <?php if ($leftPct !== null): ?><div class="left" style="--w:<?= $leftPct ?>%">Απομένουν <?= (int)$s['left'] ?> κωδικοί<i></i></div><?php endif ?>
  <div class="foot">
    <button class="heart" type="button" data-fav="<?= (int)$s['id'] ?>" aria-pressed="<?= $fav ? 'true' : 'false' ?>" aria-label="Αγαπημένα: <?= e($s['title']) ?>"><?= $fav ? '♥' : '♡' ?></button>
    <button class="add" type="button" data-add="<?= (int)$s['id'] ?>" aria-label="Προσθήκη στο καλάθι: <?= e($s['title']) ?>">+ Καλάθι</button>
  </div>
</article>
