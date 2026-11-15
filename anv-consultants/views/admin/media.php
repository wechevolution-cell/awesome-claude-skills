<?php use function App\{e, csrf_field}; ?>
<h1>Πολυμέσα</h1>
<form method="post" enctype="multipart/form-data" class="form card" style="max-width:640px">
  <?= csrf_field() ?><input type="hidden" name="action" value="upload">
  <label>Εικόνα (JPG, PNG, WebP, GIF · έως 10 MB · σμικρύνεται αυτόματα στα 2000 px)<input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif" required></label>
  <label>Περιγραφή (alt)<input name="alt" placeholder="π.χ. Τοπίο με δέντρα και καθαρό ουρανό"></label>
  <button class="btn" type="submit">Ανέβασμα</button>
</form>
<?php if ($confirm): $cm = array_values(array_filter($rows, fn($r) => (int) $r['id'] === (int) $confirm))[0] ?? null; if ($cm): ?>
  <div class="flash err">Επιβεβαίωση διαγραφής <code><?= e($cm['path']) ?></code>;
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $cm['id'] ?>"><input type="hidden" name="confirm" value="yes"><button class="btn danger" type="submit">Ναι, διαγραφή</button></form>
    <a href="/admin/media">Ακύρωση</a></div>
<?php endif; endif; ?>
<div class="media-grid">
  <?php if (!$rows): ?><p class="muted">Δεν υπάρχουν εικόνες ακόμα.</p><?php endif; ?>
  <?php foreach ($rows as $m): ?>
    <figure class="card media">
      <img src="<?= e($m['path']) ?>" alt="<?= e($m['alt']) ?>" loading="lazy" width="<?= (int) $m['width'] ?>" height="<?= (int) $m['height'] ?>">
      <figcaption>
        <code class="copy" title="Αντιγραφή"><?= e($m['path']) ?></code>
        <span class="muted small"><?= (int) $m['width'] ?>×<?= (int) $m['height'] ?> · <?= round($m['size'] / 1024) ?> KB<?= $m['webp'] ? ' · +WebP' : '' ?></span>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="alt"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input name="alt" value="<?= e($m['alt']) ?>" aria-label="Alt"><button class="btn small ghost" type="submit">Alt</button></form>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="linkbtn danger-text" type="submit">Διαγραφή</button></form>
      </figcaption>
    </figure>
  <?php endforeach; ?>
</div>
