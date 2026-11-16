<div class="auth">
  <h1 class="disp">Νέος κωδικός</h1>
  <form class="form" method="post" action="/epanafora/<?= e($token) ?>" style="margin-top:20px"><?= csrf_field() ?>
    <div class="field"><label for="n-pass">Νέος κωδικός πρόσβασης</label><input id="n-pass" type="password" name="password" required minlength="10" autocomplete="new-password"><span class="help">Τουλάχιστον 10 χαρακτήρες.</span></div>
    <button class="btn gold block" type="submit">Αποθήκευση</button>
  </form>
</div>
