<?php use function App\{e, csrf_field}; ?>
<div class="auth-card">
  <h1>Σύνδεση</h1>
  <?php if ($err): ?><div class="flash err" role="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Email<input name="email" type="email" required autocomplete="username" value="<?= e((string) ($_POST['email'] ?? '')) ?>"></label>
    <label>Κωδικός<input name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit">Σύνδεση</button>
  </form>
  <p class="muted small">Μετά από 5 αποτυχημένες προσπάθειες η σύνδεση κλειδώνει για 15 λεπτά.</p>
</div>
