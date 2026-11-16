<?php
$isFree = (int)$s['price_cents'] === 0 && !array_filter($variants, fn($v) => (int)$v['price_cents'] > 0);
$first = $variants[0] ?? null;
$price = (int)($first['price_cents'] ?? $s['price_cents']);
$regular = (int)($first['regular_cents'] ?? $s['regular_cents']);
$pct = discount_pct($regular, $price);
$fav = in_array((int)$s['id'], $favs, true);
?>
<div style="padding-top:12px"><?php partial('crumbs', ['crumbs' => $crumbs]) ?></div>
<div class="pdp">
  <div>
    <div class="media">
      <?php if ($isFree): ?><div class="stick free">Δωρεάν</div><?php elseif ($pct > 0): ?><div class="stick" data-pct>−<?= $pct ?>%</div><?php endif ?>
      <?php if ($s['image']): ?><img src="<?= e($s['image']) ?>" alt="<?= e($s['title']) ?>" width="1200" height="1200" fetchpriority="high"><?php else: partial('art', ['id' => $s['id'], 'label' => $s['title']]); endif ?>
    </div>

    <section class="sec prose" aria-labelledby="about">
      <h2 id="about">Τι περιλαμβάνει</h2>
      <?php if ($s['summary']): ?><p><strong><?= e($s['summary']) ?></strong></p><?php endif ?>
      <?= nl2p($s['description']) ?>
      <?php if ($s['terms']): ?><h2>Όροι εξαργύρωσης</h2><ul><?php foreach (array_filter(array_map('trim', explode("\n", $s['terms']))) as $t): ?><li><?= e($t) ?></li><?php endforeach ?></ul><?php endif ?>
      <?php if ($stores): ?>
        <h2>Πού εξαργυρώνεται</h2>
        <ul><?php foreach ($stores as $st): ?><li><strong><?= e($st['name']) ?></strong><?= $st['address'] ? ' · ' . e($st['address']) : '' ?><?= $st['city'] ? ', ' . e($st['city']) : '' ?><?= $st['phone'] ? ' · ' . e($st['phone']) : '' ?></li><?php endforeach ?></ul>
      <?php endif ?>
      <h2>Κριτικές</h2>
      <?php if ($reviews): foreach ($reviews as $r): ?>
        <p><strong><?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5 - (int)$r['rating']) ?></strong> · <?= e($r['name']) ?><br><?= e($r['body']) ?></p>
      <?php endforeach; else: ?>
        <p class="muted">Δεν υπάρχουν κριτικές ακόμα. Κριτική γράφουν μόνο όσοι εξαργύρωσαν κωδικό.</p>
      <?php endif ?>
      <h2>Συχνές ερωτήσεις</h2>
      <?php foreach ($faq as [$q, $a]): ?><details class="faq"><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach ?>
    </section>
  </div>

  <aside class="buybox" aria-label="Αγορά">
    <a class="eyebrow" href="/katastima/<?= e($business['slug']) ?>" style="text-decoration:none"><?= e($business['name']) ?></a>
    <h1 class="disp"><?= e($s['title']) ?></h1>
    <form class="stack" method="post" action="/kalathi/add" data-add-form>
      <?= csrf_field() ?>
      <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
      <div class="price lg" data-price-box>
        <?php if ($regular > $price): ?><s data-regular><?= money_short($regular) ?></s><?php endif ?>
        <b data-price><?= $price ? money_short($price) : '0 €' ?></b>
      </div>
      <?php if ($variants): ?>
        <fieldset class="variants">
          <legend class="lbl">Επίλεξε</legend>
          <?php foreach ($variants as $i => $v): ?>
            <label><input type="radio" name="variant_id" value="<?= (int)$v['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> data-price-cents="<?= (int)$v['price_cents'] ?>" data-regular-cents="<?= (int)$v['regular_cents'] ?>"><span><?= e($v['label']) ?><small><?= $v['price_cents'] ? money_short((int)$v['price_cents']) : 'Δωρεάν' ?></small></span></label>
          <?php endforeach ?>
        </fieldset>
      <?php endif ?>
      <?php if ($left !== null): ?><div class="left" style="--w:<?= max(4, (int)round($left / max(1, (int)$s['max_codes']) * 100)) ?>%;padding:0">Απομένουν <?= (int)$left ?> κωδικοί<i></i></div><?php endif ?>
      <div class="row" style="flex-wrap:nowrap">
        <?php if ($left === 0): ?>
          <button class="btn block" type="button" disabled>Εξαντλήθηκε</button>
        <?php else: ?>
          <button class="btn gold block" type="submit" data-buy><?= $isFree ? 'Πάρε δωρεάν κωδικό' : 'Πρόσθεσε στο καλάθι' ?> →</button>
        <?php endif ?>
        <button class="heart" type="button" data-fav="<?= (int)$s['id'] ?>" aria-pressed="<?= $fav ? 'true' : 'false' ?>" aria-label="Αγαπημένα"><?= $fav ? '♥' : '♡' ?></button>
      </div>
    </form>
    <ul class="facts">
      <li><span>Ισχύς κωδικού</span><span><?= (int)$s['code_validity_days'] ?> ημέρες από την έκδοση</span></li>
      <li><span>Κωδικοί ανά πελάτη</span><span>έως <?= (int)$s['per_user_limit'] ?></span></li>
      <li><span>Εξαργύρωση</span><span>QR ή κωδικός στο ταμείο</span></li>
      <li><span>Παράδοση</span><span>Αμέσως στον λογαριασμό σου</span></li>
      <?php if ($cat): ?><li><span>Κατηγορία</span><span><a class="link" href="/katigoria/<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a></span></li><?php endif ?>
    </ul>
  </aside>
</div>

<?php if ($related): ?>
<section class="sec" aria-labelledby="rel">
  <div class="sec-h"><h2 id="rel" class="disp h2">Μπορεί να σου <span class="foil">αρέσουν</span></h2></div>
  <div class="cards"><?php foreach ($related as $r) partial('card', ['s' => $r, 'favs' => $favs]) ?></div>
</section>
<?php endif ?>

<div class="sticky-buy" data-sticky-buy>
  <div><div class="small muted" style="line-height:1.2"><?= e(mb_strimwidth($s['title'], 0, 34, '…')) ?></div><b class="tnum" data-price-mini><?= $price ? money_short($price) : '0 €' ?></b></div>
  <button class="btn gold" type="button" data-sticky-submit><?= $isFree ? 'Πάρε κωδικό' : 'Στο καλάθι' ?></button>
</div>
