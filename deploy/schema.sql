-- Satta King Database Schema
-- Run this before importing production_data.sql

-- Create sequences
CREATE SEQUENCE IF NOT EXISTS site_settings_id_seq;
CREATE SEQUENCE IF NOT EXISTS ad_placements_id_seq;
CREATE SEQUENCE IF NOT EXISTS games_id_seq;
CREATE SEQUENCE IF NOT EXISTS satta_results_id_seq;
CREATE SEQUENCE IF NOT EXISTS posts_id_seq;
CREATE SEQUENCE IF NOT EXISTS news_posts_id_seq;
CREATE SEQUENCE IF NOT EXISTS site_pages_id_seq;
CREATE SEQUENCE IF NOT EXISTS scrape_sources_id_seq;
CREATE SEQUENCE IF NOT EXISTS scrape_logs_id_seq;
CREATE SEQUENCE IF NOT EXISTS push_subscribers_id_seq;
CREATE SEQUENCE IF NOT EXISTS url_redirects_id_seq;
CREATE SEQUENCE IF NOT EXISTS notification_logs_id_seq;
CREATE SEQUENCE IF NOT EXISTS scrape_schedule_id_seq;

-- Create tables
CREATE TABLE IF NOT EXISTS site_settings (
    id INTEGER NOT NULL DEFAULT nextval('site_settings_id_seq'::regclass) PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ad_placements (
    id INTEGER NOT NULL DEFAULT nextval('ad_placements_id_seq'::regclass) PRIMARY KEY,
    placement_name VARCHAR(50) NOT NULL,
    ad_code TEXT,
    is_active smallint DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS games (
    id INTEGER NOT NULL DEFAULT nextval('games_id_seq'::regclass) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    time_slot time without time zone NOT NULL,
    display_order INTEGER DEFAULT 999,
    is_active smallint DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS satta_results (
    id INTEGER NOT NULL DEFAULT nextval('satta_results_id_seq'::regclass) PRIMARY KEY,
    game_name VARCHAR(100) NOT NULL,
    result VARCHAR(10),
    result_time time without time zone,
    result_date DATE,
    source_url TEXT,
    scraped_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS posts (
    id INTEGER NOT NULL DEFAULT nextval('posts_id_seq'::regclass) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    meta_description TEXT,
    meta_keywords TEXT,
    games_included TEXT,
    post_date DATE,
    views INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS news_posts (
    id INTEGER NOT NULL DEFAULT nextval('news_posts_id_seq'::regclass) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    excerpt TEXT,
    content TEXT,
    featured_image VARCHAR(500),
    meta_title VARCHAR(200),
    meta_description TEXT,
    meta_keywords TEXT,
    status VARCHAR(20) DEFAULT 'draft',
    views INTEGER DEFAULT 0,
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS site_pages (
    id INTEGER NOT NULL DEFAULT nextval('site_pages_id_seq'::regclass) PRIMARY KEY,
    slug VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    meta_title VARCHAR(200),
    meta_description TEXT,
    is_published smallint DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS scrape_sources (
    id INTEGER NOT NULL DEFAULT nextval('scrape_sources_id_seq'::regclass) PRIMARY KEY,
    url TEXT NOT NULL,
    source_name VARCHAR(100),
    is_active smallint DEFAULT 1,
    last_scraped_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS scrape_logs (
    id INTEGER NOT NULL DEFAULT nextval('scrape_logs_id_seq'::regclass) PRIMARY KEY,
    source_url TEXT,
    status VARCHAR(50),
    message TEXT,
    records_count INTEGER,
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS scrape_schedule (
    id INTEGER NOT NULL DEFAULT nextval('scrape_schedule_id_seq'::regclass) PRIMARY KEY,
    schedule_time time without time zone NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS push_subscribers (
    id INTEGER NOT NULL DEFAULT nextval('push_subscribers_id_seq'::regclass) PRIMARY KEY,
    endpoint TEXT NOT NULL,
    p256dh TEXT NOT NULL,
    auth TEXT NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS url_redirects (
    id INTEGER NOT NULL DEFAULT nextval('url_redirects_id_seq'::regclass) PRIMARY KEY,
    old_url TEXT NOT NULL,
    new_url TEXT NOT NULL,
    redirect_type INTEGER DEFAULT 301,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notification_logs (
    id INTEGER NOT NULL DEFAULT nextval('notification_logs_id_seq'::regclass) PRIMARY KEY,
    title VARCHAR(255),
    body TEXT,
    url TEXT,
    total_sent INTEGER DEFAULT 0,
    success_count INTEGER DEFAULT 0,
    fail_count INTEGER DEFAULT 0,
    notification_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_satta_results_game_date ON satta_results(game_name, result_date);
CREATE INDEX IF NOT EXISTS idx_satta_results_date ON satta_results(result_date);
CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts(slug);
CREATE INDEX IF NOT EXISTS idx_posts_date ON posts(post_date);
CREATE INDEX IF NOT EXISTS idx_games_active ON games(is_active);
CREATE INDEX IF NOT EXISTS idx_news_posts_slug ON news_posts(slug);
