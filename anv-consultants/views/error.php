<?php
use function App\{e, services};
/** @var array $page @var int $code */
?>
<section class="hero hero-page" aria-labelledby="h1">
  <canvas class="horizon" data-horizon="still" data-seed="<?= $code === 404 ? 17 : 29 ?>" aria-hidden="true"></canvas>
  <div class="hero-copy wrap"><div class="inner">
    <p class="err-code" aria-hidden="true"><?= (int) $code ?></p>
    <h1 id="h1" class="display" style="font-size:clamp(1.8rem,4vw,2.8rem)"><?= e($page['h1']) ?></h1>
    <p class="lead"><?= e($page['intro']) ?></p>
    <?php if ($code !== 503): ?>
      <div class="btns" style="margin-top:var(--s-5)">
        <a class="btn btn-primary" href="/">Αρχική σελίδα</a>
        <?php foreach (services() as $s): ?><a class="btn btn-soft" href="<?= e($s['path']) ?>"><?= e($s['title']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div></div>
</section>
