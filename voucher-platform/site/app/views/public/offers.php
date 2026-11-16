<?php $cur = (string)input('k'); ?>
<div class="sec" style="padding-bottom:0">
  <?php if (!empty($crumbs)) partial('crumbs', ['crumbs' => $crumbs]) ?>
  <h1 class="disp h1" style="margin-top:12px"><?= e($heading) ?></h1>
  <?php if (!empty($intro)): ?><div class="lead" style="margin-top:12px"><?= nl2p($intro) ?></div><?php endif ?>
</div>

<?php if (!empty($subs)): ?>
<nav class="chips" aria-label="Υποκατηγορίες" style="margin-top:20px">
  <?php foreach ($subs as $c): ?><a class="chip" href="/katigoria/<?= e($c['slug']) ?>"><?= e($c['name']) ?></a><?php endforeach ?>
</nav>
<?php endif ?>

<form class="filters" method="get" style="margin-top:20px" data-autosubmit>
  <?php if (empty($hide_cat_filter)): ?>
  <label class="sr" for="f-k">Κατηγορία</label>
  <select id="f-k" name="k"><option value="">Όλες οι κατηγορίες</option><?php foreach (nav_categories() as $c): ?><option value="<?= e($c['slug']) ?>" <?= $cur === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select>
  <?php endif ?>
  <label class="sr" for="f-t">Τύπος κωδικού</label>
  <select id="f-t" name="t"><option value="">Δωρεάν και επί πληρωμή</option><option value="free" <?= input('t') === 'free' ? 'selected' : '' ?>>Μόνο δωρεάν</option><option value="paid" <?= input('t') === 'paid' ? 'selected' : '' ?>>Μόνο επί πληρωμή</option></select>
  <label class="sr" for="f-s">Ταξινόμηση</label>
  <select id="f-s" name="sort"><?php foreach (['featured' => 'Προτεινόμενες', 'new' => 'Νεότερες', 'price' => 'Χαμηλότερη τιμή', 'ending' => 'Λίγοι κωδικοί'] as $k => $v): ?><option value="<?= $k ?>" <?= input('sort', 'featured') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach ?></select>
  <label class="sr" for="f-p">Περιοχή</label>
  <input class="input" id="f-p" name="perioxi" value="<?= e((string)input('perioxi')) ?>" placeholder="Περιοχή ή ΤΚ" style="max-width:200px;border-radius:999px;min-height:44px;padding:8px 14px">
  <?php if (input('q') !== ''): ?><input type="hidden" name="q" value="<?= e((string)input('q')) ?>"><?php endif ?>
  <button class="btn sm dark" type="submit">Εφαρμογή</button>
  <span class="small muted tnum"><?= (int)$pg['total'] ?> προσφορές</span>
</form>

<?php if ($items): ?>
  <div class="cards"><?php foreach ($items as $s) partial('card', ['s' => $s, 'favs' => $favs]) ?></div>
  <?php partial('pager', ['pg' => $pg]) ?>
<?php else: ?>
  <div class="empty"><b>Δεν βρέθηκαν προσφορές</b>Δοκίμασε άλλη περιοχή ή κατηγορία. <a class="link" href="/prosfores">Δες όλες τις προσφορές</a></div>
<?php endif ?>
