<section class="hero" aria-labelledby="hero-title">
  <div>
    <h1 id="hero-title" class="disp"><?= e(setting('hero_line_1')) ?> <span class="it"><?= e(setting('hero_line_2')) ?></span><br><span class="foil"><?= e(setting('hero_line_3')) ?></span></h1>
    <p class="lead"><?= e(setting('hero_text')) ?></p>
    <form class="search" action="/prosfores" method="get" role="search" style="margin-top:24px">
      <label for="hero-q" class="sr">Περιοχή ή ταχυδρομικός κώδικας</label>
      <input id="hero-q" name="perioxi" placeholder="Γράψε περιοχή ή ΤΚ" autocomplete="address-level2">
      <button class="btn" type="submit">Δες προσφορές</button>
    </form>
    <div class="trust"><span>Δωρεάν εγγραφή</span><span>Κωδικός αμέσως στο κινητό</span><span>Κριτικές μόνο από πραγματικές εξαργυρώσεις</span></div>
  </div>
  <div class="stage">
    <div class="city">
      <canvas id="city" width="600" height="500" aria-label="Εικονογράφηση: νυχτερινή πόλη όπου ανάβουν τα καταστήματα με προσφορά"></canvas>
      <div class="ctl"><span>Πριν</span><label for="cityr" class="sr">Πριν και μετά</label><input id="cityr" type="range" min="0" max="100" value="45"><span>Μετά</span></div>
    </div>
    <?php if ($deal): ?>
    <a class="dealcard" href="/prosfora/<?= e($deal['slug']) ?>">
      <?php if ((int)$deal['price_cents'] === 0): ?><span class="stick free">Δωρεάν</span><?php elseif ($deal['pct'] > 0): ?><span class="stick">−<?= (int)$deal['pct'] ?>%</span><?php endif ?>
      <span class="eyebrow">Προσφορά της ημέρας</span>
      <h3><?= e($deal['title']) ?></h3>
      <span class="small muted"><?= e($deal['business_name']) ?><?= $deal['city'] ? ' · ' . e($deal['city']) : '' ?></span>
      <span class="price" style="margin:10px 0 6px"><?php if ($deal['regular_cents'] > $deal['price_cents']): ?><s><?= money_short((int)$deal['regular_cents']) ?></s><?php endif ?><b><?= money_short((int)$deal['price_cents']) ?></b></span>
      <?php if ($deal['left'] !== null): ?><span class="clock"><span>Απομένουν</span><b><?= (int)$deal['left'] ?> κωδικοί</b></span><?php endif ?>
      <span class="btn gold block">Δες την προσφορά →</span>
    </a>
    <?php endif ?>
  </div>
</section>

<nav class="chips" aria-label="Κατηγορίες">
  <a class="chip" aria-current="true" href="/prosfores">Όλες οι προσφορές</a>
  <?php foreach (nav_categories() as $c): ?><a class="chip" href="/katigoria/<?= e($c['slug']) ?>"><?= e($c['name']) ?></a><?php endforeach ?>
  <a class="chip" href="/prosfores?t=free">Δωρεάν</a>
</nav>

<section class="sec" aria-labelledby="deals-title">
  <div class="sec-h"><h2 id="deals-title" class="disp h2">Οι πιο <span class="foil">δυνατές</span><br>προσφορές</h2><p>Με σειρά από τη μεγαλύτερη έκπτωση. Ο κωδικός εμφανίζεται στον λογαριασμό σου αμέσως.</p></div>
  <?php if ($deals): ?>
    <div class="cards"><?php foreach ($deals as $s) partial('card', ['s' => $s, 'favs' => $favs]) ?></div>
    <p style="margin-top:22px"><a class="btn ghost" href="/prosfores">Όλες οι προσφορές →</a></p>
  <?php else: ?>
    <div class="empty"><b>Οι πρώτες προσφορές έρχονται</b>Δεν υπάρχουν ενεργές προσφορές ακόμα. Ο διαχειριστής τις προσθέτει από το admin.</div>
  <?php endif ?>
</section>

<?php if ($campaigns): ?>
<section class="sec" aria-labelledby="camp-title">
  <div class="sec-h"><h2 id="camp-title" class="disp h2">Καμπάνιες</h2><p>Θεματικές, χρονικές ή ανά περιοχή.</p></div>
  <div class="luxe">
    <?php foreach ($campaigns as $i => $c): ?>
      <a href="/kampania/<?= e($c['slug']) ?>" class="<?= $c['image'] ? 'has-img' : '' ?>" <?= $c['image'] ? 'style="--img:url(\'' . e($c['image']) . '\')"' : '' ?>>
        <p><?= e(['theme' => 'Θεματική', 'time' => 'Χρονική', 'region' => 'Περιοχή'][$c['kind']] ?? '') ?><?= $c['ends_at'] ? ' · έως ' . e(date_gr($c['ends_at'])) : '' ?></p>
        <div><h3 class="disp"><?= e($c['title']) ?></h3><?php if ($i === 0 && $c['summary']): ?><p><?= e($c['summary']) ?></p><?php endif ?></div>
      </a>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<section class="sec" aria-labelledby="how-title">
  <div class="sec-h"><h2 id="how-title" class="disp h2">Πώς <span class="foil">δουλεύει</span></h2><p><a class="link" href="/pos-leitourgei">Αναλυτικά βήματα</a></p></div>
  <ol class="how">
    <li><b>Καμπάνια</b><span>Διάλεξε θέμα ή περιοχή</span></li>
    <li><b>Κατηγορία</b><span>Spa, γεύση, ομορφιά…</span></li>
    <li><b>Κατάστημα</b><span>Κοντά σου</span></li>
    <li><b>Υπηρεσία</b><span>Πακέτο, διάρκεια, άτομα</span></li>
    <li><b>Κωδικός</b><span>Στον λογαριασμό σου, με QR</span></li>
  </ol>
</section>

<section class="sec">
  <div class="biz-cta">
    <div>
      <p style="font-weight:700;margin:0">Για επιχειρήσεις</p>
      <h2 class="disp">Νέοι πελάτες.<br>Μηδέν χαρτιά.</h2>
      <p style="max-width:46ch;opacity:.92">Δημοσίευσε προσφορές, εξαργύρωσε κωδικούς με το κινητό και δες αναφορές για κάθε κατάστημα.</p>
    </div>
    <a class="btn gold" href="/synergates">Γίνε συνεργάτης →</a>
  </div>
</section>
<script>window.CITY=true;</script>
