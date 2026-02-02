# Satta King Results Website

## Overview
This is a Flask-based web application for displaying Satta King game results. The application scrapes results from external sources and displays them with a modern, responsive UI.

## Project Structure
- `app.py` - Main Flask application with routes, scraping logic, and database functions
- `main.py` - Entry point for the application (imports app from app.py)
- `static/` - Static assets (CSS, JS, images)
- `templates/` - Jinja2 HTML templates
- `attached_assets/` - SQL dump and other assets

## Tech Stack
- **Backend**: Flask (Python 3.11)
- **Database**: PostgreSQL (via psycopg2-binary)
- **Web Server**: Gunicorn
- **Key Libraries**:
  - `flask` - Web framework
  - `apscheduler` - Background task scheduling
  - `cloudscraper` - Bypass Cloudflare protection for scraping
  - `pywebpush` / `py-vapid` - Push notifications
  - `pytz` - Timezone handling (IST)

## Database Schema
The application uses PostgreSQL with the following tables:
- `site_settings` - Key-value store for app configuration
- `ad_placements` - Advertisement placement management
- `games` - List of games with time slots
- `satta_results` - Game results by date
- `posts` - Daily result posts for SEO
- `news_posts` - News articles
- `site_pages` - Static pages (about, contact, etc.)
- `scrape_sources` - URLs to scrape for results
- `scrape_logs` - Scraping activity logs
- `push_subscribers` - Push notification subscribers
- `url_redirects` - URL redirection rules
- `notification_logs` - Push notification history

## Key Features
1. **Auto-scraping**: Scrapes results every 30 minutes from configured sources
2. **Daily Posts**: Automatically creates daily result posts at scheduled time
3. **Push Notifications**: Sends push notifications when new results are available
4. **Result Charts**: Monthly/yearly result charts per game
5. **SEO Optimized**: Meta tags, sitemaps, and structured data
6. **Site Branding**: Upload custom logo, favicon, and site icon from Admin > Site Branding

## Running the Application
The application runs on port 5000 using Gunicorn:
```bash
gunicorn --bind 0.0.0.0:5000 --reuse-port --reload app:app
```

## Environment Variables
- `DATABASE_URL` - PostgreSQL connection string (auto-configured by Replit)
- `SESSION_SECRET` - Flask session secret key

## Recent Changes
- **Feb 2, 2026**: Migrated to Replit environment with PostgreSQL database
- All tables created and production data imported (98 games, 8881 results, 192 posts)
- Application configured to run on Replit with proper workflow settings
- **Feb 3, 2026**: Added Site Branding feature in Admin panel for uploading logo, favicon, and site icon
