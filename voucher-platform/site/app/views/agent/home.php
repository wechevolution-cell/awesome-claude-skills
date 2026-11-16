<div class="kpis">
  <div><b class="tnum"><?= $kpi['businesses'] ?></b><span>Επιχειρήσεις</span></div>
  <div><b class="tnum"><?= $kpi['active'] ?></b><span>Ενεργές</span></div>
  <div><b class="tnum"><?= money_short($kpi['pending_cents']) ?></b><span>Προμήθειες προς πληρωμή</span></div>
  <div><b class="tnum"><?= money_short($kpi['paid_cents']) ?></b><span>Πληρωμένες προμήθειες</span></div>
</div>
<div class="grid g2" style="margin-top:16px">
  <div class="panel"><h2>Σύνδεσμος σύστασης</h2>
    <p class="small muted">Οι επιχειρήσεις που κάνουν αίτηση από αυτόν τον σύνδεσμο συνδέονται αυτόματα μαζί σου.</p>
    <div class="row"><input class="input mono" readonly value="<?= e(abs_url('/synergates') . '?ref=' . $a['affiliate_code']) ?>" id="aff" aria-label="Σύνδεσμος σύστασης"><button class="btn sm" type="button" data-copy="#aff">Αντιγραφή</button></div>
    <p class="small" style="margin-top:10px">Προμήθεια: <?= (int)$a['commission_percent'] ?>% + <?= money((int)$a['commission_fixed_cents']) ?> ανά εξαργύρωση<?= $a['region'] ? ' · Περιοχή: ' . e($a['region']) : '' ?></p>
  </div>
  <div class="panel"><h2>Υπο-αντιπρόσωποι</h2>
    <?php if ($subs): ?><ul><?php foreach ($subs as $s): ?><li><?= e($s['name']) ?> · <?= e($s['region'] ?: '—') ?></li><?php endforeach ?></ul><?php else: ?><p class="muted small">Κανένας. Τους ορίζει ο διαχειριστής.</p><?php endif ?>
  </div>
</div>
<div class="panel" style="margin-top:16px"><div class="row between"><h2>Επιχειρήσεις</h2><a class="btn sm gold" href="/agent/new-business">+ Νέα επιχείρηση</a></div>
<?php if ($businesses): ?><div class="table-wrap" style="border:0"><table class="t"><thead><tr><th>Επωνυμία</th><th>Κατάσταση</th><th>Προσφορές</th><th>Εξαργυρώσεις</th></tr></thead><tbody>
<?php foreach ($businesses as $b): ?><tr><td><a class="link" href="/agent/c/businesses/<?= (int)$b['id'] ?>"><?= e($b['name']) ?></a></td><td><span class="badge badge-<?= e($b['status']) ?>"><?= e(STATUS_OPTIONS[$b['status']] ?? $b['status']) ?></span></td><td><?= (int)$b['services'] ?></td><td><?= (int)$b['redeemed'] ?></td></tr><?php endforeach ?>
</tbody></table></div><?php else: ?><p class="muted">Δεν έχεις επιχειρήσεις ακόμα.</p><?php endif ?>
</div>
