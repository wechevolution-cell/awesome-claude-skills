<?php
use function App\{e, csrf_field};
use const App\{PAGE_TYPES, SCHEMA_TYPES};
/** @var array $p @var array $errors @var array $parents @var array $media @var array $analysis @var ?array $existing */
$faq = json_decode($p['faq_json'] ?: '[]', true) ?: [];
$how = json_decode($p['howto_json'] ?: '[]', true) ?: [];
$faq[] = ['q' => '', 'a' => ''];
$how[] = ['name' => '', 'text' => ''];
$isHome = $p['type'] === 'home';
$pubAt = $p['publish_at'] ? date('Y-m-d\TH:i', strtotime((string) $p['publish_at'])) : '';
?>
<div class="head-row">
  <h1><?= $existing ? 'Επεξεργασία' : 'Νέα σελίδα' ?></h1>
  <div class="actions">
    <?php if ($existing): ?><a class="btn ghost" href="<?= e($existing['path']) ?>" target="_blank">Προβολή ↗</a><?php endif; ?>
    <?php if ($existing && !$isHome): ?><a class="btn ghost danger-text" href="/admin/pages/delete?id=<?= (int) $existing['id'] ?>">Διαγραφή…</a><?php endif; ?>
  </div>
</div>
<?php if ($errors): ?><div class="flash err" role="alert"><?= implode('<br>', array_map(fn($x) => e($x), $errors)) ?></div><?php endif; ?>

