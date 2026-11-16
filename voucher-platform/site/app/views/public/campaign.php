<div class="sec" style="padding-bottom:16px">
  <?php partial('crumbs', ['crumbs' => $crumbs]) ?>
  <div class="camp" style="margin-top:16px;min-height:300px<?= $c['image'] ? ';background-image:linear-gradient(180deg,rgba(20,10,30,.15),rgba(20,10,30,.85)),url(\'' . e($c['image']) . '\')' : '' ?>">
    <span class="eyebrow" style="color:var(--gold)"><?= e(['theme' => 'Θεματική καμπάνια', 'time' => 'Χρονική καμπάνια', 'region' => 'Καμπάνια περιοχής'][$c['kind']] ?? '') ?><?= $c['ends_at'] ? ' · ισχύει έως ' . e(date_gr($c['ends_at'])) : '' ?></span>
    <h1 class="disp" style="font-size:clamp(44px,7vw,96px)"><?= e($c['title']) ?></h1>
    <?php if ($c['summary']): ?><p style="margin:8px 0 0;max-width:52ch;font-size:18px"><?= e($c['summary']) ?></p><?php endif ?>
  </div>
  <?php if ($c['description']): ?><div class="prose" style="margin-top:20px"><?= nl2p($c['description']) ?></div><?php endif ?>
</div>
<?php if ($items): ?><div class="cards"><?php foreach ($items as $s) partial('card', ['s' => $s, 'favs' => $favs]) ?></div>
<?php else: ?><div class="empty"><b>Οι προσφορές της καμπάνιας έρχονται σύντομα</b><a class="link" href="/prosfores">Δες όλες τις προσφορές</a></div><?php endif ?>
