import os
import re
from datetime import datetime, timedelta
from flask import Flask, render_template, request, redirect, url_for, session, jsonify, Response
from dotenv import load_dotenv
from apscheduler.schedulers.background import BackgroundScheduler
import cloudscraper
from functools import wraps

load_dotenv()

app = Flask(__name__, static_folder='static', static_url_path='')
app.secret_key = os.getenv('SESSION_SECRET', 'default-secret-key')
app.config['SEND_FILE_MAX_AGE_DEFAULT'] = 0

DATABASE_URL = os.getenv('DATABASE_URL', '')
USE_MYSQL = os.getenv('MYSQL_HOST') or (not DATABASE_URL)

if USE_MYSQL:
    import pymysql
    MYSQL_CONFIG = {
        'host': os.getenv('MYSQL_HOST', 'localhost'),
        'user': os.getenv('MYSQL_USER', 'root'),
        'password': os.getenv('MYSQL_PASSWORD', ''),
        'database': os.getenv('MYSQL_DATABASE', 'sattaking'),
        'charset': 'utf8mb4',
        'cursorclass': pymysql.cursors.DictCursor
    }
    def get_db():
        return pymysql.connect(**MYSQL_CONFIG)
else:
    import psycopg2
    import psycopg2.extras
    def get_db():
        conn = psycopg2.connect(DATABASE_URL)
        return conn

def get_cursor(conn):
    if USE_MYSQL:
        return conn.cursor()
    else:
        return conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)

def get_setting(key, default=''):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT setting_value FROM site_settings WHERE setting_key = %s", (key,))
        result = cursor.fetchone()
        cursor.close()
        conn.close()
        return result['setting_value'] if result else default
    except Exception as e:
        print(f"get_setting error: {e}")
        return default

def set_setting(key, value):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        if USE_MYSQL:
            cursor.execute("""
                INSERT INTO site_settings (setting_key, setting_value) VALUES (%s, %s)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
            """, (key, value))
        else:
            cursor.execute("""
                INSERT INTO site_settings (setting_key, setting_value) VALUES (%s, %s)
                ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP
            """, (key, value))
        conn.commit()
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"Error setting {key}: {e}")

def display_ad(position):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT ad_code FROM ad_placements WHERE position = %s AND is_active = 1", (position,))
        result = cursor.fetchone()
        cursor.close()
        conn.close()
        return result['ad_code'] if result else ''
    except:
        return ''

def normalize_game_name(name):
    name = re.sub(r'\s+', ' ', name.strip())
    return name.title()

def is_valid_game_name(name):
    invalid_names = ['live', 'next', 'rest', 'board', 'section', 'header', 'title']
    return name.lower().strip() not in invalid_names and len(name) > 2

