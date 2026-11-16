<div class="sec">
  <h1 class="disp h2">Γεια σου, <?= e(explode(' ', $u['name'])[0]) ?></h1>
  <?= view('account/_nav', ['tab' => $tab]) ?>
  <div class="stats">
    <div><b class="tnum"><?= $stats['active'] ?></b><span>Ενεργοί κωδικοί</span></div>
    <div><b class="tnum"><?= $stats['redeemed'] ?></b><span>Εξαργυρώσεις</span></div>
    <div><b class="tnum"><?= money_short($stats['saved']) ?></b><span>Εξοικονόμηση</span></div>
    <div><b class="tnum"><?= $stats['favs'] ?></b><span>Αγαπημένα</span></div>
  </div>
  <h2 class="disp h3" style="margin:32px 0 14px">Λήγουν σύντομα</h2>
  <?php if ($active): ?><div class="stack"><?php foreach ($active as $c) partial('ticket', ['c' => $c]) ?></div>
  <?php else: ?><div class="empty"><b>Δεν έχεις ενεργούς κωδικούς</b><a class="link" href="/prosfores">Βρες μια προσφορά</a></div><?php endif ?>
  <form method="post" action="/exodos" style="margin-top:28px"><?= csrf_field() ?><button class="btn ghost sm" type="submit">Αποσύνδεση</button></form>
</div>
