<div class="sec">
  <h1 class="disp h2">Στοιχεία</h1>
  <?= view('account/_nav', ['tab' => $tab]) ?>
  <div class="grid g2">
    <form class="form panel" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <h2>Προφίλ</h2>
      <div class="field"><label for="pr-n">Όνομα</label><input id="pr-n" name="name" required value="<?= e($u['name']) ?>" autocomplete="name"></div>
      <div class="field"><label for="pr-e">Email</label><input id="pr-e" type="email" value="<?= e($u['email']) ?>" disabled></div>
      <div class="field"><label for="pr-p">Τηλέφωνο</label><input id="pr-p" type="tel" name="phone" value="<?= e($u['phone']) ?>" autocomplete="tel"></div>
      <button class="btn" type="submit">Αποθήκευση</button>
    </form>
    <form class="form panel" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h2>Κωδικός πρόσβασης</h2>
      <div class="field"><label for="pw-c">Τρέχων κωδικός</label><input id="pw-c" type="password" name="current" required autocomplete="current-password"></div>
      <div class="field"><label for="pw-n">Νέος κωδικός</label><input id="pw-n" type="password" name="password" required minlength="10" autocomplete="new-password"></div>
      <button class="btn dark" type="submit">Αλλαγή κωδικού</button>
    </form>
  </div>
  <form method="post" action="/exodos" style="margin-top:24px"><?= csrf_field() ?><button class="btn ghost sm" type="submit">Αποσύνδεση</button></form>
</div>
