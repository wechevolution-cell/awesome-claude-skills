<div class="sec narrow">
  <?php partial('crumbs', ['crumbs' => ['Αρχική' => '/', 'Συχνές ερωτήσεις' => '/syxnes-erotiseis']]) ?>
  <h1 class="disp h1" style="margin-top:12px">Συχνές ερωτήσεις</h1>
  <div style="margin-top:20px">
  <?php foreach ($faq as [$q, $a]): ?><details class="faq"><summary><h2 style="font:inherit"><?= e($q) ?></h2></summary><p><?= e($a) ?></p></details><?php endforeach ?>
  </div>
  <p style="margin-top:24px" class="muted">Δεν βρήκες απάντηση; <a class="link" href="/epikoinonia">Γράψε μας</a>.</p>
</div>
