# Satta King Website

## Overview
A responsive website for displaying Satta King game results. Built with PHP, MySQL, HTML, and CSS. The website is fully responsive and works on both mobile devices and desktop computers.

## Project Structure
```
/
├── index.php          # Main homepage with results display (today + yesterday)
├── chart.php          # Individual game monthly record chart with filters
├── admin.php          # Admin panel for managing results (password protected)
├── daily-updates.php  # Lists all published daily update posts in grid view
├── post.php           # Individual post display with full SEO
├── news.php           # News listing page (grid view)
├── news-post.php      # Single news post display with full SEO
├── page.php           # Footer pages display (About, Contact, etc.)
├── sitemap.php        # XML sitemap generator for all posts
├── install.php        # Installation wizard for server setup
├── router.php         # URL routing for PHP built-in server
├── config/
│   ├── database.php   # Database connection configuration
│   └── init.php       # Database initialization script
├── lib/
│   ├── scraper.php    # Web scraping service for data import
│   └── ads.php        # Ad display helper functions
├── uploads/
│   └── news/          # Directory for news post images
└── css/
    ├── style.css      # Public pages styling
    └── admin.css      # Admin panel styling
```

## Features
- **Auto-Responsive Design**: Works on mobile, tablet, and desktop
- **Live Results Display**: Shows today and yesterday results in table format
- **Record Chart**: Per-game monthly result charts with month/year filters
- **Weekly Chart**: Displays last 7 days of results
- **Admin Panel**: Add/update results and create new games (password protected with SESSION_SECRET)
- **Web Scraping**: Import data from external websites (supports paste option for Cloudflare-protected sites)
- **Date-wise Storage**: Historical data preserved, only current day data updated on re-scrape
- **Security**: Admin panel protected with session-based authentication and CSRF tokens
- **Daily Update Posts**: Individual posts per game with SEO optimization, JSON-LD schema, 7-day history chart, and views tracking
- **News Posts**: Manual news/blog posts with full SEO, HTML content support, featured images, H1/H2/paragraph formatting
- **Footer Pages CMS**: Editable About, Contact, Disclaimer, Privacy Policy, Terms & Conditions pages
- **XML Sitemap**: Automatic sitemap generation for all published posts
- **Ad Management**: 5 placement types (header, after_result, sidebar, footer, between_posts) with enable/disable per placement
- **Post Views Tracking**: Automatic view count increment on each post visit
- **Google Analytics**: Admin configurable analytics and verification codes
- **Installation Wizard**: Easy 4-step setup wizard for server deployment

## Database Tables
1. **games**: Stores game names and time slots (UNIQUE constraint on name)
2. **satta_results**: Stores daily results for each game (UNIQUE constraint on game_name + result_date)
3. **scrape_logs**: Tracks scraping history and status
4. **posts**: Daily update posts with SEO metadata (slug, meta_description, meta_keywords, games_included, views)
5. **news_posts**: Manual news posts (title, slug, excerpt, content, featured_image, meta_title, meta_description, meta_keywords, status, views)
6. **ad_placements**: Ad code storage with placement_name, ad_code, is_active status
7. **site_pages**: Footer pages (slug, title, content, meta_title, meta_description)
8. **site_settings**: Site-wide settings (google_analytics_code, verification codes)

## How to Use
1. **View Results**: Visit the homepage to see today's results
2. **Admin Panel**: Go to `/admin.php` and login with SESSION_SECRET password
3. **Scrape Data**: 
   - Enter URL (e.g., https://satta-king-fast.com/) and click "Scrape Data"
   - If URL scraping fails (Cloudflare), use "Paste Data Option" with format: `GAME NAME | 02:30 PM | 45 | 67`
4. **Re-scrape**: Same-day re-scrapes update existing records, historical data is preserved
5. **News Posts**: Admin > News Posts > Create New Post (supports HTML content with H1, H2, paragraphs, images, links)
6. **Footer Pages**: Admin > Footer Pages to edit About, Contact, Disclaimer, Privacy, Terms pages

## Installation (For Server Deployment)
1. Upload all files to your server
2. Create a MySQL database
3. Visit `/install.php` in your browser
4. Enter database credentials (host, port, database name, user, password)
5. Set admin password
6. Click "Install Now"
7. Delete or rename install.php after installation for security

## Technical Details
- **Backend**: PHP 8.4
- **Database**: MySQL with upsert logic (ON DUPLICATE KEY UPDATE)
- **Styling**: Pure CSS with responsive media queries
- **Server**: PHP built-in server on port 5000
- **Security**: Session-based authentication with CSRF protection

## Recent Changes
- January 8, 2026: Added News section with manual post creation, grid view listing, full SEO support, HTML content with H1/H2/paragraphs
- January 8, 2026: Added Installation Wizard (install.php) for easy server deployment with step-by-step setup
- January 8, 2026: Removed Admin link from public navigation, Chart page now has full game/month/year selectors
- January 8, 2026: Added AdSense/Ad management system with 5 placement types, enable/disable per placement
- January 8, 2026: Added post views tracking with automatic increment on page visit
- January 8, 2026: Changed to individual game posts (separate post per game) with 7-day history chart
- January 8, 2026: Updated website theme to dark blue (#020d1f, #1e3a5f)
- January 8, 2026: FAQ questions now display in yellow color
- January 8, 2026: Mobile navigation toggle moved to right side
- January 8, 2026: Added Daily Update Posts system with auto-generation, SEO optimization, JSON-LD schema, and XML sitemap
- January 8, 2026: Added comprehensive SEO optimization with meta tags, keywords, FAQ section targeting 10+ Satta King keywords
- January 8, 2026: Redesigned admin panel with professional sidebar navigation
- January 8, 2026: Added Record Chart feature with month/year filters for each game
- January 8, 2026: Initial website creation with responsive design and admin security
