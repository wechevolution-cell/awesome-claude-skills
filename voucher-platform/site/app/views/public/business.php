<div class="sec" style="padding-bottom:16px">
  <?php partial('crumbs', ['crumbs' => $crumbs]) ?>
  <div class="row" style="margin-top:16px;align-items:center;gap:18px">
    <?php if ($b['logo']): ?><img src="<?= e($b['logo']) ?>" alt="Λογότυπο <?= e($b['name']) ?>" width="88" height="88" style="border-radius:20px;width:88px;height:88px;object-fit:cover"><?php endif ?>
    <h1 class="disp h1"><?= e($b['name']) ?></h1>
  </div>
  <?php if ($b['description']): ?><div class="lead" style="margin-top:12px"><?= nl2p($b['description']) ?></div><?php endif ?>
  <?php if ($stores): ?>
  <div class="grid g3" style="margin-top:20px">
    <?php foreach ($stores as $st): ?>
      <div class="panel"><b><?= e($st['name']) ?></b><div class="small muted"><?= e(trim(($st['address'] ?? '') . ', ' . ($st['city'] ?? ''), ', ')) ?></div><?php if ($st['phone']): ?><div class="small">Τηλ. <?= e($st['phone']) ?></div><?php endif ?><?php if ($st['hours']): ?><div class="small muted" style="margin-top:6px"><?= nl2br(e($st['hours'])) ?></div><?php endif ?></div>
    <?php endforeach ?>
  </div>
  <?php endif ?>
</div>
<h2 class="disp h3" style="margin-bottom:16px">Προσφορές</h2>
<?php if ($items): ?><div class="cards"><?php foreach ($items as $s) partial('card', ['s' => $s, 'favs' => $favs]) ?></div>
<?php else: ?><div class="empty"><b>Καμία ενεργή προσφορά</b>Δες ξανά σύντομα.</div><?php endif ?>