class SattaScraper:
    def __init__(self):
        self.scraper = cloudscraper.create_scraper(
            browser={'browser': 'chrome', 'platform': 'windows', 'mobile': False}
        )
    
    def fetch_url(self, url):
        try:
            response = self.scraper.get(url, timeout=30)
            if response.status_code == 200:
                return {'success': True, 'html': response.text}
            return {'success': False, 'error': f'HTTP {response.status_code}'}
        except Exception as e:
            return {'success': False, 'error': str(e)}
    
    def parse_satta_ink(self, html):
        data = []
        today = datetime.now().strftime('%Y-%m-%d')
        yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')
        
        pattern = r'<div[^>]*class=["\']result-card["\'][^>]*>.*?<span[^>]*class=["\']game-name["\'][^>]*>([^<]+)</span>.*?<span[^>]*class=["\']game-time["\'][^>]*>Draw:\s*(\d{1,2}:\d{2}\s*[AP]M)</span>.*?<div[^>]*class=["\']score-now["\'][^>]*>(\d{2}|XX)</div>.*?<div[^>]*class=["\']score-old["\'][^>]*>Yest:\s*(\d{2}|XX)</div>'
        matches = re.findall(pattern, html, re.DOTALL | re.IGNORECASE)
        
        for match in matches:
            game_name = match[0].strip()
            time_str = match[1].strip()
            today_result = match[2].strip()
            yesterday_result = match[3].strip()
            
            if not is_valid_game_name(game_name):
                continue
            
            try:
                time_obj = datetime.strptime(time_str, '%I:%M %p')
                time_24 = time_obj.strftime('%H:%M:%S')
            except:
                time_24 = '12:00:00'
            
            normalized_name = normalize_game_name(game_name)
            
            if re.match(r'^\d{2}$', yesterday_result):
                data.append({
                    'game_name': normalized_name,
                    'result': yesterday_result,
                    'result_time': time_24,
                    'result_date': yesterday
                })
            
            if re.match(r'^\d{2}$', today_result):
                data.append({
                    'game_name': normalized_name,
                    'result': today_result,
                    'result_time': time_24,
                    'result_date': today
                })
        
        return data
    
    def parse_satta_king_fast(self, html):
        data = []
        today = datetime.now().strftime('%Y-%m-%d')
        yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')
        
        pattern = r'<tr[^>]*class=[\'"][^\'"]*game-result[^\'"]*[\'"][^>]*>.*?<h3[^>]*class=[\'"][^\'"]*game-name[^\'"]*[\'"][^>]*>([^<]+)</h3>.*?<h3[^>]*class=[\'"][^\'"]*game-time[^\'"]*[\'"][^>]*>\s*at\s*(\d{1,2}:\d{2}\s*[AP]M)</h3>.*?<td[^>]*class=[\'"][^\'"]*yesterday-number[^\'"]*[\'"][^>]*>.*?<h3>(\d{2}|--|-|XX)</h3>.*?<td[^>]*class=[\'"][^\'"]*today-number[^\'"]*[\'"][^>]*>.*?<h3>(\d{2}|--|-|XX)</h3>'
        matches = re.findall(pattern, html, re.DOTALL | re.IGNORECASE)
        
        for match in matches:
            game_name = match[0].strip()
            time_str = match[1].strip()
            yesterday_result = match[2].strip()
            today_result = match[3].strip()
            
            if not is_valid_game_name(game_name):
                continue
            
            try:
                time_obj = datetime.strptime(time_str, '%I:%M %p')
                time_24 = time_obj.strftime('%H:%M:%S')
            except:
                time_24 = '12:00:00'
            
            normalized_name = normalize_game_name(game_name)
            
            if re.match(r'^\d{2}$', yesterday_result):
                data.append({
                    'game_name': normalized_name,
                    'result': yesterday_result,
                    'result_time': time_24,
                    'result_date': yesterday
                })
            
            if re.match(r'^\d{2}$', today_result):
                data.append({
                    'game_name': normalized_name,
                    'result': today_result,
                    'result_time': time_24,
                    'result_date': today
                })
        
        return data
    
    def scrape(self, url):
        result = {'success': False, 'message': '', 'records_updated': 0}
        
        fetch = self.fetch_url(url)
        if not fetch['success']:
            result['message'] = f"Failed to fetch: {fetch.get('error', 'Unknown error')}"
            return result
        
        html = fetch['html']
        
        if 'Just a moment' in html or 'cf_chl' in html:
            result['message'] = 'Cloudflare protection detected, retrying with cloudscraper...'
            return result
        
        if 'satta.ink' in url:
            data = self.parse_satta_ink(html)
        else:
            data = self.parse_satta_king_fast(html)
        
        if not data:
            result['message'] = 'No data found on the page'
            return result
        
        updated = self.save_data(data, url)
        result['success'] = True
        result['message'] = f'Successfully scraped {updated} records'
        result['records_updated'] = updated
        
        return result
    
    def save_data(self, data, source_url):
        updated = 0
        today = datetime.now().strftime('%Y-%m-%d')
        
        try:
            conn = get_db()
            with conn.cursor() as cursor:
                for row in data:
                    cursor.execute("""
                        INSERT IGNORE INTO games (name, time_slot) VALUES (%s, %s)
                    """, (row['game_name'], row['result_time']))
                    
                    if row['result_date'] == today:
                        cursor.execute("""
                            INSERT INTO satta_results (game_name, result, result_time, result_date, source_url, scraped_at)
                            VALUES (%s, %s, %s, %s, %s, CURRENT_TIMESTAMP)
                            ON DUPLICATE KEY UPDATE 
                                result = VALUES(result),
                                result_time = VALUES(result_time),
                                source_url = VALUES(source_url),
                                scraped_at = CURRENT_TIMESTAMP
                        """, (row['game_name'], row['result'], row['result_time'], row['result_date'], source_url))
                    else:
                        cursor.execute("""
                            INSERT IGNORE INTO satta_results (game_name, result, result_time, result_date, source_url, scraped_at)
                            VALUES (%s, %s, %s, %s, %s, CURRENT_TIMESTAMP)
                        """, (row['game_name'], row['result'], row['result_time'], row['result_date'], source_url))
                    
                    updated += 1
                
                conn.commit()
            conn.close()
        except Exception as e:
            print(f"Error saving data: {e}")
        
        return updated

