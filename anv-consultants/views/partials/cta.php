<?php
use function App\{e, setting, tel_href, icon};
$phone = (string) setting('phone', '');
$email = (string) setting('email', '');
?>
<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="cta-band">
      <div class="stack">
        <h2>Μιλήστε μαζί μας</h2>
        <p>Καλέστε μας ή στείλτε μας email για τη δική σας περίπτωση.</p>
      </div>
      <div class="btns">
        <?php if ($phone !== ''): ?><a class="btn btn-primary" href="<?= e(tel_href($phone)) ?>"><?= icon('phone', 18) ?><?= e($phone) ?></a><?php endif; ?>
        <?php if ($email !== ''): ?><a class="btn btn-ghost" href="mailto:<?= e($email) ?>"><?= icon('mail', 18) ?>Email</a><?php endif; ?>
        <?php if ($phone === '' && $email === ''): ?><a class="btn btn-primary" href="/epikoinonia/">Επικοινωνία</a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
