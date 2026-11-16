<nav class="tabs" aria-label="Λογαριασμός">
<?php foreach (['home' => ['/logariasmos', 'Επισκόπηση'], 'codes' => ['/logariasmos/kodikoi', 'Κωδικοί'], 'favs' => ['/logariasmos/agapimena', 'Αγαπημένα'], 'orders' => ['/logariasmos/paraggelies', 'Παραγγελίες'], 'profile' => ['/logariasmos/stoixeia', 'Στοιχεία']] as $k => [$h, $l]): ?>
  <a href="<?= $h ?>" <?= ($tab ?? '') === $k ? 'aria-current="page"' : '' ?>><?= $l ?></a>
<?php endforeach ?>
</nav>
