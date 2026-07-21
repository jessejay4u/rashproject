-- GMA-USA (Ghana Music Awards USA) website database
-- Import with: mysql -u root -p < schema.sql
--
-- Content sourced from "GMAUSA.ORG Website Content Documentation" (a
-- page-by-page audit of gmausa.org supplied by the site owner, accessed
-- 20 July 2026). Historical nominee/winner names and most gallery photos
-- are embedded as images on the original site and are not available as
-- text, so those areas are seeded empty with in-app "coming soon" states
-- — upload the real photos/names via the admin dashboard when available.

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
 'Ghana Music Awards USA revealed this year''s nominees at a live event in Kumasi.',
 '<p>Ghana Music Awards USA (GMA-USA) unveiled its 2026 nominees at The Octopus, Kumasi City Mall, drawing artistes, industry stakeholders, and music lovers from across the Ghanaian music community.</p><p>The awards recognize artistes across both the US-based diaspora community and the Ghana-based music industry, continuing GMA-USA''s mission of celebrating Ghanaian culture and promoting Ghanaian music to a global audience.</p><p><em>Editor''s note: replace this placeholder body with the full official article text once supplied.</em></p>',
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
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_update_message (message)
) ENGINE=InnoDB;

INSERT INTO updates (message, link_url, is_active, display_order) VALUES
('Nominations are currently closed  - check back soon for the next window', 'nomination.php', 1, 1),
('Vote Now is open on our official voting partner site', 'https://gmaus.votinghubgh.com', 1, 2)
ON DUPLICATE KEY UPDATE message = message;

-- ---------------------------------------------------------------------------
-- Award categories (from the "Categories & Definitions" page, cat.html)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS award_categories (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL,
  category_group ENUM('us_based','ghana_based') NOT NULL DEFAULT 'us_based',
  description    TEXT,
  display_order  INT NOT NULL DEFAULT 0,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_category (name, category_group)
) ENGINE=InnoDB;

-- USA categories (page heading says "19 Categories" but only 17 are
-- exposed as readable page text on the source site - reproduced as
-- published, not corrected here).
INSERT INTO award_categories (name, category_group, description, display_order) VALUES
('Emerging Artiste of the Year', 'us_based', 'Emerging talent with significant impact, creativity, originality and strong future potential during the eligibility year.', 1),
('Female Artiste of the Year', 'us_based', 'Female artiste with the strongest impact in the US market; must have released a song, EP or album in the eligibility year.', 2),
('Male Artiste of the Year', 'us_based', 'Male artiste with significant impact, talent, creativity and originality; release required in the eligibility year.', 3),
('Gospel Artiste of the Year', 'us_based', 'Gospel artiste with significant impact and demonstrated excellence in the genre during the eligibility year.', 4),
('Afro-Pop Artiste of the Year', 'us_based', 'Afro-pop artiste with significant impact, creativity and originality in the eligibility year.', 5),
('Highlife Artiste of the Year', 'us_based', 'Highlife artiste with significant impact and demonstrated excellence in the genre.', 6),
('Reggae/Dancehall Artiste of the Year', 'us_based', 'Reggae/dancehall artiste with significant impact and a qualifying release during the eligibility year.', 7),
('Female Vocalist of the Year', 'us_based', 'Female solo artiste recognized for the best vocal delivery on a qualifying musical project.', 8),
('Male Vocalist of the Year', 'us_based', 'Male solo artiste recognized for the best vocal delivery on a qualifying musical project.', 9),
('Rapper of the Year', 'us_based', 'Rapper recognized for lyrical dexterity, word composition and rap performance on a qualifying project.', 10),
('Best DJ of the Year', 'us_based', 'DJ with significant impact promoting Ghanaian music and strong mixing, production and live-performance skill.', 11),
('Best Producer / Sound Engineer of the Year', 'us_based', 'Producer/engineer associated with leading nominated work and excellence in recording, mixing and production.', 12),
('Music Video of the Year', 'us_based', 'Outstanding music video judged on creativity; award is associated with the artiste and video director.', 13),
('Highlife Song of the Year', 'us_based', 'Outstanding highlife song based on songwriting, composition, vocals, production and audience appeal.', 14),
('Gospel Song of the Year', 'us_based', 'Outstanding gospel song based on songwriting, composition, vocals, production and audience appeal.', 15),
('Best Collaboration of the Year', 'us_based', 'Collaboration between two or more artistes with significant impact and high audience appeal.', 16),
('New Artiste of the Year', 'us_based', 'Promising new artiste whose song/EP/album established a public identity and generated strong audience appeal.', 17)
ON DUPLICATE KEY UPDATE description = VALUES(description), display_order = VALUES(display_order);

