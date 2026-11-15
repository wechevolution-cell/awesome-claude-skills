<?php use function App\{e, csrf_field}; ?>
<h1>Πίνακας ελέγχου</h1>
<div class="kpis">
  <a class="kpi" href="/admin/pages"><b><?= $kpi['published'] ?></b><span>Δημοσιευμένες</span></a>
  <a class="kpi" href="/admin/pages"><b><?= $kpi['drafts'] ?></b><span>Πρόχειρα</span></a>
  <a class="kpi" href="/admin/pages"><b><?= $kpi['scheduled'] ?></b><span>Προγραμματισμένες</span></a>
  <div class="kpi"><b class="score s<?= (int) floor($kpi['seo'] / 20) ?>"><?= $kpi['seo'] ?></b><span>Μέσο SEO score</span></div>
  <div class="kpi"><b class="score s<?= (int) floor($kpi['aeo'] / 20) ?>"><?= $kpi['aeo'] ?></b><span>Μέσο AEO score</span></div>
  <a class="kpi<?= $kpi['placeholders'] ? ' warn' : '' ?>" href="#placeholders"><b><?= $kpi['placeholders'] ?></b><span>Με πρότυπο κείμενο</span></a>
  <a class="kpi" href="/admin/404"><b><?= $kpi['404'] ?></b><span>404 προς έλεγχο</span></a>
  <a class="kpi" href="/admin/redirects"><b><?= $kpi['redirects'] ?></b><span>Ενεργές ανακατευθύνσεις</span></a>
  <div class="kpi muted-kpi"><b>—</b><span>Μηνύματα: χωρίς φόρμα (κατ' επιλογή)</span></div>
</div>

<div class="cols">
  <section class="card">
    <h2>Πριν το live</h2>
    <ul class="checks">
      <?php foreach ($checks as $c): ?>
        <li class="<?= $c['ok'] ? 'ok' : 'bad' ?>"><span class="dot" aria-hidden="true"></span><div><b><?= e($c['label']) ?></b><?php if (!$c['ok']): ?><br><a href="<?= e($c['link']) ?>"><?= e($c['fix']) ?></a><?php endif; ?></div><span class="sr-only"><?= $c['ok'] ? 'εντάξει' : 'εκκρεμεί' ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="card">
    <h2>Αρχεία για μηχανές αναζήτησης</h2>
    <ul class="files">
      <li><a href="/sitemap.xml" target="_blank">sitemap.xml</a> <span class="pill ok">αυτόματο</span></li>
      <li><a href="/robots.txt" target="_blank">robots.txt</a> <span class="pill ok">αυτόματο</span></li>
      <li><a href="/llms.txt" target="_blank">llms.txt</a> <span class="pill ok">αυτόματο</span></li>
      <li><a href="/feed.xml" target="_blank">feed.xml (RSS)</a> <span class="pill ok">αυτόματο</span></li>
    </ul>
    <h2 id="backup" style="margin-top:24px">Backup</h2>
    <p class="muted small">Τελευταίο backup: <?= $lb !== '' ? e(date('d/m/Y H:i', strtotime($lb))) : 'ποτέ' ?>. Περιλαμβάνει τη βάση δεδομένων και τις εικόνες.</p>
    <form method="post" action="/admin/backup"><?= csrf_field() ?><button class="btn" type="submit">Λήψη backup (.zip)</button></form>
  </section>
</div>

<div class="cols">
  <section class="card">
    <h2>Αδύναμες σελίδες (SEO ή AEO κάτω από 60)</h2>
    <?php if (!$weak): ?><p class="muted">Καμία.</p><?php else: ?>
    <table class="tbl"><thead><tr><th>Σελίδα</th><th>SEO</th><th>AEO</th></tr></thead><tbody>
      <?php foreach ($weak as $w): ?><tr><td><a href="/admin/pages/edit?id=<?= (int) $w['id'] ?>"><?= e($w['title']) ?></a><br><span class="muted small"><?= e($w['path']) ?></span></td><td><span class="score s<?= (int) floor($w['seo_score'] / 20) ?>"><?= (int) $w['seo_score'] ?></span></td><td><span class="score s<?= (int) floor($w['aeo_score'] / 20) ?>"><?= (int) $w['aeo_score'] ?></span></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </section>
  <section class="card">
    <h2>Χωρίς meta description ή λέξη-κλειδί</h2>
    <?php if (!$missingMeta): ?><p class="muted">Καμία.</p><?php else: ?>
    <ul class="plain"><?php foreach ($missingMeta as $m): ?><li><a href="/admin/pages/edit?id=<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a> <span class="muted small"><?= e($m['path']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </section>
</div>

<section class="card" id="placeholders">
  <h2>Σελίδες με πρότυπο κείμενο (<?= count($placeholders) ?>)</h2>
  <p class="muted small">Αντικαταστήστε το κείμενο και ξετσεκάρετε «Περιέχει πρότυπο κείμενο» στη σελίδα.</p>
  <ul class="plain cols3"><?php foreach ($placeholders as $m): ?><li><a href="/admin/pages/edit?id=<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a></li><?php endforeach; ?></ul>
</section>
