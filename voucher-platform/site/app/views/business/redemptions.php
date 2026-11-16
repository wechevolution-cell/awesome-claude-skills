<form class="filters" method="get">
  <label class="small" for="fr">Από</label><input class="input" type="date" id="fr" name="from" value="<?= e($from) ?>" style="max-width:180px">
  <label class="small" for="to">Έως</label><input class="input" type="date" id="to" name="to" value="<?= e($to) ?>" style="max-width:180px">
  <button class="btn sm dark" type="submit">Εμφάνιση</button>
  <a class="btn sm ghost" href="/business/redemptions.csv?from=<?= e($from) ?>&amp;to=<?= e($to) ?>">Εξαγωγή CSV</a>
  <span class="small muted"><?= count($rows) ?> εξαργυρώσεις · <?= money(array_sum(array_column($rows, 'paid_cents'))) ?></span>
</form>
<?php if ($rows): ?>
<div class="table-wrap"><table class="t"><thead><tr><th>Ημερομηνία</th><th>Κωδικός</th><th>Υπηρεσία</th><th>Πελάτης</th><th>Κατάστημα</th><th>Υπάλληλος</th><th>Πληρωμή</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e(date_gr($r['redeemed_at'], true)) ?></td><td class="mono"><?= e($r['code']) ?></td><td><?= e($r['service_title']) ?><?= $r['variant_label'] ? ' · ' . e($r['variant_label']) : '' ?></td><td><?= e($r['customer']) ?></td><td><?= e($r['store']) ?></td><td><?= e($r['staff']) ?></td><td class="tnum"><?= money((int)$r['paid_cents']) ?></td></tr><?php endforeach ?>
</tbody></table></div>
<?php else: ?><div class="empty"><b>Καμία εξαργύρωση στο διάστημα</b></div><?php endif ?>
