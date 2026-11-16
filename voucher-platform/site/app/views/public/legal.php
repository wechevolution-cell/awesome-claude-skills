<div class="sec narrow prose">
  <?php partial('crumbs', ['crumbs' => ['Αρχική' => '/', $heading => '/' . $page]]) ?>
  <h1 class="disp h1" style="margin-top:12px"><?= e($heading) ?></h1>
  <div class="alert info" style="margin:16px 0">Πρότυπο κείμενο — έλεγξέ το πριν το live. Πρέπει να το δει νομικός σύμβουλος.</div>
  <?= view('public/legal_' . $page) ?>
</div>
