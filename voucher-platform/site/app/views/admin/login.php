<h1 class="disp" style="font-size:56px">Διαχείριση</h1>
<form class="form" method="post" style="margin-top:16px"><?= csrf_field() ?>
  <div class="field"><label for="a-e">Email</label><input id="a-e" type="email" name="email" required autocomplete="email"></div>
  <div class="field"><label for="a-p">Κωδικός πρόσβασης</label><input id="a-p" type="password" name="password" required autocomplete="current-password"></div>
  <button class="btn gold block" type="submit">Σύνδεση</button>
</form>
