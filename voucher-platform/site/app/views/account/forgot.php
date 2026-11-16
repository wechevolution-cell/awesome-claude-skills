<div class="auth">
  <h1 class="disp">Νέος κωδικός</h1>
  <p class="muted">Γράψε το email του λογαριασμού σου. Θα σου στείλουμε σύνδεσμο που ισχύει μία ώρα.</p>
  <form class="form" method="post" action="/xehasa-kodiko" style="margin-top:20px"><?= csrf_field() ?>
    <div class="field"><label for="f-email">Email</label><input id="f-email" type="email" name="email" required autocomplete="email"></div>
    <button class="btn gold block" type="submit">Αποστολή συνδέσμου</button>
  </form>
</div>
