<div class="grid g2">
  <div class="panel"><h2>Ομάδα</h2>
    <div class="table-wrap" style="border:0"><table class="t" style="min-width:0"><tbody>
    <?php foreach ($staff as $s): ?><tr><td><b><?= e($s['name']) ?></b><br><span class="small muted"><?= e($s['email']) ?></span></td><td><?= $s['role'] === 'owner' ? 'Ιδιοκτήτης' : 'Υπάλληλος' ?><?= $s['store'] ? '<br><span class="small muted">' . e($s['store']) . '</span>' : '' ?></td>
      <td><?php if ($s['role'] === 'staff'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>"><button class="link small" style="background:none;border:0;cursor:pointer" type="submit">Αφαίρεση</button></form><?php endif ?></td></tr><?php endforeach ?>
    </tbody></table></div>
  </div>
  <form class="form panel" method="post"><?= csrf_field() ?>
    <h2>Νέος υπάλληλος</h2>
    <p class="small muted">Ο υπάλληλος βλέπει μόνο την εξαργύρωση και το ιστορικό εξαργυρώσεων.</p>
    <div class="field"><label for="s-n">Όνομα</label><input id="s-n" name="name" required value="<?= e(old('name')) ?>"></div>
    <div class="field"><label for="s-e">Email</label><input id="s-e" type="email" name="email" required value="<?= e(old('email')) ?>"></div>
    <div class="field"><label for="s-p">Κωδικός πρόσβασης</label><input id="s-p" type="password" name="password" minlength="10" autocomplete="new-password"><span class="help">Τουλάχιστον 10 χαρακτήρες. Δώσ' τον στον υπάλληλο.</span></div>
    <?php if ($stores): ?><div class="field"><label for="s-s">Κατάστημα</label><select id="s-s" name="store_id"><option value="">Όλα</option><?php foreach ($stores as $st): ?><option value="<?= (int)$st['id'] ?>"><?= e($st['name']) ?></option><?php endforeach ?></select></div><?php endif ?>
    <button class="btn gold" type="submit">Προσθήκη</button>
  </form>
</div>
