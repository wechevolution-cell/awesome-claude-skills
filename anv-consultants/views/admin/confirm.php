<?php use function App\{e, csrf_field}; ?>
<h1>Διαγραφή σελίδας</h1>
<div class="card" style="max-width:640px">
  <p>Θα διαγραφεί οριστικά η σελίδα <b><?= e($p['title']) ?></b> (<code><?= e($p['path']) ?></code>).</p>
  <?php if ($kids): ?>
    <div class="flash err">Η σελίδα έχει <?= (int) $kids ?> υποσελίδες. Μετακινήστε ή διαγράψτε τις πρώτα.</div>
    <p><a href="/admin/pages">Πίσω στις σελίδες</a></p>
  <?php else: ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <fieldset><legend>Τι να γίνει με την παλιά διεύθυνση;</legend>
      <label class="check"><input type="radio" name="after" value="301" checked> 301 προς <?= e($parent ? $parent['title'] . ' (' . $parent['path'] . ')' : 'την αρχική') ?> (προτείνεται)</label>
      <label class="check"><input type="radio" name="after" value="410"> 410 (η σελίδα αφαιρέθηκε οριστικά)</label>
      <label class="check"><input type="radio" name="after" value="none"> Τίποτα (θα δίνει 404)</label>
    </fieldset>
    <label>Βήμα 2: πληκτρολογήστε <b>ΔΙΑΓΡΑΦΗ</b> για επιβεβαίωση<input name="confirm" required autocomplete="off" pattern="ΔΙΑΓΡΑΦΗ"></label>
    <div class="actions"><button class="btn danger" type="submit">Οριστική διαγραφή</button><a class="btn ghost" href="/admin/pages/edit?id=<?= (int) $p['id'] ?>">Ακύρωση</a></div>
  </form>
  <?php endif; ?>
</div>
