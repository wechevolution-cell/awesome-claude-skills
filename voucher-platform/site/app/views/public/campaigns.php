<div class="sec" style="padding-bottom:16px">
  <?php partial('crumbs', ['crumbs' => ['Αρχική' => '/', 'Καμπάνιες' => '/kampanies']]) ?>
  <h1 class="disp h1" style="margin-top:12px">Καμπάνιες</h1>
  <p class="lead">Θεματικές, χρονικές ή ανά περιοχή. Κάθε καμπάνια μαζεύει προσφορές από πολλά καταστήματα.</p>
</div>
<?php if ($items): ?>
<div class="camp-list">
<?php foreach ($items as $c): ?>
  <a class="camp" href="/kampania/<?= e($c['slug']) ?>" <?= $c['image'] ? 'style="background-image:linear-gradient(180deg,rgba(20,10,30,.15),rgba(20,10,30,.85)),url(\'' . e($c['image']) . '\')"' : '' ?>>
    <span class="eyebrow" style="color:var(--gold)"><?= e(['theme' => 'Θεματική', 'time' => 'Χρονική', 'region' => 'Περιοχή'][$c['kind']] ?? '') ?><?= $c['ends_at'] ? ' · έως ' . e(date_gr($c['ends_at'])) : '' ?></span>
    <h2 class="disp"><?= e($c['title']) ?></h2>
    <?php if ($c['summary']): ?><p style="margin:6px 0 0;opacity:.9"><?= e($c['summary']) ?></p><?php endif ?>
  </a>
<?php endforeach ?>
</div>
<?php else: ?><div class="empty"><b>Καμία ενεργή καμπάνια</b>Δες <a class="link" href="/prosfores">όλες τις προσφορές</a>.</div><?php endif ?>
