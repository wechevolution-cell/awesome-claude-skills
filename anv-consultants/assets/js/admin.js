/* ANV admin: live SEO/AEO analyzer, repeaters, counters, SERP preview. */
(() => {
  'use strict';
  const d = document;
  const csrf = (d.querySelector('meta[name="csrf"]') || {}).content || '';

  // repeaters
  d.querySelectorAll('[data-add]').forEach(btn => btn.addEventListener('click', () => {
    const rep = d.querySelector('[data-repeater="' + btn.dataset.add + '"]');
    const last = rep.querySelector('.rep-item:last-child');
    const clone = last.cloneNode(true);
    clone.querySelectorAll('input,textarea').forEach(i => { i.value = ''; });
    rep.appendChild(clone);
    clone.querySelector('input,textarea').focus();
    schedule();
  }));
  d.addEventListener('click', e => {
    const b = e.target.closest('[data-remove]');
    if (!b) return;
    const item = b.closest('.rep-item');
    const rep = item.parentElement;
    if (rep.children.length > 1) item.remove(); else item.querySelectorAll('input,textarea').forEach(i => { i.value = ''; });
    schedule();
  });

  // copy media path
  d.querySelectorAll('.copy').forEach(c => c.addEventListener('click', () => {
    navigator.clipboard && navigator.clipboard.writeText(c.textContent.trim()).then(() => { c.dataset.old = c.textContent; c.textContent = 'Αντιγράφηκε'; setTimeout(() => { c.textContent = c.dataset.old; }, 1200); }).catch(() => {});
  }));

  const form = d.getElementById('editor');
  if (!form) return;

  // status → publish date
  const status = form.querySelector('[data-status]');
  const when = form.querySelector('[data-when]');
  const syncWhen = () => { when.hidden = status.value !== 'scheduled'; };
  status.addEventListener('change', syncWhen); syncWhen();

  // counters
  const counters = [...form.querySelectorAll('[data-count]')];
  const syncCounters = () => counters.forEach(c => {
    const f = form.elements[c.dataset.count];
    const n = f.value.trim().length;
    c.textContent = n + ' χαρ.';
    c.className = 'muted counter ' + (n === 0 ? '' : (n >= +c.dataset.min && n <= +c.dataset.max ? 'ok' : 'bad'));
  });

  // SERP preview
  const site = d.title.split('· Admin ').pop();
  const serp = () => {
    const t = form.elements.meta_title.value.trim() || (form.elements.title.value.trim() + ' · ' + site);
    const desc = form.elements.meta_desc.value.trim() || form.elements.intro.value.trim();
    d.getElementById('serp-title').textContent = t.length > 62 ? t.slice(0, 60) + '…' : t;
    d.getElementById('serp-desc').textContent = desc.length > 162 ? desc.slice(0, 160) + '…' : desc;
    d.getElementById('serp-url').textContent = location.host + (d.getElementById('path-preview').textContent || '');
  };

  // auto-slug for new pages until the user edits the slug
  const slug = form.elements.slug;
  let slugTouched = !!(slug && slug.value);
  slug && slug.addEventListener('input', () => { slugTouched = true; });

  let timer = 0, inflight = null;
  function schedule() { clearTimeout(timer); timer = setTimeout(analyze, 450); syncCounters(); serp(); }
  async function analyze() {
    const fd = new FormData(form);
    if (slug && !slugTouched) fd.set('slug', '');
    if (inflight) inflight.abort();
    inflight = new AbortController();
    try {
      const r = await fetch(form.dataset.analyze, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf }, signal: inflight.signal, credentials: 'same-origin' });
      if (!r.ok) return;
      const a = await r.json();
      setScore('seo-score', a.seo); setScore('aeo-score', a.aeo);
      const ul = d.getElementById('checks');
      ul.innerHTML = '';
      a.checks.forEach(c => {
        const li = d.createElement('li'); li.className = c.level;
        const dot = d.createElement('span'); dot.className = 'dot'; dot.setAttribute('aria-hidden', 'true');
        const div = d.createElement('div'); const b = d.createElement('b'); b.textContent = c.label; div.appendChild(b);
        if (!c.ok) { div.appendChild(d.createElement('br')); const s = d.createElement('span'); s.className = 'muted'; s.textContent = c.hint; div.appendChild(s); }
        li.append(dot, div); ul.appendChild(li);
      });
      const can = d.getElementById('cannibal');
      can.textContent = a.cannibal.length ? 'Προσοχή, πιθανό keyword cannibalization: ' + a.cannibal.map(x => '«' + x.title + '» (' + x.kw + ', ' + x.level + ')').join(', ') : '';
      d.getElementById('path-preview').textContent = a.path;
      if (slug && !slugTouched && !slug.disabled) slug.value = a.slug;
      serp();
    } catch (e) { /* aborted or offline */ }
  }
  function setScore(id, v) { const el = d.getElementById(id); el.textContent = v; el.className = 'score big s' + Math.floor(v / 20); }
  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  syncCounters(); serp(); analyze();

  // warn on leaving with unsaved changes
  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('submit', () => { dirty = false; });
  addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
