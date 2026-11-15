<?php
use function App\e;
/** @var array $steps */
if (!$steps) { return; }
?>
<ol class="steps">
  <?php foreach ($steps as $s): ?>
    <li><b><?= e($s['name']) ?></b><?php if (trim((string) ($s['text'] ?? '')) !== ''): ?><span><?= e($s['text']) ?></span><?php endif; ?></li>
  <?php endforeach; ?>
</ol>
