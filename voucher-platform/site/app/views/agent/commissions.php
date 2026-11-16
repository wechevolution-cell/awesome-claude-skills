<?php if ($rows): ?>
<div class="table-wrap"><table class="t"><thead><tr><th>Ημερομηνία</th><th>Επιχείρηση</th><th>Κωδικός</th><th>Βάση</th><th>Ποσό</th><th>Κατάσταση</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e(date_gr($r['created_at'], true)) ?></td><td><?= e($r['business']) ?></td><td class="mono"><?= e($r['code']) ?></td><td class="small"><?= e($r['basis']) ?></td><td class="tnum"><?= money((int)$r['amount_cents']) ?></td><td><span class="badge badge-<?= e($r['status']) ?>"><?= e(['pending' => 'Εκκρεμεί', 'approved' => 'Εγκρίθηκε', 'paid' => 'Πληρώθηκε'][$r['status']] ?? $r['status']) ?></span></td></tr><?php endforeach ?>
</tbody></table></div>
<?php else: ?><div class="empty"><b>Καμία προμήθεια ακόμα</b>Οι προμήθειες γράφονται αυτόματα σε κάθε εξαργύρωση στις επιχειρήσεις σου.</div><?php endif ?>
