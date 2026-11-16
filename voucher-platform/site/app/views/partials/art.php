<?php
/** Placeholder illustration used until a real photo is uploaded. Deterministic per id. */
$palettes = [['#F7D774', '#D98E2B', '#3A1450'], ['#FF4F3A', '#FFF4E6', '#F2C14E'], ['#2BD4A4', '#1E0F2A', '#F2C14E'], ['#2E1A3E', '#F2C14E', '#FF4F3A'], ['#5B2477', '#FFE7A3', '#2BD4A4']];
$p = $palettes[((int)$id) % count($palettes)];
$k = ((int)$id) % 3;
?><svg viewBox="0 0 300 300" role="img" aria-label="<?= e($label ?? 'Εικόνα') ?>" preserveAspectRatio="xMidYMid slice">
<rect width="300" height="300" fill="<?= $p[0] ?>"/>
<?php if ($k === 0): ?><circle cx="210" cy="90" r="120" fill="<?= $p[1] ?>" opacity=".55"/><rect x="70" y="150" width="160" height="110" rx="55" fill="<?= $p[2] ?>"/><circle cx="150" cy="120" r="34" fill="<?= $p[1] ?>"/>
<?php elseif ($k === 1): ?><circle cx="150" cy="160" r="96" fill="<?= $p[1] ?>"/><circle cx="150" cy="160" r="66" fill="<?= $p[2] ?>"/><rect x="58" y="60" width="8" height="190" rx="4" fill="#1E0F2A"/><rect x="234" y="60" width="8" height="190" rx="4" fill="#1E0F2A"/>
<?php else: ?><path d="M150 50 C110 100 96 130 96 160 a54 54 0 0 0 108 0 C204 130 190 100 150 50Z" fill="<?= $p[1] ?>"/><circle cx="150" cy="164" r="22" fill="<?= $p[2] ?>"/><path d="M0 250 Q150 210 300 250 V300 H0Z" fill="<?= $p[2] ?>" opacity=".5"/>
<?php endif ?>
</svg>