def run_auto_scrape():
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM scrape_sources WHERE is_active = 1")
            sources = cursor.fetchall()
        conn.close()
        
        if not sources:
            return
        
        scraper = SattaScraper()
        for source in sources:
            try:
                result = scraper.scrape(source['url'])
                if result['success']:
                    conn = get_db()
                    with conn.cursor() as cursor:
                        cursor.execute("UPDATE scrape_sources SET last_scraped = CURRENT_TIMESTAMP WHERE id = %s", (source['id'],))
                        conn.commit()
                    conn.close()
            except Exception as e:
                print(f"Error scraping {source['url']}: {e}")
        
        set_setting('last_auto_scrape', datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
    except Exception as e:
        print(f"Auto scrape error: {e}")

def should_run_auto_scrape():
    last_run = get_setting('last_auto_scrape')
    if not last_run:
        return True
    try:
        last_time = datetime.strptime(last_run, '%Y-%m-%d %H:%M:%S')
        return (datetime.now() - last_time).total_seconds() >= 1800
    except:
        return True

def trigger_auto_scrape_if_needed():
    if should_run_auto_scrape():
        run_auto_scrape()

@app.after_request
def add_header(response):
    response.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate'
    response.headers['Pragma'] = 'no-cache'
    response.headers['Expires'] = '0'
    return response

@app.route('/')
def index():
    trigger_auto_scrape_if_needed()
    
    today = datetime.now().strftime('%Y-%m-%d')
    yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')
    
    last_update = get_setting('last_auto_scrape')
    try:
        last_update_formatted = datetime.strptime(last_update, '%Y-%m-%d %H:%M:%S').strftime('%I:%M %p') if last_update else 'Not yet'
    except:
        last_update_formatted = 'Not yet'
    
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        
        if USE_MYSQL:
            cursor.execute("""
                SELECT sr.game_name, sr.result, DATE_FORMAT(sr.result_date, '%%Y-%%m-%%d') as result_date, 
                       sr.result_time, g.time_slot
                FROM satta_results sr 
                LEFT JOIN games g ON g.name = sr.game_name
                WHERE sr.result_date IN (CURDATE(), CURDATE() - INTERVAL 1 DAY)
                ORDER BY g.time_slot ASC, sr.game_name ASC
            """)
        else:
            cursor.execute("""
                SELECT sr.game_name, sr.result, TO_CHAR(sr.result_date, 'YYYY-MM-DD') as result_date, 
                       sr.result_time, g.time_slot
                FROM satta_results sr 
                LEFT JOIN games g ON g.name = sr.game_name
                WHERE sr.result_date IN (CURRENT_DATE, CURRENT_DATE - INTERVAL '1 day')
                ORDER BY g.time_slot ASC, sr.game_name ASC
            """)
        all_results = cursor.fetchall()
        
        if USE_MYSQL:
            cursor.execute("""
                SELECT result_date, game_name, result 
                FROM satta_results 
                WHERE result_date >= CURDATE() - INTERVAL 7 DAY
                ORDER BY result_date DESC, game_name
            """)
        else:
            cursor.execute("""
                SELECT result_date, game_name, result 
                FROM satta_results 
                WHERE result_date >= CURRENT_DATE - INTERVAL '7 days'
                ORDER BY result_date DESC, game_name
            """)
        chart_data = cursor.fetchall()
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"Database error: {e}")
        all_results = []
        chart_data = []
    
    game_results = {}
    for r in all_results:
        game_name = r['game_name']
        if game_name not in game_results:
            game_results[game_name] = {
                'name': game_name,
                'time': r['result_time'],
                'today': '--',
                'yesterday': '--',
                'time_slot': r['time_slot'] or '23:59:00'
            }
        if r['result_date'] == today:
            game_results[game_name]['today'] = r['result']
        else:
            game_results[game_name]['yesterday'] = r['result']
    
    game_results = sorted(game_results.values(), key=lambda x: x['time_slot'])
    
    chart_by_date = {}
    for row in chart_data:
        date = str(row['result_date'])
        if date not in chart_by_date:
            chart_by_date[date] = {}
        chart_by_date[date][row['game_name']] = row['result']
    
    return render_template('index.html',
        game_results=game_results,
        chart_by_date=chart_by_date,
        today=today,
        yesterday=yesterday,
        last_update_formatted=last_update_formatted,
        current_date=datetime.now().strftime('%d %B %Y'),
        adsense_auto_ads=get_setting('adsense_auto_ads'),
        analytics_code=get_setting('google_analytics_code'),
        google_verify=get_setting('meta_verification_google'),
        bing_verify=get_setting('meta_verification_bing'),
        header_ad=display_ad('header_ad'),
        after_result_ad=display_ad('after_result'),
        footer_ad=display_ad('footer_ad')
    )

