<section class="hero split75">
  <div>
    <span class="eyebrow">Για επιχειρήσεις</span>
    <h1 class="disp h1" style="margin-top:10px">Νέοι πελάτες.<br><span class="foil">Μηδέν χαρτιά.</span></h1>
    <p class="lead">Δημοσίευσε προσφορές για τις υπηρεσίες σου. Οι πελάτες παίρνουν κωδικό, εσύ τον εξαργυρώνεις με το κινητό, και βλέπεις αναφορές για κάθε κατάστημα.</p>
    <ul class="trust" style="flex-direction:column;gap:8px;font-size:15px">
      <span>Περιβάλλον επιχείρησης για προσφορές, καταστήματα και προσωπικό</span>
      <span>Εξαργύρωση με σάρωση QR ή πληκτρολόγηση κωδικού</span>
      <span>Ιστορικό εξαργυρώσεων και εξαγωγή αναφορών CSV</span>
      <span>Δωρεάν ή επί πληρωμή κωδικοί, εσύ αποφασίζεις</span>
    </ul>
  </div>
  <div class="panel" id="aitisi">
    <h2>Αίτηση συνεργασίας</h2>
    <?php if (setting('business_signup') !== '1'): ?>
      <p class="muted">Οι αιτήσεις είναι προσωρινά κλειστές. <a class="link" href="/epikoinonia">Επικοινώνησε μαζί μας</a>.</p>
    <?php else: ?>
    <form class="form" method="post" action="/synergates">
      <?= csrf_field() ?><?= bot_fields() ?>
      <div class="field"><label for="p-b">Επωνυμία επιχείρησης</label><input id="p-b" name="business" required value="<?= e(old('business')) ?>" autocomplete="organization"></div>
      <div class="grid g2"><div class="field"><label for="p-v">ΑΦΜ</label><input id="p-v" name="vat" inputmode="numeric" value="<?= e(old('vat')) ?>"></div>
      <div class="field"><label for="p-c">Πόλη</label><input id="p-c" name="city" value="<?= e(old('city')) ?>" autocomplete="address-level2"></div></div>
      <div class="field"><label for="p-n">Υπεύθυνος</label><input id="p-n" name="name" required value="<?= e(old('name')) ?>" autocomplete="name"></div>
      <div class="grid g2"><div class="field"><label for="p-e">Email</label><input id="p-e" type="email" name="email" required value="<?= e(old('email')) ?>" autocomplete="email"></div>
      <div class="field"><label for="p-t">Τηλέφωνο</label><input id="p-t" type="tel" name="phone" value="<?= e(old('phone')) ?>" autocomplete="tel"></div></div>
      <div class="field"><label for="p-p">Κωδικός πρόσβασης</label><input id="p-p" type="password" name="password" required minlength="10" autocomplete="new-password"><span class="help">Τουλάχιστον 10 χαρακτήρες.</span></div>
      <label class="check"><input type="checkbox" name="consent" value="1" required> <span>Αποδέχομαι τους <a class="link" href="/oroi">όρους συνεργασίας</a> και την <a class="link" href="/aporrito">πολιτική απορρήτου</a>.</span></label>
      <button class="btn gold" type="submit">Στείλε αίτηση →</button>
      <?php if (!empty($_COOKIE['ref'])): ?><p class="small muted">Κωδικός σύστασης: <?= e($_COOKIE['ref']) ?></p><?php endif ?>
    </form>
    <?php endif ?>
  </div>
</section>
