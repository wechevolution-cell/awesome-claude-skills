<?php if ($b['status'] !== 'active'): ?><div class="alert info" style="margin-bottom:16px">Η επιχείρηση περιμένει έγκριση. Στο μεταξύ συμπλήρωσε <a class="link" href="/business/c/profile">στοιχεία</a>, <a class="link" href="/business/c/stores">καταστήματα</a> και <a class="link" href="/business/c/services">προσφορές</a>.</div><?php endif ?>
<div class="kpis">
  <div><b class="tnum"><?= $kpi['redeemed'] ?></b><span>Εξαργυρώσεις μήνα</span></div>
  <div><b class="tnum"><?= $kpi['issued'] ?></b><span>Νέοι κωδικοί μήνα</span></div>
  <div><b class="tnum"><?= $kpi['active'] ?></b><span>Ενεργοί κωδικοί πελατών</span></div>
  <div><b class="tnum"><?= money_short($kpi['revenue']) ?></b><span>Αξία εξαργυρώσεων μήνα</span></div>
</div>
<div class="grid g2" style="margin-top:16px">
  <div class="panel"><h2>Εξαργυρώσεις, 14 ημέρες</h2>
    <?php $max = max(1, max($days)); ?>
    <div class="bars" role="img" aria-label="Εξαργυρώσεις ανά ημέρα"><?php foreach ($days as $d => $n): ?><span style="height:<?= max(2, (int)round($n / $max * 100)) ?>%" data-v="<?= $n ?: '' ?>" title="<?= e(date('d/m', strtotime($d))) ?>: <?= $n ?>"></span><?php endforeach ?></div>
    <div class="bars-x"><?php foreach (array_keys($days) as $i => $d): ?><span><?= $i % 2 === 0 ? date('d/m', strtotime($d)) : '' ?></span><?php endforeach ?></div>
  </div>
  <div class="panel"><h2>Γρήγορες ενέργειες</h2>
    <div class="stack">
      <a class="btn gold block" href="/business/redeem">Εξαργύρωση κωδικού</a>
      <a class="btn ghost block" href="/business/c/services/new">+ Νέα προσφορά</a>
      <p class="small muted"><?= $kpi['services'] ?> ενεργές προσφορές<?= $kpi['pending'] ? ' · ' . $kpi['pending'] . ' σε έγκριση' : '' ?></p>
    </div>
  </div>
</div>
<div class="panel" style="margin-top:16px"><h2>Τελευταίες εξαργυρώσεις</h2>
<?php if ($recent): ?><div class="table-wrap" style="border:0"><table class="t"><thead><tr><th>Ώρα</th><th>Κωδικός</th><th>Υπηρεσία</th><th>Πελάτης</th></tr></thead><tbody>
<?php foreach ($recent as $r): ?><tr><td><?= e(date_gr($r['redeemed_at'], true)) ?></td><td class="mono"><?= e($r['code']) ?></td><td><?= e($r['service_title']) ?></td><td><?= e($r['customer']) ?></td></tr><?php endforeach ?>
</tbody></table></div><?php else: ?><p class="muted">Δεν υπάρχουν εξαργυρώσεις ακόμα.</p><?php endif ?>
</div>
