<div class="auth">
  <h1 class="disp">Σύνδεση</h1>
  <p class="muted">Για καταναλωτές, επιχειρήσεις και αντιπροσώπους.</p>
  <form class="form" method="post" action="/eisodos" style="margin-top:20px">
    <?= csrf_field() ?>
    <div class="field"><label for="l-email">Email</label><input id="l-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
    <div class="field"><label for="l-pass">Κωδικός πρόσβασης</label><input id="l-pass" type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn gold block" type="submit">Σύνδεση</button>
  </form>
  <p class="small" style="margin-top:16px"><a class="link" href="/xehasa-kodiko">Ξέχασα τον κωδικό</a> · Δεν έχεις λογαριασμό; <a class="link" href="/eggrafi">Εγγραφή</a></p>
</div>
