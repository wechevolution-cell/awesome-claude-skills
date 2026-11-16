<div class="sec">
  <h1 class="disp h2">Παραγγελίες</h1>
  <?= view('account/_nav', ['tab' => $tab]) ?>
  <?php if ($orders): ?>
  <div class="table-wrap"><table class="t"><thead><tr><th>Αριθμός</th><th>Ημερομηνία</th><th>Είδη</th><th>Σύνολο</th><th>Κατάσταση</th></tr></thead><tbody>
  <?php foreach ($orders as $o): ?><tr><td><a class="link mono" href="/paraggelia/<?= e($o['number']) ?>"><?= e($o['number']) ?></a></td><td><?= e(date_gr($o['created_at'], true)) ?></td><td><?= (int)$o['n'] ?></td><td class="tnum"><?= money((int)$o['total_cents']) ?></td><td><span class="badge badge-<?= e($o['status']) ?>"><?= e(['paid' => 'Πληρώθηκε', 'free' => 'Δωρεάν', 'pending' => 'Εκκρεμεί', 'failed' => 'Απέτυχε', 'cancelled' => 'Ακυρώθηκε'][$o['status']] ?? $o['status']) ?></span></td></tr><?php endforeach ?>
  </tbody></table></div>
  <?php else: ?><div class="empty"><b>Καμία παραγγελία ακόμα</b></div><?php endif ?>
</div>
