<?php
use function App\{e, icon};
/** @var array $items [{q,a}] */
if (!$items) { return; }
?>
<div class="faq">
  <?php foreach ($items as $f): ?>
    <details>
      <summary><span><?= e($f['q']) ?></span><?= icon('chevron', 20) ?></summary>
      <div class="answer"><p><?= nl2br(e($f['a'])) ?></p></div>
    </details>
  <?php endforeach; ?>
</div>
