<h1 class="disp" style="font-size:56px">Πρώτη ρύθμιση</h1>
<p class="muted">Δημιούργησε τον λογαριασμό διαχειριστή. Δεν υπάρχουν προεπιλεγμένοι κωδικοί.</p>
<?php foreach ($errors as $er): ?><div class="alert err" style="margin-top:8px"><?= e($er) ?></div><?php endforeach ?>
<form class="form" method="post" style="margin-top:16px"><?= csrf_field() ?>
  <div class="field"><label for="s-site">Όνομα brand (αλλάζει αργότερα)</label><input id="s-site" name="site_name" value="<?= e(input('site_name')) ?>" placeholder="Brand"></div>
  <div class="field"><label for="s-name">Το όνομά σου</label><input id="s-name" name="name" required value="<?= e(input('name')) ?>" autocomplete="name"></div>
  <div class="field"><label for="s-email">Email</label><input id="s-email" type="email" name="email" required value="<?= e(input('email')) ?>" autocomplete="email"></div>
  <div class="field"><label for="s-p1">Κωδικός πρόσβασης</label><input id="s-p1" type="password" name="password" required minlength="10" autocomplete="new-password"><span class="help">Τουλάχιστον 10 χαρακτήρες.</span></div>
  <div class="field"><label for="s-p2">Επανάληψη κωδικού</label><input id="s-p2" type="password" name="password2" required minlength="10" autocomplete="new-password"></div>
  <button class="btn gold block" type="submit">Δημιουργία διαχειριστή</button>
</form>
