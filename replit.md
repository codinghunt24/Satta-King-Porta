# Satta King Website

## Overview
A Satta King results website built with Python Flask that displays game results, charts, and news. Features Cloudflare bypass for auto-scraping, 30-minute intervals, and admin panel. Supports both MySQL (production) and PostgreSQL (Replit).

## Project Structure
- `app.py` - Main Flask application with all routes
- `templates/` - Jinja2 HTML templates
  - `base.html` - Base template with header/footer
  - `index.html` - Homepage with live results
  - `post.html` - Individual game result page
  - `chart.html` - Historical results chart
  - `admin.html` - Admin panel
  - `news.html` / `news-post.html` - News pages
  - `page.html` - Static pages
  - `daily-updates.html` - Daily updates
- `static/css/` - Stylesheets (same as PHP version)
- `requirements.txt` - Python dependencies

## Key Features
- **Cloudflare Bypass**: Uses cloudscraper library for satta-king-fast.com
- **Dual Database**: MySQL for production, PostgreSQL for Replit testing
- **Auto-Scrape**: APScheduler runs scraping every 30 minutes
- **SEO-Rich**: Same URL structure as PHP (/post/, /chart, /news/, /page/)
- **91 Games**: Supports satta.ink (35 games) + satta-king-fast.com (56 games)

## Database
Supports both MySQL and PostgreSQL:
- `games` - List of games and time slots
- `satta_results` - Game results by date
- `posts` - Daily update posts
- `news_posts` - News articles
- `site_pages` - Static pages
- `site_settings` - Site configuration
- `ad_placements` - Google AdSense placements
- `scrape_sources` - Scraper source URLs

## Admin Panel
Access at `/admin`
- Login with `SESSION_SECRET` environment variable
- Manage results, games, posts, news, pages, ads
- Manual and auto scraping controls

## Environment Variables
- `DATABASE_URL` - PostgreSQL (Replit) or omit for MySQL
- `MYSQL_HOST`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DATABASE` - MySQL (production)
- `SESSION_SECRET` - Admin panel password

## Running the Project
```
python app.py
```

## Production Deployment
Use gunicorn:
```
gunicorn -w 4 -b 0.0.0.0:5000 app:app
```

## Recent Changes
- 2026-02-02: Advanced SEO news system with Schema.org, Open Graph, pagination
- 2026-02-02: Admin news editor with rich text (H1-H3, bold, links, images, tables)
- 2026-02-02: Single news post with Article schema, FAQ, share buttons
- 2026-02-02: URL Redirects admin panel for preserving indexed PHP URLs
- 2026-02-02: Migrated from PHP to Python Flask
- 2026-02-02: Added cloudscraper for Cloudflare bypass
- 2026-02-02: Added dual database support (MySQL/PostgreSQL)
- 2026-02-02: Implemented APScheduler for 30-min auto-scrape
