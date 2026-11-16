<div class="sec narrow">
  <div class="alert info">Δοκιμαστική πληρωμή: δεν γίνεται καμία χρέωση. Στο live εδώ ανοίγει η σελίδα του παρόχου πληρωμών.</div>
  <div class="panel" style="margin-top:20px">
    <span class="eyebrow">Παραγγελία <?= e($o['number']) ?></span>
    <h1 class="disp h2" style="margin:8px 0">Πληρωμή <?= money((int)$o['total_cents']) ?></h1>
    <form method="post" class="row" style="margin-top:16px"><?= csrf_field() ?>
      <button class="btn gold" name="result" value="approve">Επιτυχής πληρωμή</button>
      <button class="btn ghost" name="result" value="decline">Απόρριψη</button>
    </form>
  </div>
</div>
