<?php
$block = function (string $type, string $title, array $rows, callable $line) { ?>
  <div class="panel" style="margin-bottom:16px"><h2><?= e($title) ?> (<?= count($rows) ?>)</h2>
  <?php if (!$rows): ?><p class="muted">Τίποτα σε αναμονή.</p><?php endif ?>
  <?php foreach ($rows as $r): ?>
    <div style="border-top:1px solid var(--line);padding:12px 0">
      <?= $line($r) ?>
      <form method="post" action="/admin/approve" class="row" style="margin-top:8px"><?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <label class="sr" for="n-<?= $type . $r['id'] ?>">Σημείωση</label><input class="input" id="n-<?= $type . $r['id'] ?>" name="note" placeholder="Σημείωση (προαιρετικά)" style="max-width:320px;min-height:40px">
        <button class="btn sm gold" name="decision" value="approve">Έγκριση</button><button class="btn sm ghost" name="decision" value="reject">Απόρριψη</button>
      </form>
    </div>
  <?php endforeach ?></div>
<?php };
$block('business', 'Επιχειρήσεις', $businesses, fn($r) => '<a class="link" href="/admin/c/businesses/' . (int)$r['id'] . '"><b>' . e($r['name']) . '</b></a> · ΑΦΜ ' . e($r['vat_number'] ?: '—') . ' · ' . e($r['email']) . ($r['agent'] ? ' · Αντιπρόσωπος: ' . e($r['agent']) : '') . ($r['referred_by'] ? ' · ref ' . e($r['referred_by']) : ''));
$block('service', 'Προσφορές', $services, fn($r) => '<a class="link" href="/admin/c/services/' . (int)$r['id'] . '"><b>' . e($r['title']) . '</b></a> · ' . e($r['business']) . ' · ' . money((int)$r['price_cents']) . ($r['regular_cents'] ? ' (από ' . money((int)$r['regular_cents']) . ')' : ''));
$block('campaign', 'Καμπάνιες', $campaigns, fn($r) => '<a class="link" href="/admin/c/campaigns/' . (int)$r['id'] . '"><b>' . e($r['title']) . '</b></a> · από ' . e(['business' => 'επιχείρηση', 'agent' => 'αντιπρόσωπο', 'platform' => 'πλατφόρμα'][$r['owner_type']] ?? '') . ' · ' . e(date_gr($r['starts_at'])) . ' – ' . e(date_gr($r['ends_at'])));
