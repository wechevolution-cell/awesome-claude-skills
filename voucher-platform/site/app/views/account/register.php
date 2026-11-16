<div class="auth">
  <h1 class="disp">Εγγραφή</h1>
  <p class="muted">Ο λογαριασμός κρατά τους κωδικούς και το ιστορικό σου. Είσαι επιχείρηση; <a class="link" href="/synergates">Κάνε αίτηση εδώ</a>.</p>
  <form class="form" method="post" action="/eggrafi" style="margin-top:20px">
    <?= csrf_field() ?><?= bot_fields() ?>
    <div class="field"><label for="r-name">Όνομα</label><input id="r-name" name="name" required autocomplete="name" value="<?= e(old('name')) ?>"></div>
    <div class="field"><label for="r-email">Email</label><input id="r-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
    <div class="field"><label for="r-pass">Κωδικός πρόσβασης</label><input id="r-pass" type="password" name="password" required minlength="10" autocomplete="new-password"><span class="help">Τουλάχιστον 10 χαρακτήρες.</span></div>
    <label class="check"><input type="checkbox" name="terms" value="1" required> <span>Αποδέχομαι τους <a class="link" href="/oroi">όρους χρήσης</a> και την <a class="link" href="/aporrito">πολιτική απορρήτου</a>.</span></label>
    <button class="btn gold block" type="submit">Δημιουργία λογαριασμού</button>
  </form>
  <p class="small" style="margin-top:16px">Έχεις ήδη λογαριασμό; <a class="link" href="/eisodos">Σύνδεση</a></p>
</div>
