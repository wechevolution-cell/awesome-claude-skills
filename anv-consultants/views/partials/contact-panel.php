<?php
use function App\{e, setting, tel_href, icon, address_line, hours, map_query};
use const App\DAY_NAMES;
$phone = (string) setting('phone', '');
$phone2 = (string) setting('phone2', '');
$email = (string) setting('email', '');
$addr = address_line();
$h = hours();
$confirmed = setting('hours_confirmed') === '1';
$q = map_query();
$today = (int) date('N');
?>
<div class="visit-grid">
  <div class="panel" data-hours='<?= e((string) json_encode($h)) ?>'>
    <span class="badge badge-closed" data-open-badge hidden>Ωράριο</span>
    <?php if ($phone !== ''): ?>
      <div class="row"><span class="ic"><?= icon('phone', 18) ?></span><div><div class="k">Τηλέφωνο</div><div class="v"><a href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a><?php if ($phone2 !== ''): ?><br><a href="<?= e(tel_href($phone2)) ?>"><?= e($phone2) ?></a><?php endif; ?></div></div></div>
    <?php endif; ?>
    <?php if ($email !== ''): ?>
      <div class="row"><span class="ic"><?= icon('mail', 18) ?></span><div><div class="k">Email</div><div class="v"><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div></div></div>
    <?php endif; ?>
    <?php if ($addr !== ''): ?>
      <div class="row"><span class="ic"><?= icon('pin', 18) ?></span><div><div class="k">Διεύθυνση</div><div class="v"><?= e($addr) ?></div></div></div>
    <?php endif; ?>
    <div class="row"><span class="ic"><?= icon('clock', 18) ?></span><div style="flex:1;min-width:0"><div class="k">Ωράριο γραφείου</div>
      <table class="hours"><tbody>
        <?php foreach ($h as $d => $slots): ?>
          <tr<?= $d === $today ? ' class="today"' : '' ?>><td><?= e(DAY_NAMES[$d]) ?></td><td><?= $slots ? implode('<br>', array_map(fn($s) => e($s[0] . ' – ' . $s[1]), $slots)) : 'Κλειστά' ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
      <?php if ($note = setting('hours_note')): ?><p class="small muted" style="margin-top:8px"><?= e($note) ?></p><?php endif; ?>
    </div></div>
    <?php if ($phone === '' && $email === '' && $addr === ''): ?>
      <p class="pending">Τα στοιχεία επικοινωνίας θα εμφανιστούν μόλις συμπληρωθούν από το admin.</p>
    <?php endif; ?>
    <div class="btns">
      <?php if ($phone !== ''): ?><a class="btn btn-primary" href="<?= e(tel_href($phone)) ?>"><?= icon('phone', 18) ?>Καλέστε τώρα</a><?php endif; ?>
      <?php if ($email !== ''): ?><a class="btn btn-soft" href="mailto:<?= e($email) ?>"><?= icon('mail', 18) ?>Στείλτε email</a><?php endif; ?>
    </div>
  </div>
  <div class="map" data-map="<?= e($q) ?>">
    <div class="map-ph">
      <svg class="bg" viewBox="0 0 600 380" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><defs><linearGradient id="mg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#eaf1f8"/><stop offset="1" stop-color="#dbe7f2"/></linearGradient></defs><rect width="600" height="380" fill="url(#mg)"/><g stroke="#fff" stroke-width="9" fill="none" stroke-linecap="round"><path d="M-20 120 Q300 60 620 150"/><path d="M-20 300 Q260 250 620 290"/><path d="M200 -20 Q260 200 230 400"/></g><path d="M380 -20 Q420 190 470 400" stroke="#cfe3f2" stroke-width="22" fill="none"/><g transform="translate(300 200)"><circle r="34" fill="#78a8cb" opacity=".25"/><path d="M0 0 C-14 -20 -20 -28 -20 -38 A20 20 0 1 1 20 -38 C20 -28 14 -20 0 0Z" fill="#004c93"/><circle cy="-38" r="7" fill="#fff"/></g></svg>
      <?php if ($q !== ''): ?>
        <div class="box">
          <strong>Χάρτης Google</strong>
          <p>Ο χάρτης φορτώνεται από την Google, που μπορεί να ορίσει δικά της cookies.</p>
          <div class="btns" style="justify-content:center">
            <button class="btn btn-primary" type="button" data-map-load>Εμφάνιση χάρτη</button>
            <a class="btn btn-soft" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($q)) ?>" target="_blank" rel="noopener">Άνοιγμα στο Google Maps</a>
          </div>
        </div>
      <?php else: ?>
        <div class="box"><strong>Χάρτης</strong><p>Ο χάρτης θα εμφανιστεί όταν συμπληρωθεί η διεύθυνση ή οι συντεταγμένες στο admin.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>
