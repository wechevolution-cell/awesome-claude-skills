<?php if ($rows): foreach ($rows as $m): ?>
<div class="panel" style="margin-bottom:12px"><div class="row between"><b><?= e($m['name']) ?> · <?= e($m['email']) ?><?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?></b><span class="small muted"><?= e(date_gr($m['created_at'], true)) ?><?= !$m['is_read'] ? ' · <span class="badge badge-pending">νέο</span>' : '' ?></span></div><p style="margin:8px 0 0;white-space:pre-wrap"><?= e($m['body']) ?></p></div>
<?php endforeach; else: ?><div class="empty"><b>Κανένα μήνυμα</b></div><?php endif ?>
