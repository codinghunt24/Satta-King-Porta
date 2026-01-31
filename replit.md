# Satta King Website

## Overview
A Satta King results website built with PHP that displays game results, charts, and news. The site includes an admin panel for managing results, games, and content.

## Project Structure
- `index.php` - Main homepage showing live results
- `admin.php` - Admin panel for managing the site
- `chart.php` - Historical results chart
- `daily-updates.php` - Daily update posts
- `news.php` - News listing page
- `news-post.php` - Individual news article page
- `page.php` - Static pages (about, contact, etc.)
- `post.php` - Individual result posts
- `config/database.php` - PostgreSQL database connection
- `config/init.php` - Database schema initialization
- `lib/scraper.php` - Data scraping utilities
- `lib/ads.php` - Ad placement utilities
- `css/` - Stylesheets
- `router.php` - PHP router for clean URLs

## Database
Uses PostgreSQL with the following tables:
- `games` - List of games and their time slots
- `satta_results` - Game results by date
- `posts` - Daily update posts
- `news_posts` - News articles
- `site_pages` - Static pages (about, contact, etc.)
- `site_settings` - Site configuration
- `ad_placements` - Ad code placements
- `scrape_logs` - Data scraping logs

## Admin Panel
Access the admin panel at `/admin.php`
- Login with the password stored in `SESSION_SECRET` environment variable
- Manage game results, games, posts, news, pages, and ads

## Environment Variables
- `DATABASE_URL` - PostgreSQL connection string (auto-configured)
- `SESSION_SECRET` - Admin panel password

## Running the Project
The site runs on PHP's built-in server:
```
php -S 0.0.0.0:5000 router.php
```

## Recent Changes
- 2026-01-31: Migrated from MySQL to PostgreSQL for Replit compatibility