@app.route('/post/<slug>')
def post(slug):
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM posts WHERE slug = %s", (slug,))
            post_data = cursor.fetchone()
            
            if not post_data:
                conn.close()
                return "Post not found", 404
            
            cursor.execute("UPDATE posts SET views = views + 1 WHERE id = %s", (post_data['id'],))
            conn.commit()
            
            post_date = post_data['post_date']
            game_name = post_data['games_included']
            
            cursor.execute("""
                SELECT game_name, result, result_time 
                FROM satta_results 
                WHERE result_date = %s AND game_name = %s
            """, (post_date, game_name))
            today_result = cursor.fetchone()
            
            cursor.execute("""
                SELECT result_date, result 
                FROM satta_results 
                WHERE game_name = %s AND result IS NOT NULL AND result != ''
                ORDER BY result_date DESC LIMIT 7
            """, (game_name,))
            weekly_results = cursor.fetchall()
            
            cursor.execute("""
                SELECT slug, title, post_date FROM posts 
                WHERE games_included = %s AND slug != %s
                ORDER BY post_date DESC LIMIT 5
            """, (game_name, slug))
            related_posts = cursor.fetchall()
            
            cursor.execute("""
                SELECT slug, title, games_included FROM posts 
                WHERE post_date = %s AND slug != %s
                ORDER BY games_included
            """, (post_date, slug))
            other_games = cursor.fetchall()
            
            cursor.execute("""
                SELECT result FROM satta_results 
                WHERE game_name = %s AND result IS NOT NULL AND result != '' AND result != 'XX'
                ORDER BY result_date DESC LIMIT 30
            """, (game_name,))
            monthly_results = [r['result'] for r in cursor.fetchall()]
            
            cursor.execute("SELECT COUNT(DISTINCT game_name) as cnt FROM satta_results WHERE result IS NOT NULL")
            total_games = cursor.fetchone()['cnt']
        
        conn.close()
        
        odd_count = sum(1 for r in monthly_results if int(r) % 2 != 0)
        even_count = len(monthly_results) - odd_count
        
        digit_freq = {}
        for r in monthly_results:
            last_digit = int(r) % 10
            digit_freq[last_digit] = digit_freq.get(last_digit, 0) + 1
        hot_digits = sorted(digit_freq.keys(), key=lambda x: digit_freq[x], reverse=True)[:3]
        
        formatted_date = datetime.strptime(str(post_date), '%Y-%m-%d').strftime('%d %B %Y')
        day_name = datetime.strptime(str(post_date), '%Y-%m-%d').strftime('%A')
        month_name = datetime.strptime(str(post_date), '%Y-%m-%d').strftime('%B')
        year = datetime.strptime(str(post_date), '%Y-%m-%d').strftime('%Y')
        
        has_valid_result = today_result and today_result['result'] and today_result['result'] not in ['XX', 'Waiting', '--']
        
        return render_template('post.html',
            post=post_data,
            today_result=today_result,
            has_valid_result=has_valid_result,
            weekly_results=weekly_results,
            related_posts=related_posts,
            other_games=other_games,
            monthly_results=monthly_results,
            total_games=total_games,
            odd_count=odd_count,
            even_count=even_count,
            hot_digits=hot_digits,
            formatted_date=formatted_date,
            day_name=day_name,
            month_name=month_name,
            year=year,
            game_name=game_name,
            adsense_auto_ads=get_setting('adsense_auto_ads')
        )
    except Exception as e:
        print(f"Post error: {e}")
        return "Error loading post", 500

