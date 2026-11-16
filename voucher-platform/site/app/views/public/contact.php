<div class="sec narrow">
  <?php partial('crumbs', ['crumbs' => ['Αρχική' => '/', 'Επικοινωνία' => '/epikoinonia']]) ?>
  <h1 class="disp h1" style="margin-top:12px">Επικοινωνία</h1>
  <p class="lead">Για ερωτήσεις σχετικά με κωδικούς ή παραγγελίες, γράψε και τον αριθμό παραγγελίας.</p>
  <?php if (setting('contact_email') || setting('contact_phone')): ?>
    <p><?= setting('contact_email') ? 'Email: <strong>' . e(setting('contact_email')) . '</strong><br>' : '' ?><?= setting('contact_phone') ? 'Τηλέφωνο: <strong>' . e(setting('contact_phone')) . '</strong>' : '' ?></p>
  <?php endif ?>
  <form class="form panel" method="post" action="/epikoinonia" style="margin-top:20px">
    <?= csrf_field() ?><?= bot_fields() ?>
    <div class="grid g2"><div class="field"><label for="c-name">Ονοματεπώνυμο</label><input id="c-name" name="name" required autocomplete="name" value="<?= e(old('name')) ?>"></div>
    <div class="field"><label for="c-email">Email</label><input id="c-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div></div>
    <div class="field"><label for="c-phone">Τηλέφωνο (προαιρετικό)</label><input id="c-phone" type="tel" name="phone" autocomplete="tel" value="<?= e(old('phone')) ?>"></div>
    <div class="field"><label for="c-msg">Μήνυμα</label><textarea id="c-msg" name="message" required minlength="10"><?= e(old('message')) ?></textarea></div>
    <label class="check"><input type="checkbox" name="consent" value="1" required> <span>Συμφωνώ να χρησιμοποιηθούν τα στοιχεία μου για να απαντηθεί το μήνυμα, σύμφωνα με την <a class="link" href="/aporrito">πολιτική απορρήτου</a>.</span></label>
    <button class="btn" type="submit">Αποστολή μηνύματος</button>
  </form>
</div>
