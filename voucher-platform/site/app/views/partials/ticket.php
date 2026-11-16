<?php $cls = ['redeemed' => 'is-used', 'expired' => 'is-expired'][$c['status']] ?? ''; ?>
<div class="ticket <?= $cls ?>">
  <div class="a">
    <div class="small muted"><?= e($c['business_name']) ?> · <?= e($c['service_title']) ?><?= !empty($c['variant_label']) ? ' · ' . e($c['variant_label']) : '' ?></div>
    <div class="code"><?= e($c['code']) ?></div>
    <div class="row small"><span class="badge badge-<?= e($c['status']) ?>"><?= e(code_status_label($c['status'])) ?></span>
      <?php if ($c['status'] === 'redeemed'): ?><span class="muted">Εξαργυρώθηκε <?= e(date_gr($c['redeemed_at'], true)) ?></span><?php else: ?><span class="muted">Ισχύει έως <?= e(date_gr($c['expires_at'])) ?></span><?php endif ?></div>
    <?php if ($c['status'] === 'active'): ?><a class="btn sm dark" style="margin-top:12px" href="/logariasmos/kodikos/<?= e($c['code']) ?>">Δείξε στο ταμείο</a><?php endif ?>
  </div>
  <div class="b"><div class="qrbox" data-qr="<?= e(code_redeem_url($c['code'])) ?>" role="img" aria-label="QR κωδικού <?= e($c['code']) ?>"></div></div>
</div>
