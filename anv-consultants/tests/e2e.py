#!/usr/bin/env python3
"""
ANV end-to-end test (stdlib only).
Run against a fresh install:  php -S 127.0.0.1:8000 index.php   then   python3 -I tests/e2e.py http://127.0.0.1:8000
"""
import sys, re, json, io, struct, zlib, zipfile, http.cookiejar, urllib.request, urllib.parse, urllib.error

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8000').rstrip('/')
EMAIL, PASS = 'admin@example.com', 'correct horse battery staple'
results = []


def check(name, cond, info=''):
    results.append((bool(cond), name, info))
    print(('PASS ' if cond else 'FAIL ') + name + (f'  [{info}]' if info and not cond else ''))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)


def req(path, data=None, files=None, headers=None):
    url = path if path.startswith('http') else BASE + path
    body = None
    h = dict(headers or {})
    if files:
        boundary = '----anvtest'
        parts = []
        for k, v in (data or {}).items():
            for val in (v if isinstance(v, list) else [v]):
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{val}\r\n'.encode())
        for k, (fn, content, ctype) in files.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"; filename="{fn}"\r\nContent-Type: {ctype}\r\n\r\n'.encode() + content + b'\r\n')
        parts.append(f'--{boundary}--\r\n'.encode())
        body = b''.join(parts)
        h['Content-Type'] = 'multipart/form-data; boundary=' + boundary
    elif data is not None:
        body = urllib.parse.urlencode(data, doseq=True).encode()
        h['Content-Type'] = 'application/x-www-form-urlencoded'
    r = urllib.request.Request(url, data=body, headers=h)
    try:
        resp = opener.open(r)
        return resp.status, resp.headers, resp.read()
    except urllib.error.HTTPError as e:
        return e.code, e.headers, e.read()


def csrf(html):
    m = re.search(rb'name="_csrf" value="([0-9a-f]+)"', html) or re.search(rb'name="csrf" content="([0-9a-f]+)"', html)
    return m.group(1).decode() if m else ''


def png_bytes(w=40, h=30):
    raw = b''.join(b'\x00' + bytes([20, 76, 147]) * w for _ in range(h))
    def chunk(t, d):
        return struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b'')


# ---------- public routes ----------
pub = ['/', '/logistika/', '/logistika/forologikes-diloseis/', '/asfaleies/', '/asfaleies/asfaleia-aftokinitou/', '/revma/',
       '/revma/allagi-parochou/', '/i-etaireia/', '/syxnes-erotiseis/', '/epikoinonia/', '/politiki-aporritou/', '/cookies/', '/oroi-xrisis/']
for p in pub:
    s, h, b = req(p)
    check(f'200 {p}', s == 200, s)
    check(f'one H1 on {p}', len(re.findall(rb'<h1\b', b)) == 1)
    lds = re.findall(rb'<script type="application/ld\+json">(.*?)</script>', b, re.S)
    try:
        ok = bool(lds) and all('@graph' in json.loads(x) for x in lds)
    except Exception as e:
        ok = False
    check(f'JSON-LD parses on {p}', ok)
    check(f'canonical on {p}', b'rel="canonical"' in b)

s, h, b = req('/logistika')
check('301 to trailing slash', s == 301 and h['Location'].endswith('/logistika/'), f'{s} {h.get("Location")}')
s, _, b = req('/does-not-exist/')
check('404 for unknown path', s == 404 and b'noindex' in b)
s, _, _ = req('/wp-login.php')
check('scanner path returns 404', s == 404)
for p in ['/app/db.php', '/data/site.sqlite', '/views/layout.php']:
    s, _, _ = req(p)
    check(f'protected {p}', s in (403, 404), s)
for p, ct in [('/sitemap.xml', 'xml'), ('/robots.txt', 'text/plain'), ('/llms.txt', 'text/plain'), ('/feed.xml', 'rss')]:
    s, h, b = req(p)
    check(f'{p} served', s == 200 and ct in h.get('Content-Type', ''), f'{s} {h.get("Content-Type")}')
_, _, robots = req('/robots.txt')
check('robots allows AI bots', all(x in robots for x in [b'GPTBot', b'OAI-SearchBot', b'ClaudeBot', b'PerplexityBot', b'Google-Extended']))
_, _, sm = req('/sitemap.xml')
check('sitemap lists all 26 pages', sm.count(b'<loc>') == 26, sm.count(b'<loc>'))
_, _, home = req('/')
check('FAQ/HowTo/ItemList schema on home', b'"HowTo"' in home and b'"ItemList"' in home)
_, _, sv = req('/revma/allagi-parochou/')
check('Service + FAQPage + BreadcrumbList on subservice', all(x in sv for x in [b'"Service"', b'"FAQPage"', b'"BreadcrumbList"']))
check('no GA before consent', b'googletagmanager' not in home)
check('no map iframe before consent', b'<iframe' not in home)