-- Ghana categories
INSERT INTO award_categories (name, category_group, description, display_order) VALUES
('Artiste of the Year', 'ghana_based', 'Artiste with the strongest impact, dominance, popularity and critical acclaim; qualifying release required.', 1),
('Most Popular Artiste of the Year', 'ghana_based', 'Artiste with the greatest popularity and audience appeal during the eligibility year.', 2),
('Song Writer of the Year', 'ghana_based', 'Songwriter associated with the best-written song, emphasizing original lyrics and composition.', 3),
('Gospel Artiste of the Year', 'ghana_based', 'Gospel artiste with significant impact and demonstrated talent, creativity and originality.', 4),
('Highlife Artiste of the Year', 'ghana_based', 'Highlife artiste with significant impact and demonstrated talent, creativity and originality.', 5),
('Hiplife/Hip-hop Artiste of the Year', 'ghana_based', 'Hiplife/hip-hop artiste with significant impact and a qualifying genre release.', 6),
('Gospel Song of the Year', 'ghana_based', 'Outstanding gospel song based on songwriting, composition, vocals, production and audience appeal.', 7),
('Highlife Song of the Year', 'ghana_based', 'Outstanding highlife song based on songwriting, composition, vocals, production and audience appeal.', 8),
('Reggae/Dancehall Artiste of the Year', 'ghana_based', 'Reggae/dancehall artiste with significant impact and a qualifying release.', 9),
('Rapper of the Year', 'ghana_based', 'Rapper recognized for lyrical dexterity, compositions, rhymes and rap execution.', 10),
('Best Collaboration of the Year', 'ghana_based', 'As published on the source site: labeled "Best Collaboration of the Year," though its definition text actually describes the Most Popular Song of the Year and audience appeal (inconsistency present on the original page).', 11),
('New Artiste of the Year', 'ghana_based', 'Promising new artiste with a release that establishes public identity and generates high audience appeal.', 12),
('Song Writer of the Year (II)', 'ghana_based', 'A second "Song Writer of the Year" entry appears on the original page, again focused on original lyrics and music composition (duplicated as published).', 13),
('Music Concert of the Year', 'ghana_based', 'Recognizes an outstanding live music event based on production value, performance quality, audience engagement, setlist and industry impact.', 14)
ON DUPLICATE KEY UPDATE description = VALUES(description), display_order = VALUES(display_order);

-- ---------------------------------------------------------------------------
-- Nominees / winners (per-year, per-category — historical names on the
-- source site are embedded in images; add real entries via the dashboard)
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
-- Core team & board members (team.html)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS team_members (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  role          VARCHAR(150) NOT NULL,
  member_type   ENUM('core_team','board') NOT NULL DEFAULT 'core_team',
  photo_path    VARCHAR(255),
  display_order INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member (name, member_type)
) ENGINE=InnoDB;

