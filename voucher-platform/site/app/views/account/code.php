<div class="sec showcode" data-wakelock>
  <span class="eyebrow"><?= e($c['business_name']) ?></span>
  <h1 class="disp h3" style="margin-top:8px"><?= e($c['service_title']) ?><?= $c['variant_label'] ? ' · ' . e($c['variant_label']) : '' ?></h1>
  <?php if ($c['status'] === 'active'): ?>
    <div class="qrbox" data-qr="<?= e(code_redeem_url($c['code'])) ?>" role="img" aria-label="QR κωδικού"></div>
    <div class="code mono"><?= e($c['code']) ?></div>
    <p class="muted">Δείξε αυτή την οθόνη στο ταμείο. Ισχύει έως <?= e(date_gr($c['expires_at'])) ?>.</p>
  <?php else: ?>
    <div class="alert <?= $c['status'] === 'redeemed' ? 'ok' : 'err' ?>" style="margin:20px 0"><?= e(code_status_label($c['status'])) ?><?= $c['redeemed_at'] ? ' · ' . e(date_gr($c['redeemed_at'], true)) : '' ?></div>
    <div class="code mono" style="opacity:.6"><?= e($c['code']) ?></div>
  <?php endif ?>
  <?php if ($c['terms']): ?><details class="faq" style="text-align:left;margin-top:16px"><summary>Όροι εξαργύρωσης</summary><p><?= nl2br(e($c['terms'])) ?></p></details><?php endif ?>
  <?php if ($stores): ?><details class="faq" style="text-align:left"><summary>Καταστήματα</summary><p><?php foreach ($stores as $st): ?><?= e($st['name']) ?> · <?= e(trim(($st['address'] ?? '') . ', ' . ($st['city'] ?? ''), ', ')) ?><br><?php endforeach ?></p></details><?php endif ?>
  <p style="margin-top:20px"><a class="link" href="/logariasmos/kodikoi">← Όλοι οι κωδικοί</a></p>
</div>