# ---------- admin setup ----------
s, h, _ = req('/admin/')
check('admin redirects to setup on fresh install', s == 302 and h['Location'].endswith('/admin/setup'))
_, _, b = req('/admin/setup')
tok = csrf(b)
s, _, b = req('/admin/setup', {'_csrf': tok, 'email': EMAIL, 'name': 'Test', 'password': 'short', 'password2': 'short'})
check('setup rejects short password', b'12' in b and s == 200)
s, h, _ = req('/admin/setup', {'_csrf': 'bad', 'email': EMAIL, 'password': PASS, 'password2': PASS})
check('setup rejects bad CSRF', s == 419)
s, h, _ = req('/admin/setup', {'_csrf': tok, 'email': EMAIL, 'name': 'Test', 'password': PASS, 'password2': PASS})
check('setup creates admin', s == 302 and h['Location'].endswith('/admin/login'))
s, h, _ = req('/admin/setup')
check('setup closed after first admin', s == 302)

_, _, b = req('/admin/login')
tok = csrf(b)
s, h, _ = req('/admin/login', {'_csrf': tok, 'email': EMAIL, 'password': PASS})
check('login works', s == 302 and h['Location'].endswith('/admin/'))
s, _, dash = req('/admin/')
check('dashboard loads', s == 200 and 'Πίνακας ελέγχου'.encode() in dash)
tok = csrf(dash)
check('admin noindex', b'noindex' in dash)

# ---------- settings ----------
_, _, b = req('/admin/settings')
form = {'_csrf': tok, 'site_name': 'ANV Financial Advisors', 'phone': '210 123 4567', 'email': 'info@example.gr', 'address_street': 'Οδός Δοκιμής 1',
        'address_city': 'Αθήνα', 'address_postcode': '10431', 'geo_lat': '37.9838', 'geo_lng': '23.7275', 'social_facebook': 'https://www.facebook.com/example',
        'allow_ai_bots': '1', 'hours_confirmed': '1', 'logo': '/assets/img/logo.png', 'og_image': '/assets/img/og-default.png'}
for d in range(1, 6):
    form.update({f'h_{d}_0_o': '09:00', f'h_{d}_0_c': '14:00', f'h_{d}_1_o': '17:00', f'h_{d}_1_c': '20:00'})
s, h, _ = req('/admin/settings', form)
check('settings save', s == 302)
_, _, home = req('/')
check('phone shown as tel link', b'href="tel:+302101234567"' in home)
check('LocalBusiness has address+geo+hours+sameAs', all(x in home for x in [b'"PostalAddress"', b'"GeoCoordinates"', b'"OpeningHoursSpecification"', b'"sameAs"']))
check('map placeholder offers consent load', b'data-map-load' in home)

# ---------- create page (article) ----------
_, _, b = req('/admin/pages/edit')
_, _, plist = req('/admin/pages')
parent = re.search(rb'edit\?parent=(\d+)', plist).group(1).decode()
data = {'_csrf': tok, 'id': '0', 'type': 'subservice', 'parent_id': parent, 'title': 'Δοκιμαστική υπηρεσία', 'slug': '', 'focus_kw': 'δοκιμαστική υπηρεσία',
        'intro': 'Η δοκιμαστική υπηρεσία είναι μια σελίδα ελέγχου που απαντά άμεσα σε μία ερώτηση με μία ή δύο σύντομες προτάσεις.',
        'body_md': '## Τι είναι;\n\nΚείμενο με [σύνδεσμο](/logistika/).\n\n- ένα\n- δύο\n\n<script>alert(1)</script>\n\n<iframe src="https://evil.example/x"></iframe>\n\n[κακό](javascript:alert(1))\n\n<p onclick="x()">καθαρό</p>',
        'faq_q[]': ['Ερώτηση 1;', 'Ερώτηση 2;'], 'faq_a[]': ['Απάντηση 1.', 'Απάντηση 2.'], 'status': 'published', 'schema_type': 'Service', 'meta_desc': 'Δοκιμαστική υπηρεσία για έλεγχο.'}
