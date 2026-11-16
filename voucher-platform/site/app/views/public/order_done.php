<div class="sec narrow">
  <?php if (in_array($o['status'], ['paid', 'free'], true)): ?>
    <span class="eyebrow">Παραγγελία <?= e($o['number']) ?></span>
    <h1 class="disp h1" style="margin-top:8px">Οι κωδικοί σου <span class="foil">είναι έτοιμοι</span></h1>
    <p class="lead">Τους βρίσκεις πάντα στο «Οι κωδικοί μου». Στείλαμε αντίγραφο στο <?= e($o['customer_email']) ?>.</p>
    <div class="stack" style="margin-top:24px">
    <?php foreach ($codes as $c) partial('ticket', ['c' => $c]) ?>
    </div>
    <p style="margin-top:24px"><a class="btn gold" href="/logariasmos/kodikoi">Οι κωδικοί μου →</a></p>
  <?php elseif ($o['status'] === 'pending'): ?>
    <h1 class="disp h2">Η πληρωμή εκκρεμεί</h1>
    <p class="lead">Η παραγγελία <?= e($o['number']) ?> περιμένει επιβεβαίωση πληρωμής.</p>
    <?php if (test_payments_allowed()): ?><a class="btn" href="/pay/test/<?= e($o['number']) ?>">Συνέχεια πληρωμής</a><?php endif ?>
  <?php else: ?>
    <h1 class="disp h2">Η πληρωμή δεν ολοκληρώθηκε</h1>
    <p class="lead">Δεν έγινε χρέωση. Πρόσθεσε ξανά τις προσφορές στο καλάθι για να δοκιμάσεις πάλι.</p>
    <ul><?php foreach ($items as $it): ?><li><?= e($it['title']) ?> ×<?= (int)$it['qty'] ?></li><?php endforeach ?></ul>
    <a class="btn" href="/prosfores">Πίσω στις προσφορές</a>
  <?php endif ?>
</div>
