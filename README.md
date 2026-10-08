# php-short-url

A dependency-free, self-hosted URL shortener in plain PHP + SQLite.
No framework, no Composer, no database server — upload and go.

## Features

- Random or custom short codes (`/abc123`)
- Click counting + per-hit log (IP, referer, user agent)
- Optional link expiry (`expires_days`)
- Token-authenticated JSON admin API (`create`, `stats`, `list`)
- Per-IP throttle on link creation (60/hour)
- Schema auto-installs on first run

## Install

1. Upload this repo so `public/` is the web root (keep `db/` **outside** the web root, or at least not writable-by-web under it — the `.htaccess` blocks `.sqlite` downloads as a safety net).
2. `cp config.example.php public/config.php` and set `BASE_URL`, `SHORT_DB`, and a long random `ADMIN_TOKEN`.
3. Make `db/` writable by the web server user. Done — the schema installs itself on the first request.

## API

```bash
# create (random code)
curl -s -H 'X-Admin-Token: YOUR_TOKEN' -X POST \
  -d 'action=create&url=https://example.com/very/long/page&note=newsletter' \
  https://s.example.com/shorten.php

# create (custom code, expires in 30 days)
curl -s -H 'X-Admin-Token: YOUR_TOKEN' -X POST \
  -d 'action=create&url=https://example.com/&code=launch&expires_days=30' \
  https://s.example.com/shorten.php

# stats + recent hits
curl -s -H 'X-Admin-Token: YOUR_TOKEN' \
  'https://s.example.com/shorten.php?action=stats&code=launch'
```

Requires PHP 7.4+ with the `pdo_sqlite` extension. MIT licensed.
