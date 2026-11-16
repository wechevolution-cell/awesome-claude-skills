<div class="panel" style="max-width:560px">
  <h2>Διαγραφή: <?= e($label) ?></h2>
  <p>Η διαγραφή είναι οριστική και δεν αναιρείται.</p>
  <form method="post" class="form"><?= csrf_field() ?>
    <label class="check"><input type="checkbox" name="confirm" value="yes" required> <span>Κατάλαβα, θέλω να διαγραφεί οριστικά.</span></label>
    <div class="row"><button class="btn" type="submit">Οριστική διαγραφή</button><a class="btn ghost" href="<?= e($d['base'] . '/' . $row['id']) ?>">Άκυρο</a></div>
  </form>
</div>
