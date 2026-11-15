<?php use function App\{e, csrf_field}; ?>
<div class="head-row"><h1>404 Monitor</h1>
  <div class="actions">
    <a class="btn ghost" href="/admin/404<?= $showIgnored ? '' : '?show=ignored' ?>"><?= $showIgnored ? 'Προβολή ενεργών' : 'Προβολή αγνοημένων' ?></a>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="cleanup"><button class="btn ghost" type="submit">Καθαρισμός</button></form>
  </div>
</div>
<p class="muted">Καταγράφονται οι διευθύνσεις που δεν βρέθηκαν. Οι αυτόματες σαρώσεις (wp-login, .env, .php κ.λπ.) αγνοούνται. Η καταγραφή γίνεται από επισκέψεις: σε νέο site είναι φυσικό να είναι άδειο.</p>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Διεύθυνση</th><th>Hits</th><th>Referrer</th><th>Τελευταίο</th><th>Ενέργεια</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="5" class="muted">Καμία εγγραφή.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><code><?= e($r['path']) ?></code></td>
      <td><?= (int) $r['hits'] ?></td>
      <td class="small"><?= e($r['referrer'] ?: '—') ?></td>
      <td class="small"><?= e(date('d/m/Y H:i', strtotime($r['last_hit']))) ?></td>
      <td>
        <form method="post" class="nf-form">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><?php if ($showIgnored): ?><input type="hidden" name="show" value="ignored"><?php endif; ?>
          <input name="target" value="<?= e($r['suggest']['path'] ?? '') ?>" placeholder="/proorismos/" aria-label="Προορισμός για <?= e($r['path']) ?>">
          <?php if (!empty($r['suggest'])): ?><small class="muted">Πρόταση: <?= e($r['suggest']['title']) ?></small><?php endif; ?>
          <span class="btnrow">
            <button class="btn small" name="action" value="301">301</button>
            <button class="btn ghost small" name="action" value="302">302</button>
            <button class="btn ghost small" name="action" value="410">410</button>
            <?php if (!$showIgnored): ?><button class="btn ghost small" name="action" value="ignore">Αγνόηση</button><?php endif; ?>
            <button class="btn ghost small" name="action" value="delete">Διαγραφή</button>
          </span>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
