<div class="sec" style="padding-bottom:16px">
  <?php partial('crumbs', ['crumbs' => ['Αρχική' => '/', 'Κατηγορίες' => '/katigories']]) ?>
  <h1 class="disp h1" style="margin-top:12px">Κατηγορίες</h1>
</div>
<div class="grid g4">
<?php foreach ($cats as $i => $c): $bg = ['var(--coral)', 'var(--foil)', 'var(--mint)', '#3A1450'][$i % 4]; $fg = in_array($i % 4, [1, 2], true) ? 'var(--ink)' : '#FFF4E6'; ?>
  <a class="camp" href="/katigoria/<?= e($c['slug']) ?>" style="min-height:170px;background:<?= $bg ?>;color:<?= $fg ?>">
    <span class="small" style="opacity:.85"><?= (int)$c['n'] ?> προσφορές</span>
    <h2 class="disp" style="font-size:clamp(28px,3vw,40px)"><?= e($c['name']) ?></h2>
  </a>
<?php endforeach ?>
</div>