INSERT INTO team_members (name, role, member_type, display_order) VALUES
('Dennis K Boafo (Don D)', 'Chief Executive Officer & Board Member', 'core_team', 1),
('Emmanuel Kusi Mensah (Capito)', 'Chief Operations Officer (COO) & Board Member', 'core_team', 2),
('David Oppong Ntow (Nana)', 'Organiser', 'core_team', 3),
('Papa Bills', 'Country Rep', 'core_team', 4),
('P''kay Asenso', 'Head of Security', 'core_team', 5),
('Mavis Mensah', 'Head of Protocol', 'core_team', 6),
('Kofi Simpson', 'Finance Administrator', 'core_team', 7),
('Arnold Baidoo', 'Board Chairman', 'board', 1),
('Frank Owusu Kwabena Owusu', 'Board Member', 'board', 2),
('Kwame Micky', 'Board Member', 'board', 3),
('Whitney Boakye Mensah', 'Board Member', 'board', 4),
('Nana Poku (Ashes)', 'Communication Director Ghana', 'board', 5),
('Mike Tamakloe', 'Board Member', 'board', 6),
('Nathan Pryce', 'Board Member', 'board', 7),
('David Kyei (Kaywa)', 'Board Member', 'board', 8),
('Joshua Tigo', 'Board Member', 'board', 9),
('Jemima Hagan', 'Board Secretary', 'board', 10),
('Halifax Ansah Addo', 'Board Member', 'board', 11)
ON DUPLICATE KEY UPDATE role = VALUES(role), display_order = VALUES(display_order);

