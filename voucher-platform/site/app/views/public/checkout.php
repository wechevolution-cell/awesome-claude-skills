<div class="sec" style="display:grid;grid-template-columns:minmax(0,7fr) minmax(0,5fr);gap:32px" data-checkout>
  <div>
    <h1 class="disp h1">Ολοκλήρωση</h1>
    <?php foreach ($errors as $er): ?><div class="alert err" style="margin-top:10px"><?= e($er) ?></div><?php endforeach ?>
    <form class="form panel" method="post" action="/tameio" style="margin-top:20px">
      <?= csrf_field() ?>
      <div class="field"><label for="co-name">Ονοματεπώνυμο</label><input id="co-name" name="name" required autocomplete="name" value="<?= e(input('name', $u['name'])) ?>"></div>
      <div class="field"><label for="co-email">Email για τους κωδικούς</label><input id="co-email" type="email" name="email" required autocomplete="email" value="<?= e(input('email', $u['email'])) ?>"><span class="help">Οι κωδικοί εμφανίζονται και στον λογαριασμό σου.</span></div>
      <div class="alert info"><?= $t['total'] > 0 ? 'Στο επόμενο βήμα πληρώνεις με ασφάλεια μέσω του παρόχου πληρωμών.' : 'Όλοι οι κωδικοί είναι δωρεάν. Θα εκδοθούν αμέσως.' ?></div>
      <label class="check"><input type="checkbox" name="terms" value="1" required> <span>Αποδέχομαι τους <a class="link" href="/oroi" target="_blank">όρους χρήσης</a> και τους όρους εξαργύρωσης κάθε προσφοράς.</span></label>
      <button class="btn gold block" type="submit"><?= $t['total'] > 0 ? 'Συνέχεια στην πληρωμή · ' . money($t['total']) : 'Έκδοση κωδικών' ?> →</button>
    </form>
  </div>
  <aside class="panel" style="align-self:start">
    <h2>Σύνοψη</h2>
    <ul style="list-style:none;padding:0;margin:0">
    <?php foreach ($lines as $l): ?><li class="line" style="grid-template-columns:minmax(0,1fr) auto"><div><b><?= e($l['title']) ?></b><span class="small muted"><?= e($l['service']['business_name']) ?> · ×<?= $l['qty'] ?></span></div><span class="tnum"><?= $l['line_cents'] ? money($l['line_cents']) : 'Δωρεάν' ?></span></li><?php endforeach ?>
    </ul>
    <div class="totals"><?php if ($t['savings'] > 0): ?><div class="save"><span>Εξοικονομείς</span><span><?= money($t['savings']) ?></span></div><?php endif ?><div class="grand"><span>Σύνολο</span><span class="tnum"><?= money($t['total']) ?></span></div></div>
    <a class="link small" href="/kalathi">Αλλαγές στο καλάθι</a>
  </aside>
</div>
<style>@media (max-width:860px){[data-checkout]{grid-template-columns:1fr!important}}</style>
