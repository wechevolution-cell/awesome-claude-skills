CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS pages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  type TEXT NOT NULL DEFAULT 'page',          -- home | service | subservice | page | faq | contact | legal
  parent_id INTEGER REFERENCES pages(id) ON DELETE SET NULL,
  slug TEXT NOT NULL DEFAULT '',
  path TEXT NOT NULL UNIQUE,                  -- full public path, always with trailing slash
  title TEXT NOT NULL,
  nav_label TEXT NOT NULL DEFAULT '',
  h1 TEXT NOT NULL DEFAULT '',
  intro TEXT NOT NULL DEFAULT '',             -- first paragraph / direct answer
  tldr TEXT NOT NULL DEFAULT '',
  key_points TEXT NOT NULL DEFAULT '',        -- one per line
  body_md TEXT NOT NULL DEFAULT '',
  body_html TEXT NOT NULL DEFAULT '',
  faq_json TEXT NOT NULL DEFAULT '[]',        -- [{q,a}]
  howto_json TEXT NOT NULL DEFAULT '[]',      -- [{name,text}]
  howto_title TEXT NOT NULL DEFAULT '',
  sources TEXT NOT NULL DEFAULT '',           -- "Label | https://url" per line
  tags TEXT NOT NULL DEFAULT '',              -- comma separated chips
  icon TEXT NOT NULL DEFAULT '',
  hero_image TEXT NOT NULL DEFAULT '',
  hero_alt TEXT NOT NULL DEFAULT '',
  meta_title TEXT NOT NULL DEFAULT '',
  meta_desc TEXT NOT NULL DEFAULT '',
  focus_kw TEXT NOT NULL DEFAULT '',
  secondary_kws TEXT NOT NULL DEFAULT '',
  schema_type TEXT NOT NULL DEFAULT 'WebPage',
  custom_jsonld TEXT NOT NULL DEFAULT '',
  noindex INTEGER NOT NULL DEFAULT 0,
  in_nav INTEGER NOT NULL DEFAULT 0,
  sort INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'draft',       -- published | draft | scheduled
  publish_at TEXT,
  is_placeholder INTEGER NOT NULL DEFAULT 0,
  author TEXT NOT NULL DEFAULT '',
  seo_score INTEGER NOT NULL DEFAULT 0,
  aeo_score INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS pages_parent ON pages(parent_id);
CREATE INDEX IF NOT EXISTS pages_status ON pages(status);

CREATE TABLE IF NOT EXISTS redirects (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  source TEXT NOT NULL,
  target TEXT NOT NULL DEFAULT '',
  code INTEGER NOT NULL DEFAULT 301,
  match_type TEXT NOT NULL DEFAULT 'exact',   -- exact | regex
  active INTEGER NOT NULL DEFAULT 1,
  hits INTEGER NOT NULL DEFAULT 0,
  last_hit TEXT,
  note TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS redirects_source ON redirects(source);

CREATE TABLE IF NOT EXISTS notfound (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  path TEXT NOT NULL UNIQUE,
  hits INTEGER NOT NULL DEFAULT 1,
  referrer TEXT NOT NULL DEFAULT '',
  ignored INTEGER NOT NULL DEFAULT 0,
  first_hit TEXT NOT NULL,
  last_hit TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL DEFAULT '',
  pass_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  last_login TEXT
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip TEXT NOT NULL,
  email TEXT NOT NULL DEFAULT '',
  success INTEGER NOT NULL DEFAULT 0,
  at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS login_attempts_ip ON login_attempts(ip, at);

CREATE TABLE IF NOT EXISTS media (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  path TEXT NOT NULL UNIQUE,
  webp TEXT NOT NULL DEFAULT '',
  width INTEGER NOT NULL DEFAULT 0,
  height INTEGER NOT NULL DEFAULT 0,
  size INTEGER NOT NULL DEFAULT 0,
  alt TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
