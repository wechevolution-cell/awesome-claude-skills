<?php $hasStatus = in_array('status', columns_of($d['table']), true); ?>
<?php if (!empty($d['notice'])): ?><div class="alert info" style="margin-bottom:14px"><?= e($d['notice']) ?></div><?php endif ?>
<form class="filters" method="get">
  <?php if (!empty($d['search'])): ?><label class="sr" for="cq">Αναζήτηση</label><input class="input" id="cq" name="q" value="<?= e((string)input('q')) ?>" placeholder="Αναζήτηση" style="max-width:260px;min-height:44px;border-radius:999px"><?php endif ?>
  <?php if ($hasStatus): ?><label class="sr" for="cs">Κατάσταση</label><select id="cs" name="status"><option value="">Όλες οι καταστάσεις</option><?php foreach (STATUS_OPTIONS as $k => $v): ?><option value="<?= $k ?>" <?= input('status') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach ?></select><?php endif ?>
  <button class="btn sm dark" type="submit">Φίλτρο</button>
  <span class="small muted"><?= (int)$pg['total'] ?> εγγραφές</span>
  <?php if (empty($d['no_create'])): ?><a class="btn sm gold" href="<?= e($d['base']) ?>/new" style="margin-left:auto">+ Νέα <?= e($d['singular']) ?></a><?php endif ?>
</form>
<?php if ($rows): ?>
<div class="table-wrap"><table class="t">
  <thead><tr><?php foreach ($d['list'] as $col => $label): ?><th><?= e($label) ?></th><?php endforeach ?><th><span class="sr">Ενέργειες</span></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <?php $first = true; foreach ($d['list'] as $col => $label): ?>
        <td><?php if ($first): ?><a class="link" href="<?= e($d['base'] . '/' . $r['id']) ?>"><?= crud_display($d, $col, $r) ?: '#' . (int)$r['id'] ?></a><?php else: ?><?= crud_display($d, $col, $r) ?><?php endif; $first = false; ?></td>
      <?php endforeach ?>
      <td style="white-space:nowrap;text-align:right">
        <?php if ($d['table'] === 'services' && ($r['status'] ?? '') === 'active'): ?><a class="small link" href="/prosfora/<?= e($r['slug']) ?>" target="_blank">Προβολή</a> · <?php endif ?>
        <a class="small link" href="<?= e($d['base'] . '/' . $r['id']) ?>">Επεξεργασία</a>
        <?php if (empty($d['no_delete'])): ?> · <a class="small link" href="<?= e($d['base'] . '/' . $r['id'] . '/delete') ?>">Διαγραφή</a><?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php partial('pager', ['pg' => $pg]) ?>
<?php else: ?>
<div class="empty"><b>Δεν υπάρχουν εγγραφές</b><?php if (empty($d['no_create'])): ?><a class="link" href="<?= e($d['base']) ?>/new">Πρόσθεσε την πρώτη <?= e($d['singular']) ?></a><?php endif ?></div>
<?php endif ?>
