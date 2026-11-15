<?php
use function App\{e, setting, tel_href, icon};
$phone = (string) setting('phone', '');
$email = (string) setting('email', '');
if ($phone === '' && $email === '') { return; }
?>
<div class="btns">
  <?php if ($phone !== ''): ?><a class="btn btn-primary" href="<?= e(tel_href($phone)) ?>"><?= icon('phone', 18) ?>Καλέστε <?= e($phone) ?></a><?php endif; ?>
  <?php if ($email !== ''): ?><a class="btn btn-soft" href="mailto:<?= e($email) ?>"><?= icon('mail', 18) ?>Στείλτε email</a><?php endif; ?>
</div>
