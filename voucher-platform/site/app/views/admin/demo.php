<div class="panel" style="max-width:760px">
<?php if (!$loaded): ?>
  <h2>Φόρτωση δεδομένων επίδειξης</h2>
  <p>Δημιουργεί 5 επιχειρήσεις, 9 προσφορές, 4 καμπάνιες και λογαριασμούς για κάθε ρόλο (καταναλωτής, ιδιοκτήτης, ταμίας, αντιπρόσωπος). Όλα έχουν την ένδειξη «(δείγμα)» και διαγράφονται με ένα κουμπί.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="load"><button class="btn gold" type="submit">Φόρτωση demo</button></form>
<?php else: $creds = json_decode((string)setting('demo_credentials', '[]'), true) ?: []; ?>
  <h2>Λογαριασμοί επίδειξης</h2>
  <p class="small muted">Οι κωδικοί φαίνονται μόνο εδώ, στον διαχειριστή.</p>
  <div class="table-wrap"><table class="t"><thead><tr><th>Ρόλος</th><th>Email</th><th>Κωδικός πρόσβασης</th></tr></thead><tbody>
  <?php foreach ($creds as $c): ?><tr><td><?= e($c['role']) ?></td><td class="mono"><?= e($c['email']) ?></td><td class="mono"><?= e($c['password']) ?></td></tr><?php endforeach ?>
  </tbody></table></div>
  <form method="post" class="form" style="margin-top:20px"><?= csrf_field() ?><input type="hidden" name="action" value="clear">
    <label class="check"><input type="checkbox" name="confirm" value="yes" required> <span>Κατάλαβα. Διαγράφονται οριστικά όλα τα δεδομένα επίδειξης, μαζί με κωδικούς και παραγγελίες τους.</span></label>
    <button class="btn" type="submit" style="justify-self:start">Διαγραφή demo</button></form>
<?php endif ?>
</div>
