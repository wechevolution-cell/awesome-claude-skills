# Marketplace κωδικών εξαργύρωσης · Φάση 1

Web εφαρμογή (PWA) όπου καταναλωτές παίρνουν κωδικούς προσφοράς από καταστήματα και τους εξαργυρώνουν με QR. Υπάρχουν περιβάλλοντα για επιχειρήσεις, αντιπροσώπους και διαχειριστή.

Οδηγός αρχιτεκτονικής, βάσης, API και διαδρομής προς τα app stores: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Τι χρειάζεται

- **PHP:** 8.1 ή νεότερη (δοκιμασμένο σε 8.3).
- **Επεκτάσεις PHP:** `pdo_mysql` (ή `pdo_sqlite` για τοπικά), `mbstring`, `gd`, `openssl`, `fileinfo`. Όλες είναι ενεργές από προεπιλογή στο Hostinger.
- **Βάση:** MySQL 5.7+ / MariaDB 10.4+.
- **Server:** Apache ή LiteSpeed με `mod_rewrite`.
- **Γενικά:** HTTPS (απαραίτητο για κάμερα, PWA και ασφαλή cookies). Χωρίς Composer, χωρίς npm, χωρίς build.

## Τοπική δοκιμή

```bash
cd site
```

```bash
APP_ENV=dev php -S localhost:8000 index.php
```

Άνοιξε το `http://localhost:8000`. Στην πρώτη επίσκεψη δημιουργούνται αυτόματα:
- το `app/config.php`, με νέο μυστικό κλειδί,
- η βάση SQLite `data/app.sqlite`.

Μετά σε στέλνει στο `/admin/setup` για τον πρώτο διαχειριστή. Δεν υπάρχουν προεπιλεγμένοι κωδικοί.

Στο `dev` τα email δεν στέλνονται. Γράφονται στο `data/mail.log`.

## Εγκατάσταση σε Hostinger (shared ή VPS με hPanel)

1. **Backup:** αν υπάρχει ήδη site στο domain, κάνε πρώτα πλήρες backup (hPanel → Files → Backups).
2. **Έκδοση PHP:** hPanel → Advanced → PHP Configuration → PHP 8.2 ή 8.3.
3. **Βάση:** hPanel → Databases → MySQL Databases. Δημιούργησε βάση και χρήστη και σημείωσε όνομα, χρήστη και κωδικό.
4. **Ανέβασμα:**
   - hPanel → File Manager → `public_html`.
   - Upload το `voucher-platform-site-php.zip`.
   - Δεξί κλικ → Extract μέσα στο `public_html`.
   - Τα αρχεία (`index.php`, `.htaccess`, `app/`, `assets/` …) πρέπει να είναι απευθείας μέσα στο `public_html`, όχι σε υποφάκελο.
5. **Ρύθμιση:**
   - Αντέγραψε το `app/config.sample.php` ως `app/config.php`.
   - Συμπλήρωσε `'db' => ['driver' => 'mysql', 'host' => 'localhost', 'name' => …, 'user' => …, 'pass' => …]`.
   - Βάλε στο `app_key` μια μεγάλη τυχαία τιμή, 64 χαρακτήρες.
   - Άφησε `'env' => 'production'`.
6. **Email:**
   - Δημιούργησε mailbox (π.χ. `no-reply@το-domain.gr`) από hPanel → Emails.
   - Συμπλήρωσε το `smtp` στο `config.php`: host `smtp.hostinger.com`, port 465, secure `ssl`, user / pass του mailbox.
7. **SSL:**
   - hPanel → Security → SSL → ενεργοποίηση.
   - Μετά βγάλε τα σχόλια `#` από τους κανόνες HTTPS (και, αν θες, non-www) στην αρχή του `.htaccess`.
8. **Πρώτη επίσκεψη:** άνοιξε το domain. Οι πίνακες της βάσης δημιουργούνται αυτόματα και σε στέλνει στο `/admin/setup`.
9. **Ρυθμίσεις:** Admin → Ρυθμίσεις: όνομα brand, επικοινωνία, social, πρόθεμα κωδικών, προμήθειες.
10. **Cache:** αν χρησιμοποιείς το Cache Manager (LiteSpeed), εξαίρεσε τις διαδρομές `/admin`, `/business`, `/agent`, `/logariasmos`, `/kalathi`, `/tameio`, `/pay`, `/api`. Μετά από κάθε ενημέρωση πάτα Purge All.

## Πριν ανοίξει στο κοινό

Admin → Πίνακας ελέγχου → «Πριν το live» δείχνει τι λείπει. Επιπλέον:

- Admin → Δεδομένα επίδειξης → **Διαγραφή demo**.
- Admin → Ρυθμίσεις → SEO: ξετσέκαρε το «Κρυφό από μηχανές αναζήτησης». Το `robots.txt` ανοίγει μόνο του, με ρητή άδεια για GPTBot, ClaudeBot, PerplexityBot και Google-Extended.
- **Search Console:**
  - Βάλε τον κωδικό επιβεβαίωσης στο Admin → Ρυθμίσεις → SEO.
  - Στείλε το `https://το-domain.gr/sitemap.xml`.
- **GA4:** βάλε το Measurement ID (`G-…`). Φορτώνεται μόνο αφού ο επισκέπτης πατήσει «Αποδοχή» στα cookies.
- **Πληρωμές:** σύνδεση πραγματικού παρόχου (Φάση 2) και αλλαγή σε «Πραγματική».
- **Νομικά κείμενα** (`/oroi`, `/aporrito`, `/cookies`): είναι πρότυπα. Συμπλήρωσε τα `[…]` και δώσ' τα σε νομικό.

## Backup

- **Βάση:** hPanel → Databases → phpMyAdmin → Export, ή αυτόματα backups του Hostinger.
- **Αρχεία:** φάκελοι `uploads/` και `app/config.php`. Ο υπόλοιπος κώδικας ξαναβγαίνει από το ZIP.

Κάνε backup πριν από κάθε ενημέρωση.

## Ενημέρωση σε νέα έκδοση

1. Backup (βάση + `uploads/` + `app/config.php`).
2. Ανέβασε το νέο ZIP και κάνε extract πάνω από τα παλιά. Το `config.php`, τα `uploads/` και το `data/` δεν αντικαθίστανται.
3. Άνοιξε το site. Οι νέες αλλαγές στη βάση εφαρμόζονται αυτόματα.
4. Cache Manager → Purge All.

## Τεστ

Με Node και Playwright:

```bash
cd site && APP_ENV=dev DB_SQLITE_PATH=/tmp/e2e.sqlite php -S 127.0.0.1:8001 index.php
```

```bash
node tests/e2e.js http://127.0.0.1:8001
```

Το τεστ ξεκινά από άδεια βάση. Ελέγχει: setup, demo, σελίδες και JSON-LD, αγορά επί πληρωμή με παραλλαγή, δωρεάν κωδικό, αγαπημένα, όριο ανά πελάτη, εξαργύρωση και διπλή εξαργύρωση, κωδικό άλλης επιχείρησης, δικαιώματα ρόλων, προμήθεια αντιπροσώπου, έγκριση προσφοράς, CSRF, κλείδωμα λογαριασμού, φόρμα επικοινωνίας, API και οριζόντια κύλιση στα 390px.

## Άδειες τρίτων

- `assets/js/vendor/qrcode.js`: qrcode-generator, MIT.
- `assets/js/vendor/jsQR.js`: jsQR, Apache-2.0 (δες `jsQR.LICENSE.txt`).
- Γραμματοσειρές Noto και JetBrains Mono: SIL Open Font License 1.1.
