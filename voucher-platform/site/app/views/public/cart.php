<div class="sec narrow">
  <h1 class="disp h1">Καλάθι</h1>
  <?php if (!$lines): ?>
    <div class="empty" style="margin-top:20px"><b>Το καλάθι είναι άδειο</b><a class="link" href="/prosfores">Δες τις προσφορές</a></div>
  <?php else: ?>
    <ul style="list-style:none;padding:0;margin:20px 0">
    <?php foreach ($lines as $l): ?>
      <li class="line">
        <div class="thumb"><?php if ($l['service']['image']): ?><img src="<?= e($l['service']['image']) ?>" alt="" width="56" height="56"><?php else: partial('art', ['id' => $l['service']['id'], 'label' => '']); endif ?></div>
        <div><b><a href="/prosfora/<?= e($l['service']['slug']) ?>" style="text-decoration:none"><?= e($l['title']) ?></a></b><span class="small muted"><?= e($l['service']['business_name']) ?></span></div>
        <div style="text-align:right">
          <form method="post" action="/kalathi/update" class="qty"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($l['key']) ?>">
            <button name="qty" value="<?= $l['qty'] - 1 ?>" aria-label="Λιγότερα">−</button><span><?= $l['qty'] ?></span><button name="qty" value="<?= $l['qty'] + 1 ?>" aria-label="Περισσότερα">+</button></form>
          <div class="small tnum" style="margin-top:4px"><?= $l['line_cents'] ? money($l['line_cents']) : 'Δωρεάν' ?></div>
        </div>
      </li>
    <?php endforeach ?>
    </ul>
    <div class="totals">
      <?php if ($t['savings'] > 0): ?><div class="save"><span>Εξοικονομείς</span><span><?= money($t['savings']) ?></span></div><?php endif ?>
      <div class="grand"><span>Σύνολο</span><span class="tnum"><?= money($t['total']) ?></span></div>
    </div>
    <div class="row" style="margin-top:16px"><a class="btn gold" href="/tameio">Ολοκλήρωση →</a><a class="btn ghost" href="/prosfores">Συνέχεια αγορών</a></div>
  <?php endif ?>
</div>
