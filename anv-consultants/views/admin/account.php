<?php use function App\{e, csrf_field}; ?>
<h1>Λογαριασμός</h1>
<p class="muted">Συνδεδεμένος ως <?= e((string) ($_SESSION['admin_email'] ?? '')) ?></p>
<form method="post" class="form card" style="max-width:480px">
  <?= csrf_field() ?>
  <h2>Αλλαγή κωδικού</h2>
  <label>Τρέχων κωδικός<input name="current" type="password" required autocomplete="current-password"></label>
  <label>Νέος κωδικός (12+ χαρακτήρες)<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
  <label>Επιβεβαίωση<input name="password2" type="password" required minlength="12" autocomplete="new-password"></label>
  <button class="btn" type="submit">Αλλαγή κωδικού</button>
</form>
