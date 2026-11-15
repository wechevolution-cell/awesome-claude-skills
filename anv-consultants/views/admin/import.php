<?php use function App\{e, csrf_field}; ?>
<h1>Εισαγωγή markdown</h1>
<div class="cols">
<form method="post" enctype="multipart/form-data" class="form card">
  <?= csrf_field() ?>
  <label>Τύπος σελίδας<select name="type"><option value="subservice">Υποϋπηρεσία</option><option value="page">Σελίδα</option><option value="service">Υπηρεσία (κύρια)</option></select></label>
  <label>Γονική υπηρεσία (για υποϋπηρεσίες)<select name="parent_id"><option value="">—</option><?php foreach ($parents as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?></select></label>
  <label>Αρχείο .md<input type="file" name="file" accept=".md,.markdown,text/markdown,text/plain"></label>
  <label>ή επικολλήστε εδώ<textarea name="markdown" rows="16" placeholder="# Τίτλος σελίδας&#10;&#10;Πρώτη παράγραφος…"></textarea></label>
  <button class="btn" type="submit">Εισαγωγή ως πρόχειρο</button>
</form>
<div class="card">
  <h2>Πώς διαβάζεται το αρχείο</h2>
  <ul class="plain">
    <li><code># Τίτλος</code> → τίτλος σελίδας και H1</li>
    <li>Η πρώτη παράγραφος → εισαγωγή (άμεση απάντηση)</li>
    <li><code>## TL;DR</code> → σύντομη απάντηση</li>
    <li><code>## Βασικά σημεία</code> με λίστα → βασικά σημεία</li>
    <li><code>## FAQ</code> με <code>### Ερώτηση</code> και από κάτω την απάντηση → ερωτήσεις FAQ</li>
    <li><code>## Πηγές</code> με λίστα συνδέσμων → πηγές</li>
    <li>Υποστηρίζονται H2–H4, λίστες, πίνακες, blockquote, κώδικας και σύνδεσμοι.</li>
    <li>Προαιρετικά, στην αρχή: <code>---</code> με <code>slug:</code>, <code>description:</code>, <code>keyword:</code> και <code>---</code></li>
  </ul>
<pre class="sample">---
slug: forologikes-diloseis
keyword: φορολογική δήλωση
---
# Φορολογικές δηλώσεις

Ετοιμάζουμε και υποβάλλουμε τη δήλωσή σας.

## Τι περιλαμβάνει
- Ε1, Ε2, Ε3

## FAQ
### Πότε υποβάλλεται;
Κάθε χρόνο, στις προθεσμίες της ΑΑΔΕ.</pre>
</div>
</div>
