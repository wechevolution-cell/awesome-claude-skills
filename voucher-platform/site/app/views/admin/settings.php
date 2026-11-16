<form class="form" method="post"><?= csrf_field() ?>
<?php foreach ($groups as $gid => [$gname, $fields]): ?>
  <fieldset class="fgroup" id="<?= $gid ?>"><legend><?= e($gname) ?></legend>
  <?php foreach ($fields as $k => $f): $v = (string)setting($k); $id = 'set-' . $k; ?>
    <div class="field">
    <?php if ($f[1] === 'bool'): ?><label class="check"><input type="checkbox" id="<?= $id ?>" name="<?= $k ?>" value="1" <?= $v === '1' ? 'checked' : '' ?>> <span><?= e($f[0]) ?></span></label>
    <?php elseif ($f[1] === 'textarea'): ?><label for="<?= $id ?>"><?= e($f[0]) ?></label><textarea id="<?= $id ?>" name="<?= $k ?>"><?= e($v) ?></textarea>
    <?php elseif ($f[1] === 'select'): ?><label for="<?= $id ?>"><?= e($f[0]) ?></label><select id="<?= $id ?>" name="<?= $k ?>"><?php foreach ($f[2] as $ok => $ol): ?><option value="<?= e($ok) ?>" <?= $v === (string)$ok ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach ?></select>
    <?php else: ?><label for="<?= $id ?>"><?= e($f[0]) ?></label><input id="<?= $id ?>" type="<?= $f[1] === 'number' ? 'number' : ($f[1] === 'email' ? 'email' : 'text') ?>" name="<?= $k ?>" value="<?= e($v) ?>">
    <?php endif ?>
    </div>
  <?php endforeach ?>
  </fieldset>
<?php endforeach ?>
<div><button class="btn gold" type="submit">Αποθήκευση ρυθμίσεων</button></div>
</form>
