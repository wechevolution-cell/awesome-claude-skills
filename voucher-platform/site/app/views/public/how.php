<div class="sec narrow">
  <?php partial('crumbs', ['crumbs' => ['Αρχική' => '/', 'Πώς λειτουργεί' => '/pos-leitourgei']]) ?>
  <h1 class="disp h1" style="margin-top:12px">Πώς <span class="foil">λειτουργεί</span></h1>
  <p class="lead"><strong>Σε μία πρόταση:</strong> διαλέγεις υπηρεσία σε προσφορά, παίρνεις μοναδικό κωδικό με QR στον λογαριασμό σου και τον δείχνεις στο ταμείο του καταστήματος.</p>
  <ol class="how" style="grid-template-columns:1fr;margin-top:28px">
    <?php foreach ($steps as [$t, $d]): ?><li><b><?= e($t) ?></b><span style="font-size:15px"><?= e($d) ?></span></li><?php endforeach ?>
  </ol>
  <div class="prose" style="margin-top:32px">
    <h2>Βασικά σημεία</h2>
    <ul>
      <li>Κάθε κωδικός είναι <strong>μίας χρήσης</strong> και κλειδώνει τη στιγμή της εξαργύρωσης.</li>
      <li>Κάθε κωδικός έχει <strong>ημερομηνία λήξης</strong>, που φαίνεται δίπλα του.</li>
      <li>Υπάρχουν <strong>δωρεάν</strong> και <strong>επί πληρωμή</strong> κωδικοί. Οι δωρεάν εκδίδονται αμέσως.</li>
      <li>Κριτική γράφουν μόνο όσοι εξαργύρωσαν κωδικό.</li>
    </ul>
  </div>
  <p style="margin-top:24px"><a class="btn gold" href="/prosfores">Δες τις προσφορές →</a></p>
</div>