s, _, an = req('/admin/api/analyze', data, headers={'X-CSRF-Token': tok})
a = json.loads(an)
check('live analyzer returns scores', s == 200 and 0 <= a['seo'] <= 100 and 0 <= a['aeo'] <= 100 and len(a['checks']) > 15, a.get('seo'))
check('analyzer previews slug from Greek title', a['slug'] == 'dokimastiki-ypiresia', a['slug'])
s, _, _ = req('/admin/api/analyze', {k: v for k, v in data.items() if k != '_csrf'}, headers={'X-CSRF-Token': 'nope'})
check('analyzer rejects bad CSRF', s == 419)
s, h, _ = req('/admin/pages/edit', data)
check('page created', s == 302 and 'edit?id=' in h['Location'])
new_id = h['Location'].split('id=')[1]
s, _, b = req('/logistika/dokimastiki-ypiresia/')
check('new page live', s == 200)
check('sanitizer strips script/onclick/javascript:/foreign iframe', b'<script>alert' not in b and b'&lt;script' not in b and b'javascript:' not in b and b'onclick' not in b and b'evil.example' not in b and 'καθαρό'.encode() in b)

# cannibalization
data2 = dict(data, title='Άλλη σελίδα', slug='alli-selida', focus_kw='Δοκιμαστική Υπηρεσία')
_, _, an = req('/admin/api/analyze', data2, headers={'X-CSRF-Token': tok})
check('keyword cannibalization detected (accents/case-insensitive)', len(json.loads(an)['cannibal']) >= 1)

# ---------- slug change → 301 ----------
data['id'] = new_id
data['slug'] = 'nea-dokimi'
s, h, _ = req('/admin/pages/edit', data)
check('slug change saved', s == 302)
s, h, _ = req('/logistika/dokimastiki-ypiresia/')
check('old URL 301 → new', s == 301 and h['Location'].endswith('/logistika/nea-dokimi/'), f'{s} {h.get("Location")}')
data['slug'] = 'trito-slug'
req('/admin/pages/edit', data)
s, h, _ = req('/logistika/dokimastiki-ypiresia/')
check('redirect chain collapsed (first URL → final)', s == 301 and h['Location'].endswith('/logistika/trito-slug/'), h.get('Location'))

# ---------- redirect manager ----------
s, h, _ = req('/admin/redirects', {'_csrf': tok, 'action': 'save', 'match_type': 'exact', 'source': '/x/', 'target': '/x/', 'code': '301', 'active': '1'})
_, _, rb_ = req('/admin/redirects')
check('rejects source = destination', 'ίδια'.encode() in rb_)
req('/admin/redirects', {'_csrf': tok, 'action': 'save', 'match_type': 'exact', 'source': '/a/', 'target': '/b/', 'code': '301', 'active': '1'})
req('/admin/redirects', {'_csrf': tok, 'action': 'save', 'match_type': 'exact', 'source': '/b/', 'target': '/a/', 'code': '301', 'active': '1'})
_, _, rb_ = req('/admin/redirects')
check('rejects redirect loop', 'βρόχο'.encode() in rb_)
req('/admin/redirects', {'_csrf': tok, 'action': 'save', 'match_type': 'regex', 'source': '^/palio/(.*)$', 'target': '/revma/$1', 'code': '302', 'active': '1'})
s, h, _ = req('/palio/allagi-parochou/')
check('regex redirect with $1 (302)', s == 302 and h['Location'].endswith('/revma/allagi-parochou/'), f'{s} {h.get("Location")}')
req('/admin/redirects', {'_csrf': tok, 'action': 'save', 'match_type': 'exact', 'source': '/gone/', 'target': '', 'code': '410', 'active': '1'})
s, _, _ = req('/gone/')
check('410 rule', s == 410)
req('/admin/redirects', {'_csrf': tok, 'action': 'save', 'match_type': 'exact', 'source': '/legal-block/', 'target': '', 'code': '451', 'active': '1'})
s, _, _ = req('/legal-block/')
check('451 rule', s == 451)
for c in (307, 308):
    req('/admin/redirects', {'_csrf': tok, 'action': 'save', 'match_type': 'exact', 'source': f'/t{c}/', 'target': '/revma/', 'code': str(c), 'active': '1'})
    s, _, _ = req(f'/t{c}/')
    check(f'{c} rule', s == c)
_, _, rb_ = req('/admin/redirects')
check('hits counted', re.search(rb'<td>[1-9]\d*</td>', rb_) is not None)

# ---------- 404 monitor ----------
req('/old-page-xyz/', headers={'Referer': 'https://example.org/'})
req('/old-page-xyz/')
req('/.env')
_, _, nf = req('/admin/404')
check('404 logged with hits', b'/old-page-xyz/' in nf and b'<td>2</td>' in nf)
check('scanner paths ignored', b'<code>/.env</code>' not in nf)
nid = re.search(rb'name="id" value="(\d+)"', nf).group(1).decode()
s, _, _ = req('/admin/404', {'_csrf': tok, 'id': nid, 'action': '301', 'target': '/revma/'})
s, h, _ = req('/old-page-xyz/')
check('404 → 301 from monitor', s == 301 and h['Location'].endswith('/revma/'))

