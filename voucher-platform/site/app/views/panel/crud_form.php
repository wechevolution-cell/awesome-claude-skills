<?php
$row = $row ?? [];
$oldv = $_SESSION['old_view'] ?? [];
$val = function (string $n) use ($row, $oldv, $errors) {
    if ($errors && array_key_exists($n, $oldv)) {
        return $oldv[$n];
    }
    return $row[$n] ?? null;
};
$groups = [];
foreach ($d['fields'] as $n => $f) {
    $groups[$f['group'] ?? 'Βασικά'][$n] = $f;
}
?>
<?php if (!empty($d['notice'])): ?><div class="alert info" style="margin-bottom:14px"><?= e($d['notice']) ?></div><?php endif ?>
<?php if (!empty($row['review_note'])): ?><div class="alert err" style="margin-bottom:14px">Σημείωση έγκρισης: <?= e($row['review_note']) ?></div><?php endif ?>
<?php if ($errors): ?><div class="alert err" style="margin-bottom:14px">Διόρθωσε τα πεδία που σημειώνονται παρακάτω.</div><?php endif ?>
<form class="form" method="post" action="<?= e($d['base']) ?>/save" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($row['id'] ?? '') ?>">
  <?php foreach ($groups as $gname => $fields): ?>
  <fieldset class="fgroup"><legend><?= e($gname) ?></legend>
  <?php foreach ($fields as $n => $f): $id = 'f-' . $n; $v = $val($n); $err = $errors[$n] ?? null; ?>
    <div class="field">
      <?php if ($f['type'] === 'bool'): ?>
        <label class="check"><input type="checkbox" id="<?= $id ?>" name="<?= $n ?>" value="1" <?= $v ? 'checked' : '' ?>> <span><?= e($f['label']) ?></span></label>
      <?php else: ?>
        <label for="<?= $id ?>"><?= e($f['label']) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
        <?php switch ($f['type']):
          case 'textarea': ?><textarea id="<?= $id ?>" name="<?= $n ?>"><?= e($v) ?></textarea><?php break;
          case 'select': ?><select id="<?= $id ?>" name="<?= $n ?>"><?php foreach (crud_options($f['options']) as $k => $lab): ?><option value="<?= e($k) ?>" <?= (string)$v === (string)$k ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach ?></select><?php break;
          case 'number': ?><input id="<?= $id ?>" type="number" name="<?= $n ?>" value="<?= e($v ?? ($row ? '' : ($f['default'] ?? ''))) ?>" step="1"><?php break;
          case 'money': ?><input id="<?= $id ?>" type="text" inputmode="decimal" name="<?= $n ?>" value="<?= e($v === null ? '' : (is_numeric($v) && !($errors && isset($oldv[$n])) ? number_format(((int)$v) / 100, 2, ',', '') : $v)) ?>" placeholder="0,00"><?php break;
          case 'datetime': ?><input id="<?= $id ?>" type="datetime-local" name="<?= $n ?>" value="<?= e($v ? date('Y-m-d\TH:i', strtotime((string)$v)) : '') ?>"><?php break;
          case 'email': ?><input id="<?= $id ?>" type="email" name="<?= $n ?>" value="<?= e($v) ?>"><?php break;
          case 'password': ?><input id="<?= $id ?>" type="password" name="<?= $n ?>" autocomplete="new-password" minlength="10"><?php break;
          case 'image': ?>
            <?php if (!empty($row[$n])): ?><img class="imgprev" src="<?= e($row[$n]) ?>" alt=""><label class="check"><input type="checkbox" name="<?= $n ?>__remove" value="1"> <span>Αφαίρεση εικόνας</span></label><?php endif ?>
            <input id="<?= $id ?>" type="file" name="<?= $n ?>" accept="image/jpeg,image/png,image/webp,image/gif"><span class="help">JPG, PNG, WebP ή GIF έως 10 MB. Μεγάλες εικόνες μικραίνουν αυτόματα στα 2000px.</span><?php break;
          case 'multi':
            $sel = ($errors && isset($oldv[$n])) ? array_map('intval', (array)$oldv[$n]) : (!empty($row['id']) ? crud_pivot_values($f, (int)$row['id']) : []);
            $opts = crud_options($f['options']); ?>
            <div class="multi" id="<?= $id ?>"><?php if (!$opts): ?><span class="small muted">Δεν υπάρχουν διαθέσιμες επιλογές ακόμα.</span><?php endif ?><?php foreach ($opts as $k => $lab): ?><label class="check"><input type="checkbox" name="<?= $n ?>[]" value="<?= (int)$k ?>" <?= in_array((int)$k, $sel, true) ? 'checked' : '' ?>> <span><?= e($lab) ?></span></label><?php endforeach ?></div><?php break;
          case 'variants':
            $vs = !empty($row['id']) ? all('SELECT * FROM service_variants WHERE service_id = ? ORDER BY sort, id', [$row['id']]) : []; ?>
            <div class="stack" data-variants>
              <div class="vrow small muted"><span>Όνομα (π.χ. 60 λεπτά)</span><span>Κανονική €</span><span>Προσφορά €</span><span></span></div>
              <?php foreach (array_merge($vs, [['label' => '', 'regular_cents' => '', 'price_cents' => '']]) as $vv): ?>
              <div class="vrow"><input name="variant_label[]" value="<?= e($vv['label']) ?>" aria-label="Όνομα παραλλαγής"><input name="variant_regular[]" inputmode="decimal" value="<?= $vv['regular_cents'] === '' ? '' : e(number_format($vv['regular_cents'] / 100, 2, ',', '')) ?>" aria-label="Κανονική τιμή"><input name="variant_price[]" inputmode="decimal" value="<?= $vv['price_cents'] === '' ? '' : e(number_format($vv['price_cents'] / 100, 2, ',', '')) ?>" aria-label="Τιμή προσφοράς"><button class="icon-btn" type="button" data-variant-remove aria-label="Αφαίρεση">✕</button></div>
              <?php endforeach ?>
              <button class="btn sm ghost" type="button" data-variant-add style="justify-self:start">+ Παραλλαγή</button>
            </div><?php break;
          default: ?><input id="<?= $id ?>" type="text" name="<?= $n ?>" value="<?= e($v) ?>"><?php endswitch ?>
      <?php endif ?>
      <?php if (!empty($f['help'])): ?><span class="help"><?= e($f['help']) ?></span><?php endif ?>
      <?php if ($err): ?><span class="error"><?= e($err) ?></span><?php endif ?>
    </div>
  <?php endforeach ?>
  </fieldset>
  <?php endforeach ?>
  <div class="row"><button class="btn gold" type="submit">Αποθήκευση</button><a class="btn ghost" href="<?= e(!empty($d['single_id']) ? '/' . $d['area'] : $d['base']) ?>">Πίσω</a>
  <?php if (!empty($row['id']) && empty($d['no_delete'])): ?><a class="link small" style="margin-left:auto" href="<?= e($d['base'] . '/' . $row['id'] . '/delete') ?>">Διαγραφή</a><?php endif ?></div>
</form>
