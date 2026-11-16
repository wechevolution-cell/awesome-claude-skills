<div class="sec">
  <h1 class="disp h2">Οι κωδικοί μου</h1>
  <?= view('account/_nav', ['tab' => $tab]) ?>
  <nav class="chips" aria-label="Φίλτρο κωδικών">
    <?php foreach (['active' => 'Ενεργοί', 'used' => 'Εξαργυρωμένοι', 'expired' => 'Ληγμένοι'] as $k => $l): ?><a class="chip" href="?f=<?= $k ?>" <?= $filter === $k ? 'aria-current="true"' : '' ?>><?= $l ?></a><?php endforeach ?>
  </nav>
  <?php if ($codes): ?><div class="stack"><?php foreach ($codes as $c) partial('ticket', ['c' => $c]) ?></div>
  <?php else: ?><div class="empty"><b>Κανένας κωδικός εδώ</b><a class="link" href="/prosfores">Δες τις προσφορές</a></div><?php endif ?>
</div>
