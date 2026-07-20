# GMA-USA Website & Admin Dashboard

A database-backed website for **Ghana Music Awards USA (GMA-USA)**, built
with HTML/CSS/JavaScript, Bootstrap 5 (vendored locally), PHP, and MySQL.
The page layout/UX pattern (sticky nav, ticker bar, hero, news grid, category
pills, footer columns) follows the same conventions as major awards-show sites
like grammy.com, but all branding (name, colors, copy) is GMA-USA's own —
Grammy's trademarked logo/visual identity is intentionally **not** reproduced.

## Content source

Site structure and copy are drawn from **"GMAUSA.ORG Website Content
Documentation"** — a page-by-page audit of the real gmausa.org site (17
internal pages) supplied by the site owner. Real, sourced content includes:
the site's navigation structure, the full Categories & Definitions list (17
US-based + 14 Ghana-based categories with definitions, including two
documented inconsistencies reproduced as published), the Team and Board
roster, Life Patrons bios, the Charity program description, Entry
Procedures text, and real contact details (address, email, phone).

**Not yet migrated** (embedded as images on the original site, not text):
historical nominee/winner names for 2021–2025, gallery photos, video
embeds, and patron/team photos. These areas render "coming soon — upload
via the admin dashboard" states rather than fabricated content.

## What's included

**Public site** (fully responsive — mobile, tablet, desktop):
- Home, About Us, Team, Life Patrons, Categories & Definitions, Entry
  Procedures, Nominees & Winners (by year, incl. combined "2022 & 2023"),
  Gallery (5 photo groups), Videos, Charity, Accreditation (real form
  fields: passport/ID details + photo uploads), News, Contact,
  Nomination form (respects an open/closed toggle), and a footer
  newsletter signup.
- Nav mirrors the real site's structure: Home / Gallery / Accreditation /
  Videos / Nomination / Entry & Categories (dropdown) / About GMA-USA
  (dropdown: About, Team, Nominees & Winners by year) / Life Patrons /
  Charity / Contact, plus external "Vote Now" and "Online Tickets" links.

**Admin dashboard** (`/admin`, single-admin login) — CRUD for: News
Articles (image upload), Latest Updates (ticker), Award Categories,
Nominees, Team & Board, Life Patrons, Videos, Gallery (grouped photo
uploads), Media Library, plus read/status views for Contact Messages,
Nomination Submissions, Accreditation Submissions (contains sensitive
PII — see Security notes), and Newsletter Subscribers, and a Site
Settings page (hero copy, about text, contact/social links, entry
procedures text, charity text, nominations open/closed toggle, admin
password change).

**MySQL schema** (`database/schema.sql`) — 15 tables with idempotent seed
data (re-running the script updates rather than duplicates rows).

## Requirements

- PHP 8.1+ with `pdo_mysql` extension
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` and **`AllowOverride All`** (the `.htaccess`
  files are what protect `config/`, `database/`, `includes/`, and block PHP
  execution inside `uploads/` — they only take effect if your host allows
  `.htaccess` overrides, which is the default on shared hosts like cPanel/
  Namecheap; nginx users must port these rules into the server block instead)

## Setup

1. **Create the database**

   ```bash
   mysql -u root -p < database/schema.sql
   ```

   Creates the `gmausa_awards` database, all tables, and seed data
   (categories, team/board, patrons, videos, a default admin account).

2. **Configure the connection** — edit `config/db.php` (or set environment
   variables `GMAUSA_DB_HOST`, `GMAUSA_DB_PORT`, `GMAUSA_DB_NAME`,
   `GMAUSA_DB_USER`, `GMAUSA_DB_PASS`, `GMAUSA_APP_URL`):

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'gmausa_awards');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('APP_URL', 'https://yourdomain.com/gmausa-awards');
   ```

   Use a dedicated, least-privileged MySQL user for the app rather than root.

3. **Make `uploads/` writable** by the web server user:

   ```bash
   chmod -R 755 uploads
   ```

4. **Log into the dashboard** at `/admin/login.php`:

   - Username: `admin`
   - Password: `GmaUsa#2026Change!`

   **Change this password immediately** via Site Settings → Change Password.

5. **Fill in what's missing** — historical nominees/winners (2021–2025),
   gallery/charity photos, video URLs, team/patron photos, and the "Vote
   Now" / "Online Tickets" links in Site Settings once you have current
   values (they're hidden from the nav until set).

## Project structure

```
gmausa-awards/
├── config/db.php           # DB connection + app constants (edit this)
├── database/schema.sql     # Full schema + seed data (15 tables)
├── includes/               # Shared PHP: header, footer, helper functions
├── assets/
│   ├── vendor/bootstrap/           # Bootstrap 5.3.8 (vendored, no CDN dependency)
│   ├── vendor/bootstrap-icons/     # Bootstrap Icons 1.11.3
│   ├── css/style.css               # GMA-USA theme (Ghana-flag palette)
│   └── js/main.js
├── uploads/                # news/ nominees/ team/ patrons/ gallery/ media/ accreditation/
├── admin/                  # Dashboard (session-auth protected)
│   ├── login.php / logout.php
│   ├── index.php                          # Stats overview
│   ├── news.php + news-form.php + news-delete.php
│   ├── updates.php                        # Ticker bar CRUD
│   ├── categories.php / nominees.php
│   ├── team.php / patrons.php / videos.php / gallery.php
│   ├── media.php                          # Media library
│   ├── messages.php / nominations.php / accreditation.php / newsletter.php
│   └── settings.php                       # Site copy, links, toggles, password
├── index.php, about.php, team.php, patrons.php, categories.php, entry.php,
├── nominees.php, gallery.php, videos.php, charity.php, accreditation.php,
├── news.php, news-article.php, contact.php, nominate.php, newsletter-subscribe.php
└── .htaccess (+ per-folder .htaccess for config/database/includes/uploads)
```

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt/argon2 default).
- All admin and public forms are CSRF-protected (`csrf_field()` / `csrf_verify()`).
- All database queries use PDO prepared statements.
- Uploaded images are validated by MIME type + `getimagesize()`, renamed to
  random filenames, size-capped at 5MB, and PHP execution is disabled inside
  `uploads/` via `.htaccess`.
- Login is rate-limited (5 attempts / 60 seconds) and sessions expire after
  8 hours of inactivity (`SESSION_LIFETIME` in `config/db.php`).
- `config/`, `database/`, and `includes/` are blocked from direct web access
  via `.htaccess` — verified against a real Apache instance with
  `AllowOverride All`.
- **The Accreditation form collects sensitive PII** (passport number/details,
  date of birth, ID photos). Restrict dashboard access, serve the site over
  HTTPS in production, and periodically delete records you no longer need.

## Local testing (for reference)

Verified locally with PHP's built-in server + MariaDB (schema import,
idempotent re-import, full CRUD flows for every content type including
image uploads, CSRF rejection, login throttling), and separately against a
real Apache + `AllowOverride All` vhost to confirm `.htaccess` protections.
Responsive layout checked at 1440×900 (desktop), 768×1024 (tablet), and
390×844 (mobile) viewports via Playwright screenshots.