-- ---------------------------------------------------------------------------
-- Life Patrons (patrons.html)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS patrons (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL UNIQUE,
  role_affiliation VARCHAR(255),
  bio            TEXT,
  photo_path     VARCHAR(255),
  display_order  INT NOT NULL DEFAULT 0,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO patrons (name, role_affiliation, bio, display_order) VALUES
('Vida Djorgee', 'COO/Founder, Essential Quality Care',
 'Healthcare executive with more than 23 years of experience across hospital operations, hospice, assisted living, nursing homes and home care. Recognized for healthcare administration, operational leadership, technology integration, quality/compliance and patient-centered care.', 1),
('Isaac Ofori Amoako', 'CEO, Ike City Hotel / Ike City Group of Companies',
 'Ghanaian business executive and philanthropist with a diaspora work history before building business interests in Ghana; recognized for humanitarian activity.', 2),
('Ing. Isaac Ampem Darko', 'CEO, DARKXLYN COMPANY LTD',
 'Civil engineer, entrepreneur and pastor with extensive experience in civil engineering, earthworks, geodetic surveying, road design and consulting.', 3),
('Sabaina Dugan', 'Nursing Leader; CEO, Plush Media; CEO/Owner, Lucern Ventures',
 'Registered Nurse with 16 years of experience and an MBA. Recognized for nursing leadership, media/event production, promotion of Ghanaian/gospel music in the US entertainment environment, and entrepreneurship.', 4),
('Dr. Sandra Adom', 'Family Nurse Practitioner; Professor/Research Professional; CEO, Adom Publications LLC',
 'Board-certified FNP, professor and research professional with 15+ years in clinical care/research. Known for publishing/storytelling work and her 2025 role supporting health and wellbeing at GMA-USA.', 5)
ON DUPLICATE KEY UPDATE bio = VALUES(bio), display_order = VALUES(display_order);

-- ---------------------------------------------------------------------------
-- Videos (video.html) — titles confirmed via documentation; add the real
-- YouTube/video URL for each via the dashboard once available.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS videos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(255) NOT NULL UNIQUE,
  video_url     VARCHAR(500),
  display_order INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO videos (title, video_url, display_order) VALUES
('GMA-USA @ 6 Sponsors', NULL, 1),
('GMA-USA 2024 Performing', NULL, 2),
('Ghana Music Awards-USA 2023 Nominees Announcement', NULL, 3),
('CELEBRATION - Official GMA-USA Theme Song (Music Video)', NULL, 4),
('GMA-USA 2021 (Promo Video)', NULL, 5),
('Ghana Music Awards USA 2020 (Live Stream)', NULL, 6),
('Nominees Announcement (Kofi TV)', NULL, 7)
ON DUPLICATE KEY UPDATE title = title;

-- ---------------------------------------------------------------------------
-- Gallery images (gallery.html groups) — seeded empty; group_key values
-- match the groups documented on the source site's Gallery page.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gallery_images (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  group_key     ENUM('gmausa_at_6','nominee_announcement','more_recent','recent','old','charity') NOT NULL,
  image_path    VARCHAR(255) NOT NULL,
  caption       VARCHAR(255),
  display_order INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Partners & Sponsors (footer/homepage logo strip — documented as a common
-- site-wide element: "Sponsors, Powered and Brought To You By, and Partners"
-- sections represented through logos/images)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS partners (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL UNIQUE,
  partner_type  ENUM('sponsor','partner') NOT NULL DEFAULT 'sponsor',
  logo_path     VARCHAR(255),
  website_url   VARCHAR(255),
  display_order INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
-- Newsletter subscribers (footer subscription prompt)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(150) NOT NULL UNIQUE,
  subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Nomination submissions (artiste nomination form)
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
-- Accreditation submissions (accre.php form)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS accreditation_submissions (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  surname             VARCHAR(100) NOT NULL,
  first_name          VARCHAR(100) NOT NULL,
  other_names         VARCHAR(150),
  institution          VARCHAR(200) NOT NULL,
  email               VARCHAR(150) NOT NULL,
  date_of_birth       DATE,
  passport_number     VARCHAR(100),
  country_of_birth    VARCHAR(100),
  passport_details    VARCHAR(255),
  date_issued         DATE,
  expiration_date     DATE,
  id_photo_path       VARCHAR(255),
  media_tag_photo_path VARCHAR(255),
  status              ENUM('new','reviewed','archived') NOT NULL DEFAULT 'new',
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Site settings (editable contact info, socials, hero copy, page text)
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
('about_text', 'Ghana Music Awards - USA (GMA-USA) is an annual celebration of Ghanaian culture, music and public figures. GMA-USA recognizes Ghanaian musicians and artists both in Ghana and the diaspora through live performances and global promotion of Ghanaian music, connecting artists, fans and industry professionals. The awards also recognize Ghanaian achievement in areas such as sports, fashion and entertainment, with particular attention to diaspora participation in the United States.'),
('ceo_name', 'Dennis K Boafo ("Don D")'),
('contact_email', 'gmausa20@gmail.com'),
('contact_phone', '+1 609-251-0178'),
('contact_address', '14 Mayfair Circle, Willingboro, NJ 08046'),
('facebook_url', 'https://www.facebook.com/groups/gmausa.org/'),
('instagram_url', 'https://www.instagram.com/gmausa_/'),
('twitter_url', ''),
('vote_url', 'https://gmaus.votinghubgh.com'),
('tickets_url', ''),
('nominations_open', '0'),
('nominations_notice', 'Nominations for the current award cycle are closed. Check back soon for the next nomination window.'),
('entry_usa_text', 'Artistes must file for nomination; refusal or failure to file bars participation in the nomination process. Entries are open to songs and/or personalities that made the strongest impact on the US market during the eligibility year.'),
('entry_ghana_text', 'Artistes may be identified by the Research Team as meeting the nomination criteria and do not necessarily have to file before taking part in the nomination process. Entries focus on songs and/or personalities that made the strongest impact on the Ghana market during the eligibility year.'),
('charity_text', 'Ghana Music Awards USA has donated tablets to academically promising but financially needy students in rural areas of Ghana over a three-year period, with the objective of encouraging students to develop interest and capability in information and communication technology (ICT).'),
('newsletter_text', 'Subscribe for the latest GMA-USA news, nominee announcements and event updates.'),
('footer_note', 'Content on this site is drawn from a documented audit of gmausa.org. Historical winner/nominee photos and gallery images are not yet migrated - upload them via the admin dashboard.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
