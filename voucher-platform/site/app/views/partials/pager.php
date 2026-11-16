<?php if ($pg['pages'] > 1): ?>
<nav class="pager" aria-label="Σελίδες">
<?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
  <?php if ($i === $pg['page']): ?><span aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e(query_with(['page' => $i > 1 ? $i : null])) ?>"><?= $i ?></a><?php endif ?>
<?php endfor ?>
</nav>
<?php endif ?>
