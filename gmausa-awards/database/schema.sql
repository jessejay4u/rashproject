-- GMA-USA (Ghana Music Awards USA) website database
-- Import with: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS gmausa_awards
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gmausa_awards;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Admin dashboard users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(100) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default login: username "admin" / password "GmaUsa#2026Change!"
-- CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN.
INSERT INTO admins (username, password_hash, display_name)
VALUES ('admin', '$2y$12$pRQNY6629ksI7Jj/R4jwXur2YrcU/2hjfxBq7yzLErVULsU2jIoja', 'GMA-USA Admin')
ON DUPLICATE KEY UPDATE username = username;

-- ---------------------------------------------------------------------------
-- News articles
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS news_articles (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) NOT NULL UNIQUE,
  excerpt       VARCHAR(500),
  body          MEDIUMTEXT NOT NULL,
  image_path    VARCHAR(255),
  status        ENUM('draft','published') NOT NULL DEFAULT 'published',
  published_at  DATETIME,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status_published (status, published_at)
) ENGINE=InnoDB;

INSERT INTO news_articles (title, slug, excerpt, body, image_path, status, published_at) VALUES
('GMA-USA Unveils 2026 Nominees in Kumasi',
 'gma-usa-unveils-2026-nominees-in-kumasi',
 'Ghana Music Awards USA revealed this year''s nominees across 35 categories at a live event in Kumasi.',
 '<p>Ghana Music Awards USA (GMA-USA) unveiled its 2026 nominees at The Octopus, Kumasi City Mall, drawing artistes, industry stakeholders, and music lovers from across the Ghanaian music community.</p><p>This year''s list spans 35 categories &mdash; 24 dedicated to the US-based Ghanaian music community and 11 honoring achievements within the Ghana-based industry &mdash; continuing GMA-USA''s mission of celebrating Ghanaian culture and promoting Ghanaian music to a global audience.</p><p><em>Editor''s note: replace this placeholder body with the full official article text once supplied.</em></p>',
 NULL, 'published', '2026-05-01 10:00:00'),
('Sample Update  - Replace With Real Newsroom Content',
 'sample-update-replace-with-real-content',
 'This is placeholder content seeded during scaffolding. Edit or delete it from the admin dashboard.',
 '<p>This article is a placeholder created while scaffolding the site. Log in to the admin dashboard to edit, replace, or delete it, and to add real GMA-USA newsroom content, photos, and announcements.</p>',
 NULL, 'draft', NULL)
ON DUPLICATE KEY UPDATE slug = slug;

-- ---------------------------------------------------------------------------
-- Latest updates / announcement ticker
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS updates (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  message        VARCHAR(500) NOT NULL,
  link_url       VARCHAR(255),
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  display_order  INT NOT NULL DEFAULT 0,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO updates (message, link_url, is_active, display_order) VALUES
('2026 nominees announced  - view the full list', '/gmausa-awards/nominees.php', 1, 1),
('Nominations form is open  - submit your entry today', '/gmausa-awards/nominate.php', 1, 2)
ON DUPLICATE KEY UPDATE message = message;

-- ---------------------------------------------------------------------------
-- Award categories
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS award_categories (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL,
  category_group ENUM('us_based','ghana_based') NOT NULL DEFAULT 'us_based',
  description    VARCHAR(500),
  display_order  INT NOT NULL DEFAULT 0,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Only categories confirmed via public reporting are seeded here.
-- GMA-USA runs 35 categories total (24 US-based, 11 Ghana-based)  - add the
-- remaining ones from the admin dashboard once the official list is on hand.
INSERT INTO award_categories (name, category_group, description, display_order) VALUES
('Discovery Act of the Year', 'us_based', 'Recognizing breakout new talent in the US-based Ghanaian music scene.', 1),
('Ghana Diaspora Act of the Year', 'us_based', 'Honoring the top-performing Ghanaian diaspora act of the year.', 2),
('US-Based Female Artiste of the Year', 'us_based', 'The leading female Ghanaian artiste based in the United States.', 3),
('US-Based Male Artiste of the Year', 'us_based', 'The leading male Ghanaian artiste based in the United States.', 4)
ON DUPLICATE KEY UPDATE name = name;

-- ---------------------------------------------------------------------------
-- Nominees / winners
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nominees (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  category_id  INT NOT NULL,
  name         VARCHAR(150) NOT NULL,
  year         INT NOT NULL,
  is_winner    TINYINT(1) NOT NULL DEFAULT 0,
  image_path   VARCHAR(255),
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES award_categories(id) ON DELETE CASCADE,
  INDEX idx_year (year)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Media library (uploaded images available for reuse across content)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media_library (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  filename     VARCHAR(255) NOT NULL,
  file_path    VARCHAR(255) NOT NULL,
  alt_text     VARCHAR(255),
  uploaded_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Contact form submissions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  email       VARCHAR(150) NOT NULL,
  subject     VARCHAR(255),
  message     TEXT NOT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Nomination submissions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nomination_submissions (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  nominee_name   VARCHAR(150) NOT NULL,
  category_id    INT,
  submitted_by   VARCHAR(150) NOT NULL,
  email          VARCHAR(150) NOT NULL,
  reason         TEXT,
  status         ENUM('new','reviewed','archived') NOT NULL DEFAULT 'new',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES award_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Site settings (editable contact info, socials, hero copy)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key    VARCHAR(100) PRIMARY KEY,
  setting_value  TEXT
) ENGINE=InnoDB;

INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'Ghana Music Awards USA'),
('site_tagline', 'Celebrating Ghanaian Music & Culture Across the Diaspora'),
('hero_heading', 'Ghana Music Awards USA'),
('hero_subheading', 'Recognizing and honoring the achievements of Ghanaian musicians and artistes, in Ghana and across the diaspora.'),
('about_text', 'Ghana Music Awards - USA (GMA-USA) is an annual event that celebrates the rich culture, music, and stars of Ghana. GMA-USA is dedicated to recognizing and honoring the achievements of Ghanaian musicians and artists, both in Ghana and in the diaspora, serving as a platform for Ghanaian artists to showcase their work and connect with fans and industry professionals from around the world.'),
('ceo_name', 'Dennis Boafo ("Don D")'),
('contact_email', 'info@gmausa.org'),
('contact_phone', ''),
('facebook_url', 'https://www.facebook.com/groups/gmausa.org/'),
('instagram_url', 'https://www.instagram.com/gmausa_/'),
('twitter_url', ''),
('footer_note', 'Content on this site is seeded from publicly available information. Replace placeholder text via the admin dashboard with official GMA-USA copy.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
