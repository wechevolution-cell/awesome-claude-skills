<?php use function App\{e, csrf_field}; use const App\REDIRECT_CODES;
$f = $edit ? ['source' => $edit['source'], 'target' => $edit['target'], 'code' => (int) $edit['code'], 'type' => $edit['match_type'], 'id' => (int) $edit['id'], 'note' => $edit['note'], 'active' => (int) $edit['active']] : ($form ?? ['source' => '', 'target' => '', 'code' => 301, 'type' => 'exact', 'id' => 0]);
?>
<h1>Ανακατευθύνσεις</h1>
<div class="cols">
  <form method="post" class="form card" id="rform">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
    <h2><?= $f['id'] ? 'Επεξεργασία #' . (int) $f['id'] : 'Νέα ανακατεύθυνση' ?></h2>
    <div class="row2">
      <label>Τύπος ταιριάσματος<select name="match_type"><option value="exact"<?= $f['type'] === 'exact' ? ' selected' : '' ?>>Ακριβές</option><option value="regex"<?= $f['type'] === 'regex' ? ' selected' : '' ?>>Regex</option></select></label>
      <label>Κωδικός<select name="code"><?php foreach (REDIRECT_CODES as $c): ?><option<?= (int) $f['code'] === $c ? ' selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Από (πηγή)<input name="source" value="<?= e($f['source']) ?>" required placeholder="/palia-selida/ ή ^/old/(.*)$"></label>
    <label>Προς (προορισμός)<input name="target" value="<?= e($f['target']) ?>" placeholder="/nea-selida/ ή /new/$1 (κενό για 410/451)"></label>
    <label>Σημείωση<input name="note" value="<?= e((string) ($f['note'] ?? '')) ?>"></label>
    <label class="check"><input type="checkbox" name="active" value="1"<?= ($f['active'] ?? 1) ? ' checked' : '' ?>> Ενεργή</label>
    <div class="actions"><button class="btn" type="submit">Αποθήκευση</button><?php if ($f['id']): ?><a class="btn ghost" href="/admin/redirects">Ακύρωση</a><?php endif; ?></div>
    <p class="muted small">Το σύστημα αρνείται πηγή = προορισμό και βρόχους, και συμπτύσσει αυτόματα τις αλυσίδες ανακατευθύνσεων.</p>
  </form>
  <form method="post" class="form card">
    <?= csrf_field() ?><input type="hidden" name="action" value="test">
    <h2>Δοκιμή URL</h2>
    <label>Διεύθυνση<input name="url" placeholder="/palia-selida/"></label>
    <button class="btn ghost" type="submit">Έλεγχος</button>
    <?php if ($test): ?><p class="result"><code><?= e($test) ?></code></p><?php endif; ?>
  </form>
</div>
<?php if ($confirm): $cr = array_values(array_filter($rows, fn($r) => (int) $r['id'] === (int) $confirm))[0] ?? null; if ($cr): ?>
  <div class="flash err" id="confirm">
    Επιβεβαίωση: διαγραφή της ανακατεύθυνσης <code><?= e($cr['source']) ?></code> → <code><?= e($cr['target'] ?: (string) $cr['code']) ?></code>;
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $cr['id'] ?>"><input type="hidden" name="confirm" value="yes"><button class="btn danger" type="submit">Ναι, διαγραφή</button></form>
    <a href="/admin/redirects">Ακύρωση</a>
  </div>
<?php endif; endif; ?>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Από</th><th>Προς</th><th>Κωδ.</th><th>Τύπος</th><th>Hits</th><th>Τελευταίο hit</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">Δεν υπάρχουν ανακατευθύνσεις.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['active'] ? '' : 'inactive' ?>">
      <td><code><?= e($r['source']) ?></code><?php if ($r['note']): ?><br><span class="muted small"><?= e($r['note']) ?></span><?php endif; ?></td>
      <td><code><?= e($r['target'] ?: '—') ?></code></td>
      <td><?= (int) $r['code'] ?></td>
      <td><?= $r['match_type'] === 'regex' ? 'Regex' : 'Ακριβές' ?></td>
      <td><?= (int) $r['hits'] ?></td>
      <td class="small"><?= $r['last_hit'] ? e(date('d/m/Y H:i', strtotime($r['last_hit']))) : '—' ?></td>
      <td class="nowrap">
        <a href="/admin/redirects?edit=<?= (int) $r['id'] ?>">Επεξ.</a>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="linkbtn" type="submit"><?= $r['active'] ? 'Απενεργ.' : 'Ενεργ.' ?></button></form>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="linkbtn danger-text" type="submit">Διαγραφή</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
