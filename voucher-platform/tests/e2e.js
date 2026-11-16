/*
 * End-to-end test. Run against a FRESH install:
 *   cd site && APP_ENV=dev DB_SQLITE_PATH=/tmp/e2e.sqlite php -S 127.0.0.1:8000 index.php
 *   node tests/e2e.js http://127.0.0.1:8000 [screenshotDir]
 */
const { chromium } = require('playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8000';
const SHOTS = process.argv[3] || null;
const results = [];
let failed = 0;

function ok(name, cond, extra = '') {
  results.push(`${cond ? 'PASS' : 'FAIL'}  ${name}${extra ? '  · ' + extra : ''}`);
  if (!cond) failed++;
}

async function shot(page, name) {
  if (!SHOTS) return;
  await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: false });
}

async function login(page, email, password, path = '/eisodos') {
  await page.goto(BASE + path);
  await page.fill('input[name=email]', email);
  await page.fill('input[name=password]', password);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
}

async function logout(ctx) {
  await ctx.clearCookies();
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  const jsErrors = [];
  page.on('pageerror', (e) => jsErrors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon|googletagmanager|ERR_|Failed to load resource/.test(m.text())) jsErrors.push(m.text()); });
  const assetErrors = [];
  page.on('response', (res) => { if (res.status() >= 400 && /\/(assets|uploads)\/|\/icon-\d+\.png|\/sw\.js|manifest/.test(res.url())) assetErrors.push(res.status() + ' ' + res.url()); });

  // 1. first run → setup
  let r = await page.goto(BASE + '/');
  ok('first visit redirects to /admin/setup', page.url().endsWith('/admin/setup'));
  await page.fill('#s-site', 'Demo Brand');
  await page.fill('#s-name', 'Admin');
  await page.fill('#s-email', 'admin@example.test');
  await page.fill('#s-p1', 'correct-horse-battery');
  await page.fill('#s-p2', 'correct-horse-battery');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  ok('admin created and dashboard opens', page.url().endsWith('/admin') && (await page.textContent('h1')).includes('Πίνακας'));
  r = await page.goto(BASE + '/admin/setup');
  ok('setup is closed once an admin exists', page.url().endsWith('/admin/login') || page.url().endsWith('/admin'));
  await shot(page, 'admin-dashboard');

  // 2. demo data
  await page.goto(BASE + '/admin/demo');
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Φόρτωση demo")')]);
  const creds = await page.$$eval('table.t tbody tr', (rows) => rows.map((tr) => [...tr.querySelectorAll('td')].map((td) => td.textContent.trim())));
  const cred = (email) => creds.find((c) => c[1] === email)[2];
  ok('demo loaded with 4 role logins', creds.length === 4, creds.map((c) => c[1]).join(', '));

  // 3. public pages + JSON-LD
  await logout(ctx);
  const publicPaths = ['/', '/prosfores', '/kampanies', '/katigories', '/pos-leitourgei', '/syxnes-erotiseis', '/epikoinonia', '/synergates', '/oroi', '/aporrito', '/cookies', '/eggrafi', '/eisodos', '/sitemap.xml', '/robots.txt', '/llms.txt', '/manifest.webmanifest'];
  for (const p of publicPaths) {
    r = await page.goto(BASE + p);
    ok(`200 ${p}`, r.status() === 200, String(r.status()));
  }
  r = await page.goto(BASE + '/den-yparxei');
  ok('404 for unknown page', r.status() === 404);
  r = await page.goto(BASE + '/prosfores/');
  ok('trailing slash → 301 to canonical', page.url().endsWith('/prosfores') && r.request().redirectedFrom() !== null);
  const sitemap = await (await page.goto(BASE + '/sitemap.xml')).text();
  const svcUrls = [...sitemap.matchAll(/<loc>([^<]*\/prosfora\/[^<]*)<\/loc>/g)].map((m) => m[1]);
  ok('sitemap lists offers', svcUrls.length >= 9, svcUrls.length + ' offers');
  const robots = await (await page.goto(BASE + '/robots.txt')).text();
  ok('robots.txt blocks everything while site is in preparation', /Disallow: \//.test(robots));

  for (const p of ['/', '/prosfores', '/syxnes-erotiseis', '/pos-leitourgei', new URL(svcUrls[0]).pathname]) {
    await page.goto(BASE + p);
    const blocks = await page.$$eval('script[type="application/ld+json"]', (s) => s.map((x) => x.textContent));
    let parsed = 0, types = [];
    for (const b of blocks) { try { const j = JSON.parse(b); parsed++; (j['@graph'] || []).forEach((n) => types.push(n['@type'])); } catch (e) {} }
    ok(`JSON-LD parses on ${p}`, blocks.length > 0 && parsed === blocks.length, types.join(','));
  }
  await page.goto(BASE + '/');
  await shot(page, 'home-desktop');

  // 4. consumer buys a paid offer with a variant
  await login(page, 'consumer@demo.local', cred('consumer@demo.local'));
  ok('consumer lands on account', page.url().endsWith('/logariasmos'));
  await page.goto(BASE + '/prosfores?q=Spa');
  await page.click('.card h3 a');
  await page.waitForLoadState();
  ok('service page has Product JSON-LD', (await page.content()).includes('"@type":"Product"'));
  await shot(page, 'service-page');
  const variants = await page.$$('input[name=variant_id]');
  ok('service shows variants', variants.length === 2);
  await page.click('.variants label:nth-of-type(2)');
  const shownPrice = await page.textContent('[data-price]');
  ok('variant changes the price', /89/.test(shownPrice), shownPrice);
  await page.click('[data-buy]');
  await page.waitForSelector('#cart-drawer.open');
  await page.waitForFunction(() => document.querySelector('[data-cart-count]').textContent === '1');
  ok('ajax add opens the cart drawer with 1 item', true);
  await shot(page, 'cart-drawer');
  await page.goto(BASE + '/tameio');
  await page.check('input[name=terms]');
  await shot(page, 'checkout');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn.gold')]);
  ok('paid order goes to test payment page', page.url().includes('/pay/test/'));
  await Promise.all([page.waitForNavigation(), page.click('button[value=approve]')]);
  ok('after payment the order page shows codes', page.url().includes('/paraggelia/') && (await page.$$('.ticket')).length === 1);
  await page.waitForSelector('.qrbox svg');
  ok('QR code is drawn', true);
  await shot(page, 'order-success');
  const paidCode = (await page.textContent('.ticket .code')).trim();

  // free offer from another business
  await page.goto(BASE + '/prosfores?t=free');
  const freeCount = await page.$$eval('.card', (c) => c.length);
  ok('free filter shows only free offers', freeCount >= 3, freeCount + ' cards');
  await page.goto(BASE + '/prosfores?q=' + encodeURIComponent('Καφές'));
  await page.click('.card [data-add]');
  await page.waitForFunction(() => document.querySelector('[data-cart-count]').textContent === '1');
  await page.goto(BASE + '/tameio');
  await page.check('input[name=terms]');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn.gold')]);
  ok('free order issues the code immediately', page.url().includes('/paraggelia/') && (await page.$$('.ticket')).length === 1);
  const freeCode = (await page.textContent('.ticket .code')).trim();

  // favourites + my codes
  await page.goto(BASE + '/prosfores');
  await page.click('.card [data-fav]');
  await page.waitForFunction(() => document.querySelector('.card [data-fav]').getAttribute('aria-pressed') === 'true');
  ok('favourite toggles on', true);
  await page.goto(BASE + '/logariasmos/kodikoi');
  ok('my codes lists 2 active codes', (await page.$$('.ticket')).length === 2);
  await page.goto(BASE + '/logariasmos/kodikos/' + paidCode);
  ok('fullscreen code page renders', (await page.textContent('.showcode .code')).trim() === paidCode);

  // per-user limit
  await page.goto(BASE + '/prosfores?q=' + encodeURIComponent('Καφές'));
  await page.click('.card [data-add]');
  await page.click('.card [data-add]');
  await page.goto(BASE + '/tameio');
  await page.check('input[name=terms]');
  await page.click('button[type=submit].btn.gold');
  await page.waitForLoadState();
  ok('per-customer limit is enforced at checkout', (await page.content()).includes('όριο κωδικών ανά πελάτη'));
  await page.goto(BASE + '/kalathi');
  while (await page.$('form.qty button[aria-label="Λιγότερα"]')) {
    await Promise.all([page.waitForNavigation(), page.click('form.qty button[aria-label="Λιγότερα"]')]);
  }

  // 5. cashier redeems
  await logout(ctx);
  await login(page, 'staff@demo.local', cred('staff@demo.local'));
  ok('staff lands on redeem screen', page.url().endsWith('/business/redeem'));
  await page.fill('#code', paidCode.toLowerCase().replace(/-/g, ' '));
  await page.click('[data-check]');
  await page.waitForSelector('[data-result]:not([hidden])');
  ok('check shows a valid code before confirming', (await page.textContent('[data-r-title]')).includes('Έγκυρος'));
  await shot(page, 'business-redeem');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn.gold')]);
  ok('redemption confirmed', (await page.textContent('[data-r-title]')).includes('Εξαργυρώθηκε'));
  await page.fill('#code', paidCode);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn.gold')]);
  ok('second redemption is refused', (await page.textContent('[data-r-msg]')).includes('ήδη εξαργυρωθεί'));
  await page.fill('#code', freeCode);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn.gold')]);
  ok('code of another business is refused', (await page.textContent('[data-r-msg]')).includes('άλλη επιχείρηση'));
  r = await page.goto(BASE + '/business/c/services');
  ok('staff cannot open the catalogue', r.status() === 403);
  r = await page.goto(BASE + '/admin');
  ok('staff cannot open admin', r.status() === 403);

  // 6. agent sees the commission
  await logout(ctx);
  await login(page, 'agent@demo.local', cred('agent@demo.local'));
  ok('agent lands on dashboard', page.url().endsWith('/agent'));
  const pendingTxt = await page.textContent('.kpis div:nth-child(3) b');
  ok('agent commission recorded on redemption', !/^0 €$/.test(pendingTxt.trim()), pendingTxt.trim());
  await shot(page, 'agent-dashboard');

  // 7. merchant creates an offer → pending → admin approves → public
  await logout(ctx);
  await login(page, 'merchant@demo.local', cred('merchant@demo.local'));
  ok('merchant lands on business dashboard', page.url().endsWith('/business'));
  await page.goto(BASE + '/business/c/services/new');
  await page.fill('#f-title', 'Δοκιμαστική υπηρεσία E2E');
  await page.selectOption('#f-category_id', { index: 1 });
  await page.fill('#f-regular_cents', '40,00');
  await page.fill('#f-price_cents', '25,00');
  await page.check('input[name="stores[]"]');
  await Promise.all([page.waitForNavigation(), page.click('form.form button[type=submit].btn.gold')]);
  ok('merchant offer saved as pending', (await page.content()).includes('στάλθηκε για έγκριση'));
  await shot(page, 'business-service-form');
  r = await page.goto(BASE + '/prosfora/dokimastiki-ypiresia-e2e');
  ok('pending offer is not public', r.status() === 404);

  await logout(ctx);
  await login(page, 'admin@example.test', 'correct-horse-battery', '/admin/login');
  await page.goto(BASE + '/admin/approvals');
  const form = page.locator('form', { has: page.locator('input[name=type][value=service]') }).first();
  await Promise.all([page.waitForNavigation(), form.locator('button[value=approve]').click()]);
  r = await page.goto(BASE + '/prosfora/dokimastiki-ypiresia-e2e');
  ok('approved offer becomes public', r.status() === 200);
  await page.goto(BASE + '/admin/settings');
  await shot(page, 'admin-settings');
  await page.goto(BASE + '/admin/c/services');
  await shot(page, 'admin-services');

  // 8. CSRF + lockout
  r = await page.request.post(BASE + '/kalathi/add', { form: { service_id: '1' }, headers: { Accept: 'application/json' } });
  ok('POST without CSRF token is rejected', r.status() === 419);
  await logout(ctx);
  for (let i = 0; i < 5; i++) {
    await login(page, 'merchant@demo.local', 'wrong-password-' + i);
  }
  await login(page, 'merchant@demo.local', cred('merchant@demo.local'));
  ok('account locks after 5 failed logins', (await page.content()).includes('Πολλές αποτυχημένες'));

  // 9. contact form (honeypot + timing)
  await page.goto(BASE + '/epikoinonia');
  await page.fill('#c-name', 'Τεστ');
  await page.fill('#c-email', 'test@example.test');
  await page.fill('#c-msg', 'Μήνυμα από το αυτόματο τεστ.');
  await page.check('input[name=consent]');
  await page.waitForTimeout(3100);
  await Promise.all([page.waitForNavigation(), page.click('form button[type=submit]')]);
  ok('contact form stores the message (PRG)', (await page.content()).includes('Το μήνυμά σου στάλθηκε') && page.url().endsWith('/epikoinonia'));

  // 10. API for the mobile app
  const api = await page.request.post(BASE + '/api/v1/auth/login', { data: { email: 'consumer@demo.local', password: cred('consumer@demo.local'), device: 'e2e' } });
  const tok = (await api.json()).token;
  ok('API login returns a token', !!tok);
  const offers = await (await page.request.get(BASE + '/api/v1/offers')).json();
  ok('API lists offers', offers.ok && offers.data.length >= 9, offers.data.length + ' offers');
  const mine = await (await page.request.get(BASE + '/api/v1/me/codes', { headers: { Authorization: 'Bearer ' + tok } })).json();
  ok('API returns my codes', mine.ok && mine.data.length === 2);
  const noauth = await page.request.get(BASE + '/api/v1/me/codes');
  ok('API rejects calls without token', noauth.status() === 401);

  // 11. responsive: no horizontal scroll at 390px
  const mob = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
  const mp = await mob.newPage();
  mp.on('pageerror', (e) => jsErrors.push(e.message));
  for (const p of ['/', '/prosfores', new URL(svcUrls[0]).pathname, '/kampanies', '/katigories', '/synergates', '/eisodos', '/syxnes-erotiseis', '/kalathi']) {
    await mp.goto(BASE + p);
    const w = await mp.evaluate(() => document.documentElement.scrollWidth);
    ok(`no horizontal scroll at 390px on ${p}`, w <= 390, 'scrollWidth ' + w);
  }
  await mp.goto(BASE + '/');
  await mp.waitForTimeout(400);
  if (SHOTS) await mp.screenshot({ path: `${SHOTS}/home-mobile.png` });
  await mp.goto(BASE + new URL(svcUrls[0]).pathname);
  if (SHOTS) await mp.screenshot({ path: `${SHOTS}/service-mobile.png` });
  await mp.evaluate(() => document.documentElement.setAttribute('data-mood', 'day'));
  await mp.goto(BASE + '/');
  await mp.evaluate(() => { localStorage.setItem('mood', 'day'); });
  await mp.reload();
  if (SHOTS) await mp.screenshot({ path: `${SHOTS}/home-mobile-day.png` });

  // broken images
  await page.goto(BASE + '/');
  const broken = await page.$$eval('img', (imgs) => imgs.filter((i) => i.complete && i.naturalWidth === 0).map((i) => i.src));
  ok('no broken images on home', broken.length === 0, broken.join(','));
  ok('no JavaScript errors', jsErrors.length === 0, jsErrors.slice(0, 3).join(' | '));
  ok('no failed asset requests (css, js, fonts, icons)', assetErrors.length === 0, assetErrors.slice(0, 3).join(' | '));

  await browser.close();
  console.log(results.join('\n'));
  console.log(`\n${results.length - failed}/${results.length} passed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(results.join('\n')); console.error(e); process.exit(2); });