<form method="post" class="editor" id="editor" data-analyze="/admin/api/analyze">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
  <div class="ed-main">
    <section class="card form">
      <label>Τίτλος σελίδας<input name="title" value="<?= e($p['title']) ?>" required data-slug-source></label>
      <div class="row2">
        <label>Slug<span class="slugbox"><span class="muted" id="path-prefix"></span><input name="slug" value="<?= e($p['slug']) ?>" <?= $isHome ? 'disabled' : '' ?> pattern="[a-z0-9\-]*" data-slug></span>
          <small class="muted">Διεύθυνση: <code id="path-preview"><?= e($p['path'] ?: '—') ?></code><?php if ($existing && !$isHome): ?> · αν αλλάξει, δημιουργείται αυτόματα 301.<?php endif; ?></small></label>
        <label>Ετικέτα μενού<input name="nav_label" value="<?= e($p['nav_label']) ?>" placeholder="Αν διαφέρει από τον τίτλο"></label>
      </div>
      <label>Κύριος τίτλος (H1)<input name="h1" value="<?= e($p['h1']) ?>" placeholder="Αν διαφέρει από τον τίτλο"></label>
      <label>Εισαγωγή: άμεση απάντηση σε 1–2 προτάσεις<textarea name="intro" rows="3"><?= e($p['intro']) ?></textarea></label>
      <label>TL;DR (προαιρετικό, αν διαφέρει από την εισαγωγή)<textarea name="tldr" rows="2"><?= e($p['tldr']) ?></textarea></label>
      <label>Βασικά σημεία (ένα ανά γραμμή)<textarea name="key_points" rows="4"><?= e($p['key_points']) ?></textarea></label>
      <label>Κείμενο (markdown)<textarea name="body_md" rows="18" class="mono"><?= e($p['body_md']) ?></textarea>
        <small class="muted">## Υπότιτλος · - λίστα · **έντονα** · [σύνδεσμος](/logistika/) · ![alt](/uploads/…) · πίνακες με | · iframe μόνο YouTube, Vimeo, Google Maps</small></label>
    </section>

    <section class="card">
      <h2>Συχνές ερωτήσεις (FAQ)</h2>
      <div class="repeater" data-repeater="faq">
        <?php foreach ($faq as $f): ?>
          <div class="rep-item">
            <label>Ερώτηση<input name="faq_q[]" value="<?= e((string) ($f['q'] ?? '')) ?>"></label>
            <label>Απάντηση<textarea name="faq_a[]" rows="2"><?= e((string) ($f['a'] ?? '')) ?></textarea></label>
            <button type="button" class="linkbtn" data-remove>Αφαίρεση</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn ghost" data-add="faq">+ Ερώτηση</button>
    </section>

    <section class="card">
      <h2>Βήματα (HowTo)</h2>
      <label>Τίτλος ενότητας βημάτων<input name="howto_title" value="<?= e($p['howto_title']) ?>" placeholder="π.χ. Πώς γίνεται η αλλαγή παρόχου"></label>
      <div class="repeater" data-repeater="howto">
        <?php foreach ($how as $s): ?>
          <div class="rep-item">
            <label>Βήμα<input name="howto_name[]" value="<?= e((string) ($s['name'] ?? '')) ?>"></label>
            <label>Περιγραφή<input name="howto_text[]" value="<?= e((string) ($s['text'] ?? '')) ?>"></label>
            <button type="button" class="linkbtn" data-remove>Αφαίρεση</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn ghost" data-add="howto">+ Βήμα</button>
    </section>

    <section class="card form">
      <h2>SEO</h2>
      <label>Λέξη-κλειδί (focus keyword)<input name="focus_kw" value="<?= e($p['focus_kw']) ?>"></label>
      <div id="cannibal" class="cannibal" aria-live="polite"></div>
      <label>Δευτερεύουσες λέξεις-κλειδιά (με κόμμα)<input name="secondary_kws" value="<?= e($p['secondary_kws']) ?>"></label>
      <label>Τίτλος SEO <small class="muted counter" data-count="meta_title" data-min="30" data-max="60"></small><input name="meta_title" value="<?= e($p['meta_title']) ?>" placeholder="Αν μείνει κενό: Τίτλος · Επωνυμία"></label>
      <label>Meta description <small class="muted counter" data-count="meta_desc" data-min="120" data-max="160"></small><textarea name="meta_desc" rows="3"><?= e($p['meta_desc']) ?></textarea></label>
      <div class="serp" aria-label="Προεπισκόπηση Google"><div class="serp-url" id="serp-url"></div><div class="serp-title" id="serp-title"></div><div class="serp-desc" id="serp-desc"></div></div>
      <label>Πηγές (μία ανά γραμμή: Τίτλος | https://…)<textarea name="sources" rows="3"><?= e($p['sources']) ?></textarea></label>
      <label>Συντάκτης / υπεύθυνος περιεχομένου<input name="author" value="<?= e($p['author']) ?>"></label>
      <div class="row2">
        <label>Τύπος schema<select name="schema_type"><?php foreach (SCHEMA_TYPES as $t): ?><option<?= $p['schema_type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
        <label class="check" style="align-self:end"><input type="checkbox" name="noindex" value="1"<?= $p['noindex'] ? ' checked' : '' ?>> noindex (να μην εμφανίζεται στη Google)</label>
      </div>
      <label>Custom JSON-LD (προαιρετικό, προστίθεται στο @graph)<textarea name="custom_jsonld" rows="4" class="mono" placeholder='{"@type":"…"}'><?= e($p['custom_jsonld']) ?></textarea></label>
    </section>
  </div>

  <aside class="ed-side">
    <section class="card form sticky">
      <div class="row2">
        <label>Κατάσταση<select name="status" data-status>
          <option value="published"<?= $p['status'] === 'published' ? ' selected' : '' ?>>Δημοσιευμένη</option>
          <option value="draft"<?= $p['status'] === 'draft' ? ' selected' : '' ?>>Πρόχειρο</option>
          <option value="scheduled"<?= $p['status'] === 'scheduled' ? ' selected' : '' ?>>Προγραμματισμένη</option>
        </select></label>
        <label data-when>Δημοσίευση στις<input type="datetime-local" name="publish_at" value="<?= e($pubAt) ?>"></label>
      </div>
      <label class="check"><input type="checkbox" name="is_placeholder" value="1"<?= $p['is_placeholder'] ? ' checked' : '' ?>> Περιέχει πρότυπο κείμενο</label>
      <button class="btn wide" type="submit">Αποθήκευση</button>

      <div class="scores" aria-live="polite">
        <div><span class="score big s<?= (int) floor($analysis['seo'] / 20) ?>" id="seo-score"><?= (int) $analysis['seo'] ?></span><small>SEO</small></div>
        <div><span class="score big s<?= (int) floor($analysis['aeo'] / 20) ?>" id="aeo-score"><?= (int) $analysis['aeo'] ?></span><small>AEO</small></div>
      </div>
      <details open><summary>Έλεγχοι</summary><ul class="checks small" id="checks">
        <?php foreach ($analysis['checks'] as $c): ?><li class="<?= e($c['level']) ?>"><span class="dot" aria-hidden="true"></span><div><b><?= e($c['label']) ?></b><?php if (!$c['ok']): ?><br><span class="muted"><?= e($c['hint']) ?></span><?php endif; ?></div></li><?php endforeach; ?>
      </ul></details>
    </section>

    <section class="card form">
      <h2>Θέση</h2>
      <label>Τύπος<select name="type" <?= $isHome ? 'disabled' : '' ?>><?php foreach (PAGE_TYPES as $k => $l): if ($k === 'home' && !$isHome) { continue; } ?><option value="<?= e($k) ?>"<?= $p['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <?php if ($isHome): ?><input type="hidden" name="type" value="home"><?php endif; ?>
      <label>Γονική σελίδα<select name="parent_id" <?= $isHome ? 'disabled' : '' ?>><option value="">— (κορυφή)</option><?php foreach ($parents as $pp): if ((int) $pp['id'] === (int) $p['id']) { continue; } ?><option value="<?= (int) $pp['id'] ?>"<?= (int) $p['parent_id'] === (int) $pp['id'] ? ' selected' : '' ?>><?= e($pp['title']) ?></option><?php endforeach; ?></select></label>
      <div class="row2">
        <label>Σειρά<input type="number" name="sort" value="<?= (int) $p['sort'] ?>"></label>
        <label class="check" style="align-self:end"><input type="checkbox" name="in_nav" value="1"<?= $p['in_nav'] ? ' checked' : '' ?>> Στο κύριο μενού</label>
      </div>
      <label>Ετικέτες κάρτας (με κόμμα)<input name="tags" value="<?= e($p['tags']) ?>"></label>
      <label>Εικονίδιο (κύριες υπηρεσίες)<select name="icon"><?php foreach (['' => '—', 'ledger' => 'Βιβλίο (λογιστικά)', 'shield' => 'Ασπίδα (ασφάλειες)', 'bolt' => 'Κεραυνός (ρεύμα)'] as $k => $l): ?><option value="<?= e($k) ?>"<?= $p['icon'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    </section>

    <section class="card form">
      <h2>Εικόνα hero</h2>
      <label>Εικόνα (από τα Πολυμέσα)<input name="hero_image" value="<?= e($p['hero_image']) ?>" list="media-list" placeholder="/uploads/…"></label>
      <datalist id="media-list"><?php foreach ($media as $m): ?><option value="<?= e($m['path']) ?>"><?= e($m['alt']) ?></option><?php endforeach; ?></datalist>
      <label>Alt εικόνας<input name="hero_alt" value="<?= e($p['hero_alt']) ?>"></label>
      <small class="muted">Κενό = ζωγραφισμένο τοπίο.</small>
    </section>
  </aside>
</form>