# ---------- CSRF on admin POST ----------
s, _, _ = req('/admin/settings', {'_csrf': 'forged', 'site_name': 'hacked'})
check('admin POST without CSRF rejected', s == 419)

# ---------- uploads ----------
s, h, _ = req('/admin/media', {'_csrf': tok, 'action': 'upload', 'alt': 'Δοκιμή'}, files={'file': ('Τοπίο Δοκιμής.png', png_bytes(), 'image/png')})
_, _, media = req('/admin/media')
m = re.search(rb'/uploads/\d{4}/\d{2}/(topio-dokimis[\w-]*\.png)', media)
check('image upload with slug filename', m is not None)
if m:
    s, _, _ = req(m.group(0).decode())
    check('uploaded image served', s == 200)
req('/admin/media', {'_csrf': tok, 'action': 'upload'}, files={'file': ('shell.php.png', b'<?php echo 1; ?>', 'image/png')})
_, _, media2 = req('/admin/media')
check('fake image rejected', b'shell' not in media2)

# ---------- backup ----------
s, h, b = req('/admin/backup', {'_csrf': tok})
ok = s == 200 and h.get('Content-Type') == 'application/zip'
if ok:
    z = zipfile.ZipFile(io.BytesIO(b))
    ok = 'data/site.sqlite' in z.namelist()
check('backup zip with database', ok)

# ---------- delete with two-step confirm ----------
s, _, b = req(f'/admin/pages/delete?id={new_id}')
check('delete asks for confirmation', s == 200 and 'ΔΙΑΓΡΑΦΗ'.encode() in b)
s, h, _ = req('/admin/pages/delete', {'_csrf': tok, 'id': new_id, 'after': '410', 'confirm': 'λάθος'})
s, _, _ = req('/logistika/trito-slug/')
check('wrong confirmation does not delete', s == 200)
req('/admin/pages/delete', {'_csrf': tok, 'id': new_id, 'after': '410', 'confirm': 'ΔΙΑΓΡΑΦΗ'})
s, _, _ = req('/logistika/trito-slug/')
check('deleted page → 410', s == 410)

# ---------- markdown import ----------
md = '---\nslug: eisagogi\nkeyword: εισαγωγή\n---\n# Εισαγωγή markdown\n\nΠρώτη παράγραφος ως εισαγωγή.\n\n## Ενότητα\n\n| Α | Β |\n|---|---|\n| 1 | 2 |\n\n> παράθεση\n\n## FAQ\n\n### Πρώτη ερώτηση;\nΠρώτη απάντηση.\n\n### Δεύτερη;\nΔεύτερη απάντηση.\n'
s, h, _ = req('/admin/import', {'_csrf': tok, 'type': 'page', 'markdown': md})
_, _, ed = req('/admin/pages/edit')
check('markdown import → FAQ repeater + intro', 'Πρώτη ερώτηση;'.encode() in ed and 'Πρώτη παράγραφος ως εισαγωγή.'.encode() in ed and b'eisagogi' in ed)

# ---------- maintenance 503 ----------
form2 = dict(form, maintenance='1')
req('/admin/settings', form2)
anon = urllib.request.build_opener(NoRedirect)
try:
    anon.open(BASE + '/')
    code = 200
except urllib.error.HTTPError as e:
    code = e.code
check('maintenance → 503 for visitors', code == 503)
s, _, _ = req('/')
check('admin bypasses maintenance', s == 200)
req('/admin/settings', form)

# ---------- logout + lockout (last: locks this IP for 15 min) ----------
req('/admin/logout', {'_csrf': tok})
s, h, _ = req('/admin/')
check('logout ends session', s == 302 and h['Location'].endswith('/admin/login'))
last = b''
for i in range(6):
    _, _, b = req('/admin/login')
    _, _, last = req('/admin/login', {'_csrf': csrf(b), 'email': EMAIL, 'password': 'wrong'})
_, _, b = req('/admin/login')
_, _, last = req('/admin/login', {'_csrf': csrf(b), 'email': EMAIL, 'password': PASS})
check('lockout after 5 failures blocks even the right password', '15 λεπτά'.encode() in last or 'λεπτά'.encode() in last)

fails = [r for r in results if not r[0]]
print(f'\n{len(results) - len(fails)}/{len(results)} passed')
sys.exit(1 if fails else 0)
