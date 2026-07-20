# GMA-USA Website & Admin Dashboard

A static-feel, database-backed website for **Ghana Music Awards USA (GMA-USA)**,
built with HTML/CSS/JavaScript, Bootstrap 5 (vendored locally), PHP, and MySQL.
The page layout/UX pattern (sticky nav, ticker bar, hero, news grid, category
pills, footer columns) follows the same conventions as major awards-show sites
like grammy.com, but all branding (name, colors, copy) is GMA-USA's own —
Grammy's trademarked logo/visual identity is intentionally **not** reproduced.

## What's included

- **Public site**: Home, About, Categories, Nominees, News (+ single article
  view), Contact, and Submit-a-Nomination pages. Fully responsive (mobile,
  tablet, desktop).
- **Admin dashboard** (`/admin`): single-admin login, and CRUD screens for
  News Articles (with image upload), Latest Updates (the scrolling ticker),
  Award Categories, Nominees, a Media Library, plus read views for Contact
  Messages and Nomination Submissions, and a Site Settings page (hero copy,
  about text, contact/social links, admin password change).
- **MySQL schema** (`database/schema.sql`) with seed data.

## Content status — please read

This project's network sandbox could not directly fetch gmausa.org's live
pages (blocked by the sandbox's network policy), so only the following facts
— gathered via web search — are seeded as **real** content:

- Org name (Ghana Music Awards USA / GMA-USA), CEO name (Dennis Boafo /
  "Don D"), the general mission statement, and 4 confirmed award category
  names out of the reported 35 total (24 US-based + 11 Ghana-based).
- One real news item (2026 nominees unveiled in Kumasi) with a short,
  paraphrased body — **not verbatim official copy**.

Everything else (the second sample news article, contact phone, remaining 31
category names, nominee/winner records, and photos/logo) is placeholder or
empty, clearly marked in the UI with "replace via admin dashboard" notes.
**Log into the dashboard and replace these with the real text, categories,
nominees, and images from gmausa.org** — that's what the admin panel is for.

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

   This creates the `gmausa_awards` database, all tables, and seed data,
   including a default admin account.

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

   **Change this password immediately** via Site Settings → Change Password
   after your first login.

5. **Replace placeholder content** — About text, remaining category names,
   nominees/winners, news articles, logo/photos, and social links, all from
   the admin dashboard.

## Project structure

```
gmausa-awards/
├── config/db.php           # DB connection + app constants (edit this)
├── database/schema.sql     # Full schema + seed data
├── includes/               # Shared PHP: header, footer, helper functions
├── assets/
│   ├── vendor/bootstrap/           # Bootstrap 5.3.8 (vendored, no CDN dependency)
│   ├── vendor/bootstrap-icons/     # Bootstrap Icons 1.11.3
│   ├── css/style.css               # GMA-USA theme (Ghana-flag palette)
│   └── js/main.js
├── uploads/                # User-uploaded images (news/nominees/media)
├── admin/                  # Dashboard (session-auth protected)
│   ├── login.php / logout.php
│   ├── index.php           # Stats overview
│   ├── news.php + news-form.php + news-delete.php
│   ├── updates.php         # Ticker bar CRUD
│   ├── categories.php / nominees.php
│   ├── media.php           # Media library
│   ├── messages.php        # Contact form submissions
│   ├── nominations.php     # Public nomination submissions
│   └── settings.php        # Site copy, contact/social links, password
├── index.php, about.php, categories.php, nominees.php,
├── news.php, news-article.php, contact.php, nominate.php
└── .htaccess (+ per-folder .htaccess for config/database/includes/uploads)
```

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt/argon2 default).
- All admin forms are CSRF-protected (`csrf_field()` / `csrf_verify()`).
- All database queries use PDO prepared statements.
- Uploaded images are validated by MIME type + `getimagesize()`, renamed to
  random filenames, size-capped at 5MB, and PHP execution is disabled inside
  `uploads/` via `.htaccess`.
- Login is rate-limited (5 attempts / 60 seconds) and sessions expire after
  8 hours of inactivity (`SESSION_LIFETIME` in `config/db.php`).
- `config/`, `database/`, and `includes/` are blocked from direct web access
  via `.htaccess` — verified against a real Apache instance with
  `AllowOverride All` during development.

## Local testing (for reference)

This was verified locally with PHP's built-in server + MariaDB, and
separately against a real Apache + `AllowOverride All` vhost to confirm the
`.htaccess` protections actually block direct access to `config/db.php`,
`database/schema.sql`, `includes/*.php`, and PHP execution under `uploads/`.
Responsive layout was checked at 1440×900 (desktop), 768×1024 (tablet), and
390×844 (mobile) viewports.
