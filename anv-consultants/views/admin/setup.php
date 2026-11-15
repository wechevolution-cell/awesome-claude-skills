<?php use function App\{e, csrf_field}; ?>
<div class="auth-card">
  <h1>Πρώτη ρύθμιση</h1>
  <p class="muted">Δημιουργήστε τον λογαριασμό διαχειριστή. Δεν υπάρχουν προεπιλεγμένοι κωδικοί.</p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Όνομα<input name="name" autocomplete="name"></label>
    <label>Email<input name="email" type="email" required autocomplete="username" value="<?= e((string) ($_POST['email'] ?? '')) ?>"></label>
    <label>Κωδικός (τουλάχιστον 12 χαρακτήρες)<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
    <label>Επιβεβαίωση κωδικού<input name="password2" type="password" required minlength="12" autocomplete="new-password"></label>
    <button class="btn" type="submit">Δημιουργία λογαριασμού</button>
  </form>
</div>