@app.route('/chart')
def chart():
    game_name = request.args.get('game', '')
    selected_month = int(request.args.get('month', datetime.now().month))
    selected_year = int(request.args.get('year', datetime.now().year))
    
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT DISTINCT name FROM games ORDER BY name")
            all_games = [r['name'] for r in cursor.fetchall()]
            
            if not all_games:
                cursor.execute("SELECT DISTINCT game_name FROM satta_results ORDER BY game_name")
                all_games = [r['game_name'] for r in cursor.fetchall()]
            
            if not game_name and all_games:
                game_name = all_games[0]
            
            cursor.execute("""
                SELECT DISTINCT YEAR(result_date) as year 
                FROM satta_results ORDER BY year DESC
            """)
            available_years = [r['year'] for r in cursor.fetchall()]
            
            if not available_years:
                current_year = datetime.now().year
                available_years = [current_year, current_year - 1, current_year - 2]
            
            results = []
            result_map = {}
            if game_name:
                cursor.execute("""
                    SELECT result_date, result, result_time 
                    FROM satta_results 
                    WHERE game_name = %s 
                    AND MONTH(result_date) = %s 
                    AND YEAR(result_date) = %s
                    ORDER BY result_date DESC
                """, (game_name, selected_month, selected_year))
                results = cursor.fetchall()
                
                for r in results:
                    result_map[str(r['result_date'])] = r['result']
        
        conn.close()
        
        import calendar
        days_in_month = calendar.monthrange(selected_year, selected_month)[1]
        month_name = calendar.month_name[selected_month]
        
        months = {i: calendar.month_name[i] for i in range(1, 13)}
        
        odd_count = sum(1 for r in results if r['result'] and r['result'] != '--' and int(r['result']) % 2 != 0)
        even_count = len([r for r in results if r['result'] and r['result'] != '--']) - odd_count
        
        digit_freq = [0] * 10
        for r in results:
            if r['result'] and r['result'] != '--':
                digit_freq[int(r['result']) % 10] += 1
        hot_digits = sorted(range(10), key=lambda x: digit_freq[x], reverse=True)[:3]
        
        return render_template('chart.html',
            all_games=all_games,
            game_name=game_name,
            selected_month=selected_month,
            selected_year=selected_year,
            available_years=available_years,
            results=results,
            result_map=result_map,
            days_in_month=days_in_month,
            month_name=month_name,
            months=months,
            odd_count=odd_count,
            even_count=even_count,
            hot_digits=hot_digits,
            total_games=len(all_games),
            adsense_auto_ads=get_setting('adsense_auto_ads')
        )
    except Exception as e:
        print(f"Chart error: {e}")
        return "Error loading chart", 500

