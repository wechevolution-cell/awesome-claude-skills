<div class="sec">
  <h1 class="disp h2">Αγαπημένα</h1>
  <?= view('account/_nav', ['tab' => $tab]) ?>
  <?php if ($items): ?><div class="cards"><?php foreach ($items as $s) partial('card', ['s' => $s, 'favs' => $favs]) ?></div>
  <?php else: ?><div class="empty"><b>Δεν έχεις αγαπημένα ακόμα</b>Πάτησε την καρδιά σε μια προσφορά για να την κρατήσεις εδώ.</div><?php endif ?>
</div>
