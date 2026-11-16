<form class="form panel" method="post" style="max-width:720px"><?= csrf_field() ?>
  <p class="small muted">Η επιχείρηση δημιουργείται σε έγκριση. Ο υπεύθυνος λαμβάνει email για να ορίσει κωδικό πρόσβασης.</p>
  <div class="grid g2"><div class="field"><label for="b-b">Επωνυμία *</label><input id="b-b" name="business" required value="<?= e(old('business')) ?>"></div><div class="field"><label for="b-v">ΑΦΜ</label><input id="b-v" name="vat" value="<?= e(old('vat')) ?>"></div></div>
  <div class="grid g2"><div class="field"><label for="b-a">Διεύθυνση</label><input id="b-a" name="address" value="<?= e(old('address')) ?>"></div><div class="field"><label for="b-c">Πόλη</label><input id="b-c" name="city" value="<?= e(old('city')) ?>"></div></div>
  <div class="grid g2"><div class="field"><label for="b-n">Υπεύθυνος *</label><input id="b-n" name="name" required value="<?= e(old('name')) ?>"></div><div class="field"><label for="b-t">Τηλέφωνο</label><input id="b-t" name="phone" value="<?= e(old('phone')) ?>"></div></div>
  <div class="field"><label for="b-e">Email υπευθύνου *</label><input id="b-e" type="email" name="email" required value="<?= e(old('email')) ?>"></div>
  <button class="btn gold" type="submit">Δημιουργία και αποστολή πρόσκλησης</button>
</form>