@app.route('/daily-updates')
def daily_updates():
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT slug, title, meta_description, post_date, views, games_included 
                FROM posts ORDER BY post_date DESC, created_at DESC LIMIT 50
            """)
            posts = cursor.fetchall()
        conn.close()
        
        return render_template('daily_updates.html',
            posts=posts,
            adsense_auto_ads=get_setting('adsense_auto_ads')
        )
    except Exception as e:
        print(f"Daily updates error: {e}")
        return "Error loading posts", 500

@app.route('/news')
def news():
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT slug, title, meta_description, created_at, views 
                FROM news_posts WHERE is_published = 1 
                ORDER BY created_at DESC
            """)
            news_list = cursor.fetchall()
        conn.close()
        
        return render_template('news.html',
            news_list=news_list,
            adsense_auto_ads=get_setting('adsense_auto_ads')
        )
    except Exception as e:
        print(f"News error: {e}")
        return "Error loading news", 500

@app.route('/news/<slug>')
def news_post(slug):
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM news_posts WHERE slug = %s AND is_published = 1", (slug,))
            news_data = cursor.fetchone()
            
            if not news_data:
                conn.close()
                return "News not found", 404
            
            cursor.execute("UPDATE news_posts SET views = views + 1 WHERE id = %s", (news_data['id'],))
            conn.commit()
        conn.close()
        
        return render_template('news_post.html',
            news=news_data,
            adsense_auto_ads=get_setting('adsense_auto_ads')
        )
    except Exception as e:
        print(f"News post error: {e}")
        return "Error loading news", 500

@app.route('/page/<slug>')
def static_page(slug):
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM site_pages WHERE slug = %s", (slug,))
            page_data = cursor.fetchone()
        conn.close()
        
        if not page_data:
            return "Page not found", 404
        
        return render_template('page.html',
            page=page_data,
            adsense_auto_ads=get_setting('adsense_auto_ads')
        )
    except Exception as e:
        print(f"Page error: {e}")
        return "Error loading page", 500

@app.route('/sitemap.xml')
def sitemap():
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT slug, post_date FROM posts ORDER BY post_date DESC")
            posts = cursor.fetchall()
            
            cursor.execute("SELECT slug FROM news_posts WHERE is_published = 1")
            news = cursor.fetchall()
            
            cursor.execute("SELECT slug FROM site_pages")
            pages = cursor.fetchall()
            
            cursor.execute("SELECT DISTINCT name FROM games")
            games = cursor.fetchall()
        conn.close()
        
        xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
        xml += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        
        xml += '<url><loc>https://sattaking.com.im/</loc><priority>1.0</priority></url>\n'
        xml += '<url><loc>https://sattaking.com.im/chart</loc><priority>0.9</priority></url>\n'
        xml += '<url><loc>https://sattaking.com.im/daily-updates</loc><priority>0.8</priority></url>\n'
        xml += '<url><loc>https://sattaking.com.im/news</loc><priority>0.7</priority></url>\n'
        
        for post in posts:
            xml += f'<url><loc>https://sattaking.com.im/post/{post["slug"]}</loc><priority>0.6</priority></url>\n'
        
        for n in news:
            xml += f'<url><loc>https://sattaking.com.im/news/{n["slug"]}</loc><priority>0.5</priority></url>\n'
        
        for p in pages:
            xml += f'<url><loc>https://sattaking.com.im/page/{p["slug"]}</loc><priority>0.4</priority></url>\n'
        
        for g in games:
            xml += f'<url><loc>https://sattaking.com.im/chart?game={g["name"]}</loc><priority>0.5</priority></url>\n'
        
        xml += '</urlset>'
        
        return Response(xml, mimetype='application/xml')
    except Exception as e:
        return "Error generating sitemap", 500

