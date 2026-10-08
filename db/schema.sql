-- php-short-url schema (applied automatically on first run)
CREATE TABLE IF NOT EXISTS links (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  code       TEXT NOT NULL UNIQUE,
  target     TEXT NOT NULL,
  note       TEXT DEFAULT '',
  clicks     INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL,
  expires_at INTEGER DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS hits (
  id       INTEGER PRIMARY KEY AUTOINCREMENT,
  link_id  INTEGER NOT NULL REFERENCES links(id) ON DELETE CASCADE,
  hit_at   INTEGER NOT NULL,
  ip       TEXT DEFAULT '',
  referer  TEXT DEFAULT '',
  ua       TEXT DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_hits_link ON hits(link_id);
CREATE INDEX IF NOT EXISTS idx_links_code ON links(code);
