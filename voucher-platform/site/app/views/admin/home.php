<div class="kpis"><?php foreach ($kpi as [$l, $v, $h]): ?><a href="<?= e($h) ?>"><b class="tnum"><?= e($v) ?></b><span><?= e($l) ?></span></a><?php endforeach ?></div>
<div class="grid g2" style="margin-top:16px">
  <div class="panel"><h2>Πριν το live</h2><ul class="checks">
    <?php foreach ($checks as [$l, $ok, $h]): ?><li><span class="<?= $ok ? 'y' : 'n' ?>"><?= $ok ? '✓' : '✕' ?></span><?= $h && !$ok ? '<a class="link" href="' . e($h) . '">' . e($l) . '</a>' : e($l) ?></li><?php endforeach ?>
  </ul></div>
  <div class="panel"><h2>Αρχεία για μηχανές αναζήτησης και app</h2><ul class="checks">
    <?php foreach ($files as [$l, $h]): ?><li><span class="y">✓</span><a class="link" href="<?= e($h) ?>" target="_blank"><?= e($l) ?></a></li><?php endforeach ?>
  </ul>
  <p class="small muted" style="margin-top:12px">Εξαγωγές: <a class="link" href="/admin/export/orders.csv">παραγγελίες</a> · <a class="link" href="/admin/export/codes.csv">κωδικοί</a> · <a class="link" href="/admin/export/payouts.csv">αποδόσεις σε επιχειρήσεις</a></p>
  <p class="small muted">Αντίγραφο ασφαλείας: δες το README (ενότητα «Backup»).</p></div>
</div>
<div class="panel" style="margin-top:16px"><h2>Πρόσφατη δραστηριότητα</h2>
<?php if ($recent): ?><div class="table-wrap" style="border:0"><table class="t"><tbody><?php foreach ($recent as $r): ?><tr><td class="small"><?= e(date_gr($r['created_at'], true)) ?></td><td><?= e($r['name'] ?? 'Σύστημα') ?></td><td class="mono small"><?= e($r['action']) ?></td><td class="small muted"><?= e($r['entity']) ?> <?= $r['entity_id'] ? '#' . (int)$r['entity_id'] : '' ?></td></tr><?php endforeach ?></tbody></table></div><?php else: ?><p class="muted">Τίποτα ακόμα.</p><?php endif ?>
</div>