def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if 'admin_logged_in' not in session:
            return redirect(url_for('admin_login'))
        return f(*args, **kwargs)
    return decorated_function

@app.route('/admin', methods=['GET', 'POST'])
def admin_login():
    if request.method == 'POST':
        password = request.form.get('password', '')
        if password == os.getenv('SESSION_SECRET', ''):
            session['admin_logged_in'] = True
            return redirect(url_for('admin_dashboard'))
        return render_template('admin_login.html', error='Invalid password')
    return render_template('admin_login.html')

@app.route('/admin/dashboard')
@login_required
def admin_dashboard():
    page = request.args.get('page', 'dashboard')
    
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT COUNT(*) as cnt FROM games")
            total_games = cursor.fetchone()['cnt']
            
            cursor.execute("SELECT COUNT(*) as cnt FROM satta_results WHERE result_date = CURDATE()")
            today_results = cursor.fetchone()['cnt']
            
            cursor.execute("SELECT COUNT(*) as cnt FROM posts")
            total_posts = cursor.fetchone()['cnt']
            
            cursor.execute("SELECT * FROM games ORDER BY name")
            games = cursor.fetchall()
            
            cursor.execute("SELECT * FROM posts ORDER BY post_date DESC, created_at DESC LIMIT 50")
            posts = cursor.fetchall()
            
            cursor.execute("SELECT * FROM scrape_sources ORDER BY created_at DESC")
            scrape_sources = cursor.fetchall()
            
            cursor.execute("SELECT * FROM news_posts ORDER BY created_at DESC")
            news_posts = cursor.fetchall()
            
            cursor.execute("SELECT * FROM site_pages ORDER BY title")
            site_pages = cursor.fetchall()
            
            cursor.execute("SELECT * FROM ad_placements ORDER BY position")
            ad_placements = cursor.fetchall()
        conn.close()
        
        return render_template('admin.html',
            page=page,
            total_games=total_games,
            today_results=today_results,
            total_posts=total_posts,
            games=games,
            posts=posts,
            scrape_sources=scrape_sources,
            news_posts=news_posts,
            site_pages=site_pages,
            ad_placements=ad_placements,
            last_auto_scrape=get_setting('last_auto_scrape'),
            adsense_publisher_id=get_setting('adsense_publisher_id'),
            adsense_auto_ads=get_setting('adsense_auto_ads'),
            auto_publish_enabled=get_setting('auto_publish_enabled', '1'),
            auto_publish_hour=get_setting('auto_publish_hour', '1')
        )
    except Exception as e:
        print(f"Admin error: {e}")
        return f"Error: {e}", 500

@app.route('/admin/scrape-now', methods=['POST'])
@login_required
def admin_scrape_now():
    run_auto_scrape()
    return redirect(url_for('admin_dashboard', page='auto-scrape'))

@app.route('/admin/add-source', methods=['POST'])
@login_required
def admin_add_source():
    url = request.form.get('url', '').strip()
    if url:
        try:
            conn = get_db()
            with conn.cursor() as cursor:
                cursor.execute("INSERT INTO scrape_sources (name, url, is_active) VALUES (%s, %s, 1)", 
                             (url, url))
                conn.commit()
            conn.close()
        except Exception as e:
            print(f"Error adding source: {e}")
    return redirect(url_for('admin_dashboard', page='auto-scrape'))

@app.route('/admin/logout')
def admin_logout():
    session.pop('admin_logged_in', None)
    return redirect(url_for('admin_login'))

scheduler = BackgroundScheduler()
scheduler.add_job(func=run_auto_scrape, trigger="interval", minutes=30)
scheduler.start()

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
