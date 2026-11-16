<?php
$msg = $result ? redeem_message($result) : null;
$row = $result['row'] ?? null;
?>
<div class="scan" data-scanner>
  <div class="stack">
    <div class="cam" data-cam hidden><video playsinline muted></video><span class="hint">Στόχευσε το QR μέσα στο πλαίσιο</span></div>
    <button class="btn dark" type="button" data-cam-start>Άνοιγμα κάμερας για σάρωση QR</button>
    <p class="small muted" data-cam-note>Η κάμερα ανοίγει μόνο όταν το πατήσεις. Αν δεν λειτουργεί, γράψε τον κωδικό στο διπλανό πεδίο.</p>
  </div>
  <div class="stack">
    <form class="form panel" method="post" action="/business/redeem" data-redeem-form>
      <?= csrf_field() ?>
      <div class="field"><label for="code">Κωδικός πελάτη</label><input class="codein" id="code" name="code" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="CD-XXXX-XXXX" value="<?= e($result['code'] ?? '') ?>" required></div>
      <?php if (count($stores) > 1): ?>
      <div class="field"><label for="store">Κατάστημα</label><select id="store" name="store_id"><?php foreach ($stores as $st): ?><option value="<?= (int)$st['id'] ?>" <?= (int)($_SESSION['last_store'] ?? $b['member_store'] ?? 0) === (int)$st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?><?= $st['city'] ? ' · ' . e($st['city']) : '' ?></option><?php endforeach ?></select></div>
      <?php endif ?>
      <div class="row"><button class="btn ghost" type="button" data-check>Έλεγχος</button><button class="btn gold" type="submit">Επιβεβαίωση εξαργύρωσης</button></div>
    </form>
    <div class="result <?= $msg ? e($msg[0]) : '' ?>" data-result <?= $msg ? '' : 'hidden' ?> role="status" aria-live="polite">
      <h2 data-r-title><?= $msg ? e(['ok' => 'Εξαργυρώθηκε', 'err' => 'Δεν εξαργυρώνεται', 'info' => 'Έγκυρος κωδικός'][$msg[0]]) : '' ?></h2>
      <p data-r-msg style="margin:0 0 8px"><?= $msg ? e($msg[1]) : '' ?></p>
      <div data-r-details class="small"><?php if ($row): ?><?= e($row['service_title']) ?><?= $row['variant_label'] ? ' · ' . e($row['variant_label']) : '' ?><br>Πελάτης: <?= e($row['customer_name']) ?> · Λήξη: <?= e(date_gr($row['expires_at'])) ?><?php endif ?></div>
    </div>
  </div>
</div>
