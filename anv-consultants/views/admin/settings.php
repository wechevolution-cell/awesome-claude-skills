<?php use function App\{e, csrf_field, setting, hours}; use const App\DAY_NAMES;
$h = hours();
?>
<h1>Ρυθμίσεις</h1>
<form method="post" class="form settings">
  <?= csrf_field() ?>
  <?php foreach ($fields as $group => $items): ?>
    <section class="card">
      <h2><?= e($group) ?></h2>
      <div class="grid2">
      <?php foreach ($items as $key => [$label, $type]): $v = (string) setting($key, ''); ?>
        <?php if ($type === 'bool'): ?>
          <label class="check"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= $v === '1' ? ' checked' : '' ?>> <?= e($label) ?></label>
        <?php elseif ($type === 'textarea'): ?>
          <label class="span2"><?= e($label) ?><textarea name="<?= e($key) ?>" rows="3"><?= e($v) ?></textarea></label>
        <?php elseif ($type === 'media'): ?>
          <label><?= e($label) ?><input name="<?= e($key) ?>" value="<?= e($v) ?>" list="media-list"></label>
        <?php else: ?>
          <label><?= e($label) ?><input type="<?= $type === 'url' ? 'url' : ($type === 'email' ? 'email' : ($type === 'tel' ? 'tel' : 'text')) ?>" name="<?= e($key) ?>" value="<?= e($v) ?>"></label>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($group === 'SEO & analytics'): ?>
        <label class="check span2"><input type="checkbox" name="canonical_confirm" value="1"> Επιβεβαιώνω ότι το κανονικό URL είναι σωστό και ότι το SSL λειτουργεί (θα γίνεται 301 από κάθε άλλη διεύθυνση).</label>
      <?php endif; ?>
      </div>
    </section>
    <?php if ($group === 'Διεύθυνση & χάρτης'): ?>
      <section class="card">
        <h2>Ωράριο</h2>
        <p class="muted small">Μέχρι δύο διαστήματα ανά ημέρα (π.χ. πρωί και απόγευμα). Αφήστε κενό για «Κλειστά».</p>
        <table class="tbl hours-ed"><thead><tr><th>Ημέρα</th><th>Άνοιγμα</th><th>Κλείσιμο</th><th>Άνοιγμα (2)</th><th>Κλείσιμο (2)</th></tr></thead><tbody>
          <?php for ($d = 1; $d <= 7; $d++): ?>
            <tr><td><?= e(DAY_NAMES[$d]) ?></td>
              <?php for ($s = 0; $s < 2; $s++): ?>
                <td><input type="time" name="h_<?= $d ?>_<?= $s ?>_o" value="<?= e($h[$d][$s][0] ?? '') ?>" aria-label="<?= e(DAY_NAMES[$d]) ?> άνοιγμα <?= $s + 1 ?>"></td>
                <td><input type="time" name="h_<?= $d ?>_<?= $s ?>_c" value="<?= e($h[$d][$s][1] ?? '') ?>" aria-label="<?= e(DAY_NAMES[$d]) ?> κλείσιμο <?= $s + 1 ?>"></td>
              <?php endfor; ?>
            </tr>
          <?php endfor; ?>
        </tbody></table>
        <label>Σημείωση ωραρίου (π.χ. αργίες)<input name="hours_note" value="<?= e((string) setting('hours_note', '')) ?>"></label>
        <label class="check"><input type="checkbox" name="hours_confirmed" value="1"<?= setting('hours_confirmed') === '1' ? ' checked' : '' ?>> Το ωράριο είναι σωστό (ενεργοποιεί το ωράριο στο schema και στο llms.txt)</label>
      </section>
    <?php endif; ?>
  <?php endforeach; ?>
  <datalist id="media-list"><?php foreach ($media as $m): ?><option value="<?= e($m) ?>"></option><?php endforeach; ?><option value="/assets/img/logo.png"></option><option value="/assets/img/og-default.png"></option></datalist>
  <div class="savebar"><button class="btn" type="submit">Αποθήκευση ρυθμίσεων</button></div>
</form>
