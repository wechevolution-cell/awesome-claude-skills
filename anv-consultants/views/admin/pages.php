<?php use function App\e; use const App\PAGE_TYPES; ?>
<div class="head-row"><h1>Σελίδες</h1><div class="actions"><a class="btn" href="/admin/pages/edit">+ Νέα σελίδα</a><a class="btn ghost" href="/admin/import">Εισαγωγή .md</a></div></div>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Τίτλος</th><th>Τύπος</th><th>Κατάσταση</th><th>SEO</th><th>AEO</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="<?= $r['depth'] ? 'child' : '' ?>"><a href="/admin/pages/edit?id=<?= (int) $r['id'] ?>"><b><?= e($r['title']) ?></b></a><?= $r['is_placeholder'] ? ' <span class="pill warn">πρότυπο</span>' : '' ?><?= $r['noindex'] ? ' <span class="pill">noindex</span>' : '' ?><br><span class="muted small"><?= e($r['path']) ?></span></td>
      <td><?= e(PAGE_TYPES[$r['type']] ?? $r['type']) ?></td>
      <td><span class="pill <?= $r['status'] === 'published' ? 'ok' : ($r['status'] === 'scheduled' ? 'info' : '') ?>"><?= e(['published' => 'Δημοσιευμένη', 'draft' => 'Πρόχειρο', 'scheduled' => 'Προγραμματισμένη'][$r['status']] ?? $r['status']) ?></span><?php if ($r['status'] === 'scheduled' && $r['publish_at']): ?><br><span class="small muted"><?= e(date('d/m/Y H:i', strtotime($r['publish_at']))) ?></span><?php endif; ?></td>
      <td><span class="score s<?= (int) floor($r['seo_score'] / 20) ?>"><?= (int) $r['seo_score'] ?></span></td>
      <td><span class="score s<?= (int) floor($r['aeo_score'] / 20) ?>"><?= (int) $r['aeo_score'] ?></span></td>
      <td class="nowrap"><a href="<?= e($r['path']) ?>" target="_blank">Προβολή</a><?php if ($r['type'] === 'service'): ?> · <a href="/admin/pages/edit?parent=<?= (int) $r['id'] ?>">+ Υποσελίδα</a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
