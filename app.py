import os
import re
import json
from datetime import datetime, timedelta
from flask import Flask, render_template, request, redirect, url_for, session, jsonify, Response, send_from_directory
from werkzeug.utils import secure_filename
from dotenv import load_dotenv
from apscheduler.schedulers.background import BackgroundScheduler
import cloudscraper
from functools import wraps
import pytz
from pywebpush import webpush, WebPushException
from py_vapid import Vapid

IST = pytz.timezone('Asia/Kolkata')

load_dotenv()

app = Flask(__name__, static_folder='static', static_url_path='')
app.secret_key = os.getenv('SESSION_SECRET', 'default-secret-key')
app.config['SEND_FILE_MAX_AGE_DEFAULT'] = 0
app.config['UPLOAD_FOLDER'] = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'static', 'uploads')
app.config['MAX_CONTENT_LENGTH'] = 5 * 1024 * 1024

ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'webp'}

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

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

@app.context_processor
def inject_branding():
    return {
        'site_logo': get_setting('site_logo'),
        'site_favicon': get_setting('site_favicon'),
        'site_icon': get_setting('site_icon')
    }

def get_vapid_keys():
    """Get or generate VAPID keys for push notifications"""
    import base64
    from cryptography.hazmat.primitives import serialization
    from cryptography.hazmat.primitives.asymmetric import ec
    from cryptography.hazmat.backends import default_backend
    
    private_key = get_setting('vapid_private_key')
    public_key = get_setting('vapid_public_key')
    
    if not private_key or not public_key:
        key = ec.generate_private_key(ec.SECP256R1(), default_backend())
        priv_bytes = key.private_numbers().private_value.to_bytes(32, 'big')
        private_key = base64.urlsafe_b64encode(priv_bytes).decode('utf-8').rstrip('=')
        pub_bytes = key.public_key().public_bytes(
            serialization.Encoding.X962, 
            serialization.PublicFormat.UncompressedPoint
        )
        public_key = base64.urlsafe_b64encode(pub_bytes).decode('utf-8').rstrip('=')
        set_setting('vapid_private_key', private_key)
        set_setting('vapid_public_key', public_key)
    
    return private_key, public_key

def send_push_notification(title, body, url='/', icon='/logo.png', notification_type='manual'):
    """Send push notification to all active subscribers"""
    private_key, public_key = get_vapid_keys()
    
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT id, endpoint, p256dh, auth FROM push_subscribers WHERE is_active = TRUE")
        subscribers = cursor.fetchall()
        cursor.close()
        conn.close()
        
        if not subscribers:
            return {'success': 0, 'fail': 0, 'total': 0}
        
        base_url = get_setting('site_url') or 'https://sattaking.com.im'
        payload = json.dumps({
            'title': title,
            'body': body,
            'url': url if url.startswith('http') else base_url + url,
            'icon': icon if icon.startswith('http') else base_url + icon
        })
        
        success_count = 0
        fail_count = 0
        failed_endpoints = []
        
        for sub in subscribers:
            try:
                webpush(
                    subscription_info={
                        'endpoint': sub['endpoint'],
                        'keys': {
                            'p256dh': sub['p256dh'],
                            'auth': sub['auth']
                        }
                    },
                    data=payload,
                    vapid_private_key=private_key,
                    vapid_claims={'sub': 'mailto:admin@sattaking.com.im'}
                )
                success_count += 1
            except WebPushException as e:
                fail_count += 1
                if e.response and e.response.status_code in [404, 410]:
                    failed_endpoints.append(sub['id'])
            except Exception as e:
                fail_count += 1
        
        if failed_endpoints:
            conn = get_db()
            cursor = get_cursor(conn)
            for sub_id in failed_endpoints:
                cursor.execute("UPDATE push_subscribers SET is_active = FALSE WHERE id = %s", (sub_id,))
            conn.commit()
            cursor.close()
            conn.close()
        
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("""
            INSERT INTO notification_logs (title, body, url, total_sent, success_count, fail_count, notification_type)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """, (title, body, url, len(subscribers), success_count, fail_count, notification_type))
        conn.commit()
        cursor.close()
        conn.close()
        
        return {'success': success_count, 'fail': fail_count, 'total': len(subscribers)}
    except Exception as e:
        print(f"Push notification error: {e}")
        return {'success': 0, 'fail': 0, 'total': 0, 'error': str(e)}

def check_redirect(path):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT new_url, redirect_type FROM url_redirects WHERE old_url = %s AND is_active = TRUE", (path,))
        result = cursor.fetchone()
        cursor.close()
        conn.close()
        return result
    except:
        return None

def display_ad(position):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT ad_code FROM ad_placements WHERE placement_name = %s AND is_active = 1", (position,))
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
        today = datetime.now(IST).strftime('%Y-%m-%d')
        yesterday = (datetime.now(IST) - timedelta(days=1)).strftime('%Y-%m-%d')
        
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
        today = datetime.now(IST).strftime('%Y-%m-%d')
        yesterday = (datetime.now(IST) - timedelta(days=1)).strftime('%Y-%m-%d')
        
        game_rows = re.findall(r'<tr[^>]*class=["\'][^"\']*game-result[^"\']*["\'][^>]*>(.*?)</tr>', html, re.DOTALL | re.IGNORECASE)
        
        display_order = 0
        for row in game_rows:
            display_order += 1
            game_name_match = re.search(r'<h3[^>]*class=["\']game-name["\'][^>]*>([^<]+)</h3>', row, re.IGNORECASE)
            time_match = re.search(r'<h3[^>]*class=["\']game-time["\'][^>]*>\s*at\s*(\d{1,2}:\d{2}\s*[AP]M)</h3>', row, re.IGNORECASE)
            yesterday_match = re.search(r'<td[^>]*class=["\']yesterday-number["\'][^>]*>.*?<h3>([^<]+)</h3>', row, re.DOTALL | re.IGNORECASE)
            today_match = re.search(r'<td[^>]*class=["\']today-number["\'][^>]*>.*?<h3>([^<]+)</h3>', row, re.DOTALL | re.IGNORECASE)
            
            if not game_name_match or not time_match:
                continue
            
            game_name = game_name_match.group(1).strip()
            time_str = time_match.group(1).strip()
            yesterday_result = yesterday_match.group(1).strip() if yesterday_match else '--'
            today_result = today_match.group(1).strip() if today_match else '--'
            
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
                    'result_date': yesterday,
                    'display_order': display_order
                })
            
            if re.match(r'^\d{2}$', today_result):
                data.append({
                    'game_name': normalized_name,
                    'result': today_result,
                    'result_time': time_24,
                    'result_date': today,
                    'display_order': display_order
                })
        
        print(f"Parsed {len(data)} game results from satta-king-fast.com")
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
        today = datetime.now(IST).strftime('%Y-%m-%d')
        new_results = []
        
        try:
            conn = get_db()
            cursor = get_cursor(conn)
            
            cursor.execute("DELETE FROM games")
            
            for row in data:
                display_order = row.get('display_order', 999)
                if USE_MYSQL:
                    cursor.execute("""
                        INSERT INTO games (name, time_slot, display_order) VALUES (%s, %s, %s)
                        ON DUPLICATE KEY UPDATE time_slot = VALUES(time_slot), display_order = VALUES(display_order)
                    """, (row['game_name'], row['result_time'], display_order))
                else:
                    cursor.execute("""
                        INSERT INTO games (name, time_slot, display_order) VALUES (%s, %s, %s)
                        ON CONFLICT (name) DO UPDATE SET time_slot = EXCLUDED.time_slot, display_order = EXCLUDED.display_order
                    """, (row['game_name'], row['result_time'], display_order))
                
                if row['result_date'] == today and row['result'] and row['result'] not in ['XX', '--', 'Waiting']:
                    cursor.execute("SELECT result FROM satta_results WHERE game_name = %s AND result_date = %s", 
                                   (row['game_name'], row['result_date']))
                    existing = cursor.fetchone()
                    old_result = existing['result'] if existing else None
                    
                    if old_result != row['result'] and old_result in [None, 'XX', '--', 'Waiting']:
                        new_results.append({'game': row['game_name'], 'result': row['result']})
                    
                    if USE_MYSQL:
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
                            INSERT INTO satta_results (game_name, result, result_time, result_date, source_url, scraped_at)
                            VALUES (%s, %s, %s, %s, %s, CURRENT_TIMESTAMP)
                            ON CONFLICT (game_name, result_date) DO UPDATE SET
                                result = EXCLUDED.result,
                                result_time = EXCLUDED.result_time,
                                source_url = EXCLUDED.source_url,
                                scraped_at = CURRENT_TIMESTAMP
                        """, (row['game_name'], row['result'], row['result_time'], row['result_date'], source_url))
                elif row['result_date'] != today:
                    if USE_MYSQL:
                        cursor.execute("""
                            INSERT IGNORE INTO satta_results (game_name, result, result_time, result_date, source_url, scraped_at)
                            VALUES (%s, %s, %s, %s, %s, CURRENT_TIMESTAMP)
                        """, (row['game_name'], row['result'], row['result_time'], row['result_date'], source_url))
                    else:
                        cursor.execute("""
                            INSERT INTO satta_results (game_name, result, result_time, result_date, source_url, scraped_at)
                            VALUES (%s, %s, %s, %s, %s, CURRENT_TIMESTAMP)
                            ON CONFLICT (game_name, result_date) DO NOTHING
                        """, (row['game_name'], row['result'], row['result_time'], row['result_date'], source_url))
                
                updated += 1
            
            conn.commit()
            cursor.close()
            conn.close()
            
            if new_results and get_setting('push_on_result', '1') == '1':
                for nr in new_results[:5]:
                    send_push_notification(
                        title=f"🎯 {nr['game']} Result: {nr['result']}",
                        body=f"Today's {nr['game']} result is {nr['result']}. Check all results now!",
                        url='/',
                        notification_type='result'
                    )
        except Exception as e:
            print(f"Error saving data: {e}")
        
        return updated

def run_auto_scrape():
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT * FROM scrape_sources WHERE is_active = 1")
        sources = cursor.fetchall()
        cursor.close()
        conn.close()
        
        if not sources:
            return
        
        scraper = SattaScraper()
        for source in sources:
            try:
                result = scraper.scrape(source['url'])
                if result['success']:
                    conn = get_db()
                    cursor = get_cursor(conn)
                    cursor.execute("UPDATE scrape_sources SET last_scraped_at = CURRENT_TIMESTAMP WHERE id = %s", (source['id'],))
                    conn.commit()
                    cursor.close()
                    conn.close()
            except Exception as e:
                print(f"Error scraping {source['url']}: {e}")
        
        set_setting('last_auto_scrape', datetime.now(IST).strftime('%Y-%m-%d %H:%M:%S'))
    except Exception as e:
        print(f"Auto scrape error: {e}")

def should_run_auto_scrape():
    last_run = get_setting('last_auto_scrape')
    if not last_run:
        return True
    try:
        last_time = datetime.strptime(last_run, '%Y-%m-%d %H:%M:%S')
        return (datetime.now(IST).replace(tzinfo=None) - last_time).total_seconds() >= 1800
    except:
        return True

def trigger_auto_scrape_if_needed():
    if should_run_auto_scrape():
        run_auto_scrape()

def create_daily_posts_for_all_games():
    """Create posts for ALL games with Waiting result at scheduled time"""
    try:
        today = datetime.now(IST).date()
        today_str = today.strftime('%Y-%m-%d')
        formatted_date = today.strftime('%-d %b %Y')
        
        conn = get_db()
        cursor = get_cursor(conn)
        
        cursor.execute("SELECT DISTINCT game_name FROM games WHERE is_active = 1 ORDER BY game_name")
        games = cursor.fetchall()
        
        posts_created = 0
        for game in games:
            game_name = game['game_name']
            slug = f"{game_name.lower().replace(' ', '-').replace('/', '-')}-satta-king-result-{today.strftime('%-d').lower()}-{today.strftime('%B').lower()}-{today.strftime('%Y')}"
            slug = re.sub(r'[^a-z0-9-]', '', slug)
            slug = re.sub(r'-+', '-', slug)
            
            title = f"{game_name} Satta King Result {formatted_date}"
            meta_desc = f"Check {game_name} Satta King Result for {formatted_date}. Get live {game_name} result, chart, and fast updates."
            meta_keywords = f"{game_name}, satta king, result, {formatted_date}, chart, live result"
            
            cursor.execute("SELECT id FROM posts WHERE slug = %s", (slug,))
            existing = cursor.fetchone()
            
            if not existing:
                if USE_MYSQL:
                    cursor.execute("""
                        INSERT INTO posts (title, slug, post_date, meta_description, meta_keywords, games_included, views)
                        VALUES (%s, %s, %s, %s, %s, %s, 0)
                    """, (title, slug, today_str, meta_desc, meta_keywords, game_name))
                else:
                    cursor.execute("""
                        INSERT INTO posts (title, slug, post_date, meta_description, meta_keywords, games_included, views)
                        VALUES (%s, %s, %s, %s, %s, %s, 0)
                    """, (title, slug, today_str, meta_desc, meta_keywords, game_name))
                posts_created += 1
        
        conn.commit()
        cursor.close()
        conn.close()
        
        set_setting('last_daily_posts_created', today_str)
        print(f"Created {posts_created} daily posts for {today_str}")
        return posts_created
    except Exception as e:
        print(f"Error creating daily posts: {e}")
        return 0

def run_daily_post_scheduler():
    """Check if it's time to create daily posts based on scheduled time"""
    try:
        enabled = get_setting('daily_post_enabled', '1')
        if enabled != '1':
            return
        
        scheduled_hour = int(get_setting('daily_post_hour', '1'))
        scheduled_minute = int(get_setting('daily_post_minute', '0'))
        
        now = datetime.now(IST)
        today_str = now.strftime('%Y-%m-%d')
        
        last_created = get_setting('last_daily_posts_created', '')
        
        if last_created == today_str:
            return
        
        if now.hour == scheduled_hour and now.minute >= scheduled_minute:
            print(f"Running daily post creation at {now.strftime('%H:%M')} IST")
            create_daily_posts_for_all_games()
    except Exception as e:
        print(f"Daily post scheduler error: {e}")

@app.after_request
def add_header(response):
    response.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate'
    response.headers['Pragma'] = 'no-cache'
    response.headers['Expires'] = '0'
    return response

@app.before_request
def handle_redirects():
    path = request.path
    if path.startswith('/static') or path.startswith('/admin') or path.startswith('/api'):
        return None
    redir = check_redirect(path)
    if redir:
        return redirect(redir['new_url'], code=redir['redirect_type'])
    return None

@app.route('/')
def index():
    trigger_auto_scrape_if_needed()
    
    today = datetime.now(IST).strftime('%Y-%m-%d')
    yesterday = (datetime.now(IST) - timedelta(days=1)).strftime('%Y-%m-%d')
    
    last_update = get_setting('last_auto_scrape')
    try:
        last_update_formatted = datetime.strptime(last_update, '%Y-%m-%d %H:%M:%S').strftime('%I:%M %p IST') if last_update else 'Not yet'
    except:
        last_update_formatted = 'Not yet'
    
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        
        if USE_MYSQL:
            cursor.execute("""
                SELECT sr.game_name, sr.result, DATE_FORMAT(sr.result_date, '%%Y-%%m-%%d') as result_date, 
                       sr.result_time, g.time_slot, g.display_order
                FROM satta_results sr 
                INNER JOIN games g ON g.name = sr.game_name
                WHERE sr.result_date IN (CURDATE(), CURDATE() - INTERVAL 1 DAY)
                ORDER BY g.display_order ASC, sr.game_name ASC
            """)
        else:
            cursor.execute("""
                SELECT sr.game_name, sr.result, TO_CHAR(sr.result_date, 'YYYY-MM-DD') as result_date, 
                       sr.result_time, g.time_slot, g.display_order
                FROM satta_results sr 
                INNER JOIN games g ON g.name = sr.game_name
                WHERE sr.result_date IN (CURRENT_DATE, CURRENT_DATE - INTERVAL '1 day')
                ORDER BY g.display_order ASC, sr.game_name ASC
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
                'time_slot': r['time_slot'] or '23:59:00',
                'display_order': r['display_order'] or 999
            }
        if r['result_date'] == today:
            game_results[game_name]['today'] = r['result']
        else:
            game_results[game_name]['yesterday'] = r['result']
    
    game_results = sorted(game_results.values(), key=lambda x: x['display_order'])
    
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
        current_date=datetime.now(IST).strftime('%d %B %Y'),
        adsense_auto_ads=get_setting('adsense_auto_ads'),
        adsense_verification=get_setting('adsense_verification'),
        analytics_code=get_setting('google_analytics_code'),
        google_verify=get_setting('meta_verification_google'),
        bing_verify=get_setting('meta_verification_bing'),
        header_ad=display_ad('header_banner'),
        below_title_ad=display_ad('below_title'),
        in_content_1_ad=display_ad('in_content_1'),
        in_content_2_ad=display_ad('in_content_2'),
        before_footer_ad=display_ad('before_footer'),
        push_prompt_title=get_setting('push_prompt_title'),
        push_prompt_message=get_setting('push_prompt_message'),
        site_url=get_setting('site_url', 'https://sattaking.com.im')
    )

@app.route('/post/<slug>')
def post(slug):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT * FROM posts WHERE slug = %s", (slug,))
        post_data = cursor.fetchone()
        
        if not post_data:
            cursor.close()
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
        
        cursor.close()
        conn.close()
        
        odd_count = sum(1 for r in monthly_results if int(r) % 2 != 0)
        even_count = len(monthly_results) - odd_count
        
        digit_freq = {}
        first_digit_freq = {}
        sum_total = 0
        for r in monthly_results:
            num = int(r)
            sum_total += num
            last_digit = num % 10
            first_digit = num // 10 if num >= 10 else num
            digit_freq[last_digit] = digit_freq.get(last_digit, 0) + 1
            first_digit_freq[first_digit] = first_digit_freq.get(first_digit, 0) + 1
        
        hot_digits = sorted(digit_freq.keys(), key=lambda x: digit_freq[x], reverse=True)[:3]
        cold_digits = sorted(digit_freq.keys(), key=lambda x: digit_freq[x])[:3]
        hot_first_digits = sorted(first_digit_freq.keys(), key=lambda x: first_digit_freq[x], reverse=True)[:3]
        avg_result = round(sum_total / len(monthly_results), 1) if monthly_results else 0
        
        high_count = sum(1 for r in monthly_results if int(r) >= 50)
        low_count = len(monthly_results) - high_count
        
        ranges = {'00-24': 0, '25-49': 0, '50-74': 0, '75-99': 0}
        for r in monthly_results:
            num = int(r)
            if num < 25: ranges['00-24'] += 1
            elif num < 50: ranges['25-49'] += 1
            elif num < 75: ranges['50-74'] += 1
            else: ranges['75-99'] += 1
        
        consecutive_same = 0
        max_consecutive = 0
        for i in range(1, len(monthly_results)):
            if monthly_results[i] == monthly_results[i-1]:
                consecutive_same += 1
                max_consecutive = max(max_consecutive, consecutive_same)
            else:
                consecutive_same = 0
        
        formatted_date = datetime.strptime(str(post_date), '%Y-%m-%d').strftime('%d %B %Y')
        day_name = datetime.strptime(str(post_date), '%Y-%m-%d').strftime('%A')
        month_name = datetime.strptime(str(post_date), '%Y-%m-%d').strftime('%B')
        year = datetime.strptime(str(post_date), '%Y-%m-%d').strftime('%Y')
        iso_date = datetime.strptime(str(post_date), '%Y-%m-%d').strftime('%Y-%m-%d')
        
        has_valid_result = today_result and today_result['result'] and today_result['result'] not in ['XX', 'Waiting', '--']
        
        current_time_ist = datetime.now(pytz.timezone('Asia/Kolkata')).strftime('%H:%M IST')
        
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
            high_count=high_count,
            low_count=low_count,
            hot_digits=hot_digits,
            cold_digits=cold_digits,
            hot_first_digits=hot_first_digits,
            avg_result=avg_result,
            ranges=ranges,
            max_consecutive=max_consecutive,
            formatted_date=formatted_date,
            day_name=day_name,
            month_name=month_name,
            year=year,
            iso_date=iso_date,
            current_time_ist=current_time_ist,
            game_name=game_name,
            adsense_auto_ads=get_setting('adsense_auto_ads')
        )
    except Exception as e:
        print(f"Post error: {e}")
        return "Error loading post", 500

@app.route('/chart.php')
@app.route('/chart')
def chart():
    game_name = request.args.get('game', '')
    selected_month = int(request.args.get('month', datetime.now(IST).month))
    selected_year = int(request.args.get('year', datetime.now(IST).year))
    
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT DISTINCT name FROM games ORDER BY name")
        all_games = [r['name'] for r in cursor.fetchall()]
        
        if not all_games:
            cursor.execute("SELECT DISTINCT game_name FROM satta_results ORDER BY game_name")
            all_games = [r['game_name'] for r in cursor.fetchall()]
        
        if not game_name and all_games:
            game_name = all_games[0]
        
        cursor.execute("""
            SELECT DISTINCT EXTRACT(YEAR FROM result_date)::int as year 
            FROM satta_results ORDER BY year DESC
        """)
        available_years = [r['year'] for r in cursor.fetchall()]
        
        if not available_years:
            current_year = datetime.now(IST).year
            available_years = [current_year, current_year - 1, current_year - 2]
        
        results = []
        result_map = {}
        if game_name:
            cursor.execute("""
                SELECT result_date, result, result_time 
                FROM satta_results 
                WHERE game_name = %s 
                AND EXTRACT(MONTH FROM result_date) = %s 
                AND EXTRACT(YEAR FROM result_date) = %s
                ORDER BY result_date DESC
            """, (game_name, selected_month, selected_year))
            results = cursor.fetchall()
            
            for r in results:
                result_map[str(r['result_date'])] = r['result']
        
        cursor.close()
        conn.close()
        
        import calendar
        days_in_month = calendar.monthrange(selected_year, selected_month)[1]
        month_name = calendar.month_name[selected_month]
        
        months = {i: calendar.month_name[i] for i in range(1, 13)}
        
        day_names = {}
        for day in range(1, days_in_month + 1):
            date_str = f"{selected_year:04d}-{selected_month:02d}-{day:02d}"
            try:
                dt = datetime.strptime(date_str, '%Y-%m-%d')
                day_names[date_str] = dt.strftime('%a')
            except:
                day_names[date_str] = '--'
        
        valid_results = [r for r in results if r['result'] and r['result'] not in ['--', 'XX', 'Waiting', ''] and r['result'].isdigit()]
        odd_count = sum(1 for r in valid_results if int(r['result']) % 2 != 0)
        even_count = len(valid_results) - odd_count
        
        digit_freq = [0] * 10
        for r in valid_results:
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
            day_names=day_names,
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
        cursor = get_cursor(conn)
        cursor.execute("""
            SELECT slug, title, meta_description, post_date, views, games_included 
            FROM posts ORDER BY post_date DESC, created_at DESC LIMIT 50
        """)
        posts = cursor.fetchall()
        cursor.close()
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
        page = request.args.get('page', 1, type=int)
        per_page = 10
        offset = (page - 1) * per_page
        
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT COUNT(*) as total FROM news_posts WHERE status = 'published'")
        total_row = cursor.fetchone()
        total = total_row['total'] if total_row else 0
        total_pages = (total + per_page - 1) // per_page if total > 0 else 1
        
        cursor.execute("""
            SELECT slug, title, excerpt, meta_description, featured_image, created_at, views 
            FROM news_posts WHERE status = 'published' 
            ORDER BY created_at DESC
            LIMIT %s OFFSET %s
        """, (per_page, offset))
        news_list = cursor.fetchall()
        
        cursor.execute("""
            SELECT slug, title FROM news_posts WHERE status = 'published' 
            ORDER BY views DESC LIMIT 5
        """)
        popular_news = cursor.fetchall()
        cursor.close()
        conn.close()
        
        return render_template('news.html',
            news_list=news_list,
            popular_news=popular_news,
            current_page=page,
            total_pages=total_pages,
            total_news=total,
            adsense_auto_ads=get_setting('adsense_auto_ads')
        )
    except Exception as e:
        print(f"News error: {e}")
        return "Error loading news", 500

@app.route('/news/<slug>')
def news_post(slug):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT * FROM news_posts WHERE slug = %s AND status = 'published'", (slug,))
        news_data = cursor.fetchone()
        
        if not news_data:
            cursor.close()
            conn.close()
            return "News not found", 404
        
        cursor.execute("UPDATE news_posts SET views = views + 1 WHERE id = %s", (news_data['id'],))
        conn.commit()
        
        cursor.execute("""
            SELECT slug, title FROM news_posts 
            WHERE status = 'published' AND id != %s 
            ORDER BY created_at DESC LIMIT 5
        """, (news_data['id'],))
        related_news = cursor.fetchall()
        cursor.close()
        conn.close()
        
        return render_template('news_post.html',
            news=news_data,
            related_news=related_news,
            adsense_auto_ads=get_setting('adsense_auto_ads')
        )
    except Exception as e:
        print(f"News post error: {e}")
        return "Error loading news", 500

@app.route('/page/<slug>')
def static_page(slug):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT * FROM site_pages WHERE slug = %s", (slug,))
        page_data = cursor.fetchone()
        cursor.close()
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
def sitemap_index():
    """Main sitemap index - splits into multiple sitemaps for better indexing"""
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        
        cursor.execute("SELECT DISTINCT EXTRACT(YEAR FROM post_date) as year, EXTRACT(MONTH FROM post_date) as month FROM posts ORDER BY year DESC, month DESC")
        post_months = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        base_url = get_setting('site_url') or 'https://sattaking.com.im'
        today = datetime.now(IST).strftime('%Y-%m-%d')
        
        xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
        xml += '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        
        xml += f'<sitemap><loc>{base_url}/sitemap-main.xml</loc><lastmod>{today}</lastmod></sitemap>\n'
        xml += f'<sitemap><loc>{base_url}/sitemap-games.xml</loc><lastmod>{today}</lastmod></sitemap>\n'
        xml += f'<sitemap><loc>{base_url}/sitemap-news.xml</loc><lastmod>{today}</lastmod></sitemap>\n'
        xml += f'<sitemap><loc>{base_url}/sitemap-pages.xml</loc><lastmod>{today}</lastmod></sitemap>\n'
        
        for pm in post_months:
            year = int(pm['year'])
            month = int(pm['month'])
            xml += f'<sitemap><loc>{base_url}/sitemap-posts-{year}-{month:02d}.xml</loc><lastmod>{today}</lastmod></sitemap>\n'
        
        xml += '</sitemapindex>'
        return Response(xml, mimetype='application/xml')
    except Exception as e:
        print(f"Sitemap index error: {e}")
        return "Error generating sitemap", 500

@app.route('/sitemap-main.xml')
def sitemap_main():
    """Main pages sitemap"""
    base_url = get_setting('site_url') or 'https://sattaking.com.im'
    today = datetime.now(IST).strftime('%Y-%m-%d')
    
    xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
    xml += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
    xml += f'<url><loc>{base_url}/</loc><lastmod>{today}</lastmod><changefreq>hourly</changefreq><priority>1.0</priority></url>\n'
    xml += f'<url><loc>{base_url}/chart</loc><lastmod>{today}</lastmod><changefreq>daily</changefreq><priority>0.9</priority></url>\n'
    xml += f'<url><loc>{base_url}/daily-updates</loc><lastmod>{today}</lastmod><changefreq>daily</changefreq><priority>0.8</priority></url>\n'
    xml += f'<url><loc>{base_url}/news</loc><lastmod>{today}</lastmod><changefreq>daily</changefreq><priority>0.7</priority></url>\n'
    xml += '</urlset>'
    return Response(xml, mimetype='application/xml')

@app.route('/sitemap-games.xml')
def sitemap_games():
    """All game chart pages"""
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT DISTINCT name FROM games ORDER BY name")
        games = cursor.fetchall()
        cursor.close()
        conn.close()
        
        base_url = get_setting('site_url') or 'https://sattaking.com.im'
        today = datetime.now(IST).strftime('%Y-%m-%d')
        
        xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
        xml += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        
        from urllib.parse import quote
        for g in games:
            game_name = quote(g['name'])
            xml += f'<url><loc>{base_url}/chart?game={game_name}</loc><lastmod>{today}</lastmod><changefreq>daily</changefreq><priority>0.6</priority></url>\n'
        
        xml += '</urlset>'
        return Response(xml, mimetype='application/xml')
    except Exception as e:
        print(f"Sitemap games error: {e}")
        return "Error", 500

@app.route('/sitemap-posts-<int:year>-<int:month>.xml')
def sitemap_posts_month(year, month):
    """Monthly posts sitemap - handles ~100 daily posts per month efficiently"""
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        
        cursor.execute("""
            SELECT slug, post_date, updated_at 
            FROM posts 
            WHERE EXTRACT(YEAR FROM post_date) = %s AND EXTRACT(MONTH FROM post_date) = %s
            ORDER BY post_date DESC
        """, (year, month))
        posts = cursor.fetchall()
        cursor.close()
        conn.close()
        
        base_url = get_setting('site_url') or 'https://sattaking.com.im'
        
        xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
        xml += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        
        for post in posts:
            lastmod = post.get('updated_at') or post['post_date']
            if hasattr(lastmod, 'strftime'):
                lastmod = lastmod.strftime('%Y-%m-%d')
            else:
                lastmod = str(lastmod)[:10]
            xml += f'<url><loc>{base_url}/post/{post["slug"]}</loc><lastmod>{lastmod}</lastmod><changefreq>weekly</changefreq><priority>0.5</priority></url>\n'
        
        xml += '</urlset>'
        return Response(xml, mimetype='application/xml')
    except Exception as e:
        print(f"Sitemap posts error: {e}")
        return "Error", 500

@app.route('/sitemap-news.xml')
def sitemap_news():
    """News articles sitemap"""
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT slug, updated_at FROM news_posts WHERE status = 'published' ORDER BY created_at DESC")
        news = cursor.fetchall()
        cursor.close()
        conn.close()
        
        base_url = get_setting('site_url') or 'https://sattaking.com.im'
        
        xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
        xml += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        
        for n in news:
            lastmod = n.get('updated_at')
            if hasattr(lastmod, 'strftime'):
                lastmod = lastmod.strftime('%Y-%m-%d')
            else:
                lastmod = datetime.now(IST).strftime('%Y-%m-%d')
            xml += f'<url><loc>{base_url}/news/{n["slug"]}</loc><lastmod>{lastmod}</lastmod><changefreq>monthly</changefreq><priority>0.5</priority></url>\n'
        
        xml += '</urlset>'
        return Response(xml, mimetype='application/xml')
    except Exception as e:
        print(f"Sitemap news error: {e}")
        return "Error", 500

@app.route('/sitemap-pages.xml')
def sitemap_pages():
    """Static pages sitemap"""
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT slug FROM site_pages")
        pages = cursor.fetchall()
        cursor.close()
        conn.close()
        
        base_url = get_setting('site_url') or 'https://sattaking.com.im'
        today = datetime.now(IST).strftime('%Y-%m-%d')
        
        xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
        xml += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        
        for p in pages:
            xml += f'<url><loc>{base_url}/page/{p["slug"]}</loc><lastmod>{today}</lastmod><changefreq>monthly</changefreq><priority>0.4</priority></url>\n'
        
        xml += '</urlset>'
        return Response(xml, mimetype='application/xml')
    except Exception as e:
        print(f"Sitemap pages error: {e}")
        return "Error", 500

@app.route('/robots.txt')
def robots_txt():
    """Dynamic robots.txt with sitemap reference"""
    base_url = get_setting('site_url') or 'https://sattaking.com.im'
    content = f"""User-agent: *
Allow: /

Sitemap: {base_url}/sitemap.xml
"""
    return Response(content, mimetype='text/plain')

@app.route('/ads.txt')
def ads_txt():
    """ads.txt for AdSense verification"""
    content = get_setting('ads_txt_content')
    if content:
        return Response(content, mimetype='text/plain')
    return Response("# No ads.txt content configured", mimetype='text/plain')

@app.route('/sw.js')
def service_worker():
    """Service worker for push notifications"""
    sw_code = '''
self.addEventListener('push', function(event) {
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'Satta King';
    const options = {
        body: data.body || 'New update available!',
        icon: data.icon || '/logo.png',
        badge: '/logo.png',
        vibrate: [100, 50, 100],
        data: { url: data.url || '/' },
        actions: [{ action: 'open', title: 'View Now' }]
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const url = event.notification.data.url || '/';
    event.waitUntil(clients.openWindow(url));
});
'''
    return Response(sw_code, mimetype='application/javascript')

@app.route('/api/push/vapid-key')
def get_vapid_public_key():
    """Get VAPID public key for push subscription"""
    _, public_key = get_vapid_keys()
    return jsonify({'publicKey': public_key})

@app.route('/api/push/subscribe', methods=['POST'])
def push_subscribe():
    """Subscribe to push notifications"""
    try:
        data = request.get_json()
        endpoint = data.get('endpoint')
        keys = data.get('keys', {})
        p256dh = keys.get('p256dh')
        auth = keys.get('auth')
        
        if not endpoint or not p256dh or not auth:
            return jsonify({'error': 'Invalid subscription data'}), 400
        
        user_agent = request.headers.get('User-Agent', '')
        
        conn = get_db()
        cursor = get_cursor(conn)
        
        if USE_MYSQL:
            cursor.execute("""
                INSERT INTO push_subscribers (endpoint, p256dh, auth, user_agent)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), is_active = TRUE
            """, (endpoint, p256dh, auth, user_agent))
        else:
            cursor.execute("""
                INSERT INTO push_subscribers (endpoint, p256dh, auth, user_agent)
                VALUES (%s, %s, %s, %s)
                ON CONFLICT (endpoint) DO UPDATE SET p256dh = EXCLUDED.p256dh, auth = EXCLUDED.auth, is_active = TRUE
            """, (endpoint, p256dh, auth, user_agent))
        
        conn.commit()
        cursor.close()
        conn.close()
        
        return jsonify({'success': True, 'message': 'Subscribed successfully'})
    except Exception as e:
        print(f"Subscribe error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/api/push/unsubscribe', methods=['POST'])
def push_unsubscribe():
    """Unsubscribe from push notifications"""
    try:
        data = request.get_json()
        endpoint = data.get('endpoint')
        
        if endpoint:
            conn = get_db()
            cursor = get_cursor(conn)
            cursor.execute("UPDATE push_subscribers SET is_active = FALSE WHERE endpoint = %s", (endpoint,))
            conn.commit()
            cursor.close()
            conn.close()
        
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/push/stats')
def push_stats():
    """Get push notification stats"""
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT COUNT(*) as count FROM push_subscribers WHERE is_active = TRUE")
        active = cursor.fetchone()['count']
        cursor.execute("SELECT COUNT(*) as count FROM push_subscribers")
        total = cursor.fetchone()['count']
        cursor.close()
        conn.close()
        return jsonify({'active': active, 'total': total})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

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
        cursor = get_cursor(conn)
        
        cursor.execute("SELECT COUNT(*) as cnt FROM games")
        row = cursor.fetchone()
        total_games = row['cnt'] if row else 0
        
        if USE_MYSQL:
            cursor.execute("SELECT COUNT(*) as cnt FROM satta_results WHERE result_date = CURDATE()")
        else:
            cursor.execute("SELECT COUNT(*) as cnt FROM satta_results WHERE result_date = CURRENT_DATE")
        row = cursor.fetchone()
        today_results = row['cnt'] if row else 0
        
        cursor.execute("SELECT COUNT(*) as cnt FROM posts")
        row = cursor.fetchone()
        total_posts = row['cnt'] if row else 0
        
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
        
        cursor.execute("SELECT * FROM ad_placements ORDER BY placement_name")
        ad_placements_list = cursor.fetchall()
        ad_placements = {p['placement_name']: p for p in ad_placements_list} if ad_placements_list else {}
        
        cursor.execute("SELECT * FROM scrape_schedule ORDER BY schedule_time")
        scrape_schedules = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        return render_template('admin.html',
            page=page,
            total_games=total_games,
            today_results=today_results,
            total_posts=total_posts,
            games=games,
            posts=posts,
            scrape_sources=scrape_sources,
            scrape_schedules=scrape_schedules,
            news_posts=news_posts,
            site_pages=site_pages,
            ad_placements=ad_placements,
            last_auto_scrape=get_setting('last_auto_scrape'),
            adsense_publisher_id=get_setting('adsense_publisher_id'),
            adsense_auto_ads=get_setting('adsense_auto_ads'),
            adsense_verification=get_setting('adsense_verification'),
            google_analytics_code=get_setting('google_analytics_code'),
            ads_txt_content=get_setting('ads_txt_content'),
            auto_publish_enabled=get_setting('auto_publish_enabled', '1'),
            auto_publish_hour=get_setting('auto_publish_hour', '1'),
            india_time=datetime.now(IST).strftime('%d %b %Y, %I:%M:%S %p IST'),
            scrape_interval=get_setting('scrape_interval_minutes', '30'),
            daily_post_enabled=get_setting('daily_post_enabled', '1'),
            daily_post_hour=get_setting('daily_post_hour', '1'),
            daily_post_minute=get_setting('daily_post_minute', '0'),
            last_daily_posts_created=get_setting('last_daily_posts_created', ''),
            site_url=get_setting('site_url', 'https://sattaking.com.im'),
            push_active_subscribers=get_push_stats()['active'],
            push_total_subscribers=get_push_stats()['total'],
            push_total_sent=get_push_stats()['sent'],
            push_prompt_title=get_setting('push_prompt_title'),
            push_prompt_message=get_setting('push_prompt_message'),
            push_on_result=get_setting('push_on_result', '1'),
            push_on_post=get_setting('push_on_post', '1'),
            notification_logs=get_notification_logs(),
            site_logo=get_setting('site_logo'),
            site_favicon=get_setting('site_favicon'),
            site_icon=get_setting('site_icon')
        )
    except Exception as e:
        print(f"Admin error: {e}")
        return f"Error: {e}", 500

def get_push_stats():
    """Get push notification statistics"""
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT COUNT(*) as count FROM push_subscribers WHERE is_active = TRUE")
        active = cursor.fetchone()['count']
        cursor.execute("SELECT COUNT(*) as count FROM push_subscribers")
        total = cursor.fetchone()['count']
        cursor.execute("SELECT COALESCE(SUM(total_sent), 0) as sent FROM notification_logs")
        sent = cursor.fetchone()['sent']
        cursor.close()
        conn.close()
        return {'active': active, 'total': total, 'sent': int(sent)}
    except:
        return {'active': 0, 'total': 0, 'sent': 0}

def get_notification_logs():
    """Get recent notification logs"""
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT * FROM notification_logs ORDER BY sent_at DESC LIMIT 20")
        logs = cursor.fetchall()
        cursor.close()
        conn.close()
        return logs
    except:
        return []

@app.route('/admin/save-push-settings', methods=['POST'])
@login_required
def admin_save_push_settings():
    set_setting('push_prompt_title', request.form.get('push_prompt_title', ''))
    set_setting('push_prompt_message', request.form.get('push_prompt_message', ''))
    return redirect(url_for('admin_dashboard', page='push'))

@app.route('/admin/save-push-auto', methods=['POST'])
@login_required
def admin_save_push_auto():
    set_setting('push_on_result', '1' if request.form.get('push_on_result') else '0')
    set_setting('push_on_post', '1' if request.form.get('push_on_post') else '0')
    return redirect(url_for('admin_dashboard', page='push'))

@app.route('/admin/send-push', methods=['POST'])
@login_required
def admin_send_push():
    title = request.form.get('title', '')
    body = request.form.get('body', '')
    url = request.form.get('url', '/')
    
    if title and body:
        send_push_notification(title, body, url, notification_type='manual')
    
    return redirect(url_for('admin_dashboard', page='push'))

@app.route('/admin/scrape-now', methods=['POST'])
@login_required
def admin_scrape_now():
    run_auto_scrape()
    return redirect(url_for('admin_dashboard', page='auto-scrape'))

@app.route('/admin/save-daily-post-settings', methods=['POST'])
@login_required
def admin_save_daily_post_settings():
    enabled = request.form.get('daily_post_enabled', '1')
    hour = request.form.get('daily_post_hour', '1')
    minute = request.form.get('daily_post_minute', '0')
    set_setting('daily_post_enabled', enabled)
    set_setting('daily_post_hour', hour)
    set_setting('daily_post_minute', minute)
    return redirect(url_for('admin_dashboard', page='daily-posts'))

@app.route('/admin/create-daily-posts-now', methods=['POST'])
@login_required
def admin_create_daily_posts_now():
    count = create_daily_posts_for_all_games()
    return redirect(url_for('admin_dashboard', page='daily-posts'))

@app.route('/admin/save-adsense-verification', methods=['POST'])
@login_required
def admin_save_adsense_verification():
    verification = request.form.get('adsense_verification', '').strip()
    set_setting('adsense_verification', verification)
    return redirect(url_for('admin_dashboard', page='ads'))

@app.route('/admin/save-analytics', methods=['POST'])
@login_required
def admin_save_analytics():
    code = request.form.get('google_analytics_code', '').strip()
    set_setting('google_analytics_code', code)
    return redirect(url_for('admin_dashboard', page='ads'))

@app.route('/admin/upload-branding', methods=['POST'])
@login_required
def admin_upload_branding():
    upload_folder = app.config['UPLOAD_FOLDER']
    
    file_mappings = {
        'site_logo': 'logo',
        'site_favicon': 'favicon',
        'site_icon': 'icon'
    }
    
    for field_name, prefix in file_mappings.items():
        file = request.files.get(field_name)
        if file and file.filename and allowed_file(file.filename):
            ext = file.filename.rsplit('.', 1)[1].lower()
            filename = f"{prefix}.{ext}"
            filepath = os.path.join(upload_folder, filename)
            file.save(filepath)
            url_path = f"/uploads/{filename}"
            set_setting(field_name, url_path)
    
    return redirect(url_for('admin_dashboard', page='branding'))

@app.route('/admin/save-auto-ads', methods=['POST'])
@login_required
def admin_save_auto_ads():
    auto_ads = request.form.get('adsense_auto_ads', '').strip()
    publisher_id = request.form.get('adsense_publisher_id', '').strip()
    set_setting('adsense_auto_ads', auto_ads)
    set_setting('adsense_publisher_id', publisher_id)
    return redirect(url_for('admin_dashboard', page='ads'))

@app.route('/admin/save-ads-txt', methods=['POST'])
@login_required
def admin_save_ads_txt():
    content = request.form.get('ads_txt_content', '').strip()
    set_setting('ads_txt_content', content)
    return redirect(url_for('admin_dashboard', page='ads'))

@app.route('/admin/save-ad-placement', methods=['POST'])
@login_required
def admin_save_ad_placement():
    placement_name = request.form.get('placement_name', '').strip()
    ad_code = request.form.get('ad_code', '').strip()
    is_active = 1 if request.form.get('is_active') else 0
    
    if placement_name:
        try:
            conn = get_db()
            cursor = get_cursor(conn)
            if USE_MYSQL:
                cursor.execute("""
                    INSERT INTO ad_placements (placement_name, ad_code, is_active)
                    VALUES (%s, %s, %s)
                    ON DUPLICATE KEY UPDATE ad_code = VALUES(ad_code), is_active = VALUES(is_active)
                """, (placement_name, ad_code, is_active))
            else:
                cursor.execute("""
                    INSERT INTO ad_placements (placement_name, ad_code, is_active)
                    VALUES (%s, %s, %s)
                    ON CONFLICT (placement_name) DO UPDATE SET ad_code = EXCLUDED.ad_code, is_active = EXCLUDED.is_active
                """, (placement_name, ad_code, is_active))
            conn.commit()
            cursor.close()
            conn.close()
        except Exception as e:
            print(f"Error saving ad placement: {e}")
    return redirect(url_for('admin_dashboard', page='ads'))

@app.route('/admin/add-source', methods=['POST'])
@login_required
def admin_add_source():
    url = request.form.get('url', '').strip()
    if url:
        try:
            conn = get_db()
            cursor = get_cursor(conn)
            cursor.execute("INSERT INTO scrape_sources (url, is_active) VALUES (%s, 1)", (url,))
            conn.commit()
            cursor.close()
            conn.close()
        except Exception as e:
            print(f"Error adding source: {e}")
    return redirect(url_for('admin_dashboard', page='auto-scrape'))

@app.route('/admin/add-schedule', methods=['POST'])
@login_required
def admin_add_schedule():
    schedule_time = request.form.get('schedule_time', '').strip()
    if schedule_time:
        try:
            conn = get_db()
            cursor = get_cursor(conn)
            cursor.execute("INSERT INTO scrape_schedule (schedule_time, is_active) VALUES (%s, 1)", (schedule_time,))
            conn.commit()
            cursor.close()
            conn.close()
        except Exception as e:
            print(f"Error adding schedule: {e}")
    return redirect(url_for('admin_dashboard', page='auto-scrape'))

@app.route('/admin/delete-schedule', methods=['POST'])
@login_required
def admin_delete_schedule():
    schedule_id = request.form.get('schedule_id')
    if schedule_id:
        try:
            conn = get_db()
            cursor = get_cursor(conn)
            cursor.execute("DELETE FROM scrape_schedule WHERE id = %s", (schedule_id,))
            conn.commit()
            cursor.close()
            conn.close()
        except Exception as e:
            print(f"Error deleting schedule: {e}")
    return redirect(url_for('admin_dashboard', page='auto-scrape'))

@app.route('/admin/set-interval', methods=['POST'])
@login_required
def admin_set_interval():
    interval = request.form.get('interval', '30')
    try:
        interval_int = int(interval)
        if interval_int < 1:
            interval_int = 1
        if interval_int > 1440:
            interval_int = 1440
        set_setting('scrape_interval_minutes', str(interval_int))
        schedule_auto_scrape()
    except:
        pass
    return redirect(url_for('admin_dashboard', page='auto-scrape'))

@app.route('/admin/add-news', methods=['POST'])
@login_required
def admin_add_news():
    import re
    title = request.form.get('title', '').strip()
    slug = request.form.get('slug', '').strip()
    excerpt = request.form.get('excerpt', '').strip()
    content = request.form.get('content', '').strip()
    featured_image = request.form.get('featured_image', '').strip()
    meta_title = request.form.get('meta_title', '').strip()
    meta_description = request.form.get('meta_description', '').strip()
    meta_keywords = request.form.get('meta_keywords', '').strip()
    status = request.form.get('status', 'published')
    
    if not title or not content:
        return redirect(url_for('admin_dashboard', page='news'))
    
    if not slug:
        slug = re.sub(r'[^a-z0-9]+', '-', title.lower()).strip('-')
    
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("""
            INSERT INTO news_posts (title, slug, excerpt, content, featured_image, 
                meta_title, meta_description, meta_keywords, status, views, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 0, NOW(), NOW())
        """, (title, slug, excerpt, content, featured_image, meta_title, meta_description, meta_keywords, status))
        conn.commit()
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"Error adding news: {e}")
    return redirect(url_for('admin_dashboard', page='news'))

@app.route('/admin/delete-news', methods=['POST'])
@login_required
def admin_delete_news():
    news_id = request.form.get('news_id')
    if news_id:
        try:
            conn = get_db()
            cursor = get_cursor(conn)
            cursor.execute("DELETE FROM news_posts WHERE id = %s", (news_id,))
            conn.commit()
            cursor.close()
            conn.close()
        except Exception as e:
            print(f"Error deleting news: {e}")
    return redirect(url_for('admin_dashboard', page='news'))

@app.route('/admin/page/add', methods=['POST'])
@login_required
def admin_add_page():
    import re
    title = request.form.get('title', '').strip()
    slug = request.form.get('slug', '').strip()
    content = request.form.get('content', '').strip()
    meta_title = request.form.get('meta_title', '').strip()
    meta_description = request.form.get('meta_description', '').strip()
    is_published = 1 if request.form.get('is_published') else 0
    
    if not title or not content:
        return redirect(url_for('admin_dashboard', page='pages'))
    
    if not slug:
        slug = re.sub(r'[^a-z0-9]+', '-', title.lower()).strip('-')
    
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("""
            INSERT INTO site_pages (title, slug, content, meta_title, meta_description, is_published, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, NOW())
        """, (title, slug, content, meta_title, meta_description, is_published))
        conn.commit()
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"Error adding page: {e}")
    return redirect(url_for('admin_dashboard', page='pages'))

@app.route('/admin/page/edit/<int:page_id>', methods=['GET', 'POST'])
@login_required
def admin_edit_page(page_id):
    conn = get_db()
    cursor = get_cursor(conn)
    
    if request.method == 'POST':
        title = request.form.get('title', '').strip()
        slug = request.form.get('slug', '').strip()
        content = request.form.get('content', '').strip()
        meta_title = request.form.get('meta_title', '').strip()
        meta_description = request.form.get('meta_description', '').strip()
        is_published = 1 if request.form.get('is_published') else 0
        
        try:
            cursor.execute("""
                UPDATE site_pages 
                SET title = %s, slug = %s, content = %s, meta_title = %s, 
                    meta_description = %s, is_published = %s, updated_at = NOW()
                WHERE id = %s
            """, (title, slug, content, meta_title, meta_description, is_published, page_id))
            conn.commit()
        except Exception as e:
            print(f"Error updating page: {e}")
        cursor.close()
        conn.close()
        return redirect(url_for('admin_dashboard', page='pages'))
    
    cursor.execute("SELECT * FROM site_pages WHERE id = %s", (page_id,))
    page_data = cursor.fetchone()
    cursor.close()
    conn.close()
    
    if not page_data:
        return redirect(url_for('admin_dashboard', page='pages'))
    
    return render_template('admin_edit_page.html', page_data=page_data)

@app.route('/admin/page/delete', methods=['POST'])
@login_required
def admin_delete_page():
    page_id = request.form.get('page_id')
    if page_id:
        try:
            conn = get_db()
            cursor = get_cursor(conn)
            cursor.execute("DELETE FROM site_pages WHERE id = %s", (page_id,))
            conn.commit()
            cursor.close()
            conn.close()
        except Exception as e:
            print(f"Error deleting page: {e}")
    return redirect(url_for('admin_dashboard', page='pages'))

@app.route('/admin/logout')
def admin_logout():
    session.pop('admin_logged_in', None)
    return redirect(url_for('admin_login'))

@app.route('/admin/redirects')
@login_required
def admin_redirects():
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT * FROM url_redirects ORDER BY created_at DESC")
        redirects = cursor.fetchall()
        cursor.close()
        conn.close()
        return render_template('admin_redirects.html', redirects=redirects)
    except Exception as e:
        print(f"Redirects error: {e}")
        return render_template('admin_redirects.html', redirects=[])

@app.route('/admin/add-redirect', methods=['POST'])
@login_required
def admin_add_redirect():
    old_url = request.form.get('old_url', '').strip()
    new_url = request.form.get('new_url', '').strip()
    redirect_type = int(request.form.get('redirect_type', 301))
    
    if old_url and new_url:
        try:
            conn = get_db()
            cursor = get_cursor(conn)
            if USE_MYSQL:
                cursor.execute("""
                    INSERT INTO url_redirects (old_url, new_url, redirect_type) VALUES (%s, %s, %s)
                    ON DUPLICATE KEY UPDATE new_url = VALUES(new_url), redirect_type = VALUES(redirect_type)
                """, (old_url, new_url, redirect_type))
            else:
                cursor.execute("""
                    INSERT INTO url_redirects (old_url, new_url, redirect_type) VALUES (%s, %s, %s)
                    ON CONFLICT (old_url) DO UPDATE SET new_url = EXCLUDED.new_url, redirect_type = EXCLUDED.redirect_type
                """, (old_url, new_url, redirect_type))
            conn.commit()
            cursor.close()
            conn.close()
        except Exception as e:
            print(f"Add redirect error: {e}")
    
    return redirect(url_for('admin_redirects'))

@app.route('/admin/delete-redirect/<int:redirect_id>', methods=['POST'])
@login_required
def admin_delete_redirect(redirect_id):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("DELETE FROM url_redirects WHERE id = %s", (redirect_id,))
        conn.commit()
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"Delete redirect error: {e}")
    
    return redirect(url_for('admin_redirects'))

@app.route('/admin/toggle-redirect/<int:redirect_id>', methods=['POST'])
@login_required
def admin_toggle_redirect(redirect_id):
    try:
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("UPDATE url_redirects SET is_active = NOT is_active WHERE id = %s", (redirect_id,))
        conn.commit()
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"Toggle redirect error: {e}")
    
    return redirect(url_for('admin_redirects'))

def check_scheduled_scrape():
    try:
        now = datetime.now(IST).strftime('%H:%M')
        conn = get_db()
        cursor = get_cursor(conn)
        cursor.execute("SELECT * FROM scrape_schedule WHERE is_active = 1")
        schedules = cursor.fetchall()
        cursor.close()
        conn.close()
        
        for schedule in schedules:
            schedule_time = str(schedule['schedule_time'])[:5]
            if schedule_time == now:
                print(f"Running scheduled scrape at {now}")
                run_auto_scrape()
                break
    except Exception as e:
        print(f"Schedule check error: {e}")

def get_scrape_interval():
    interval = get_setting('scrape_interval_minutes', '30')
    try:
        return int(interval)
    except:
        return 30

def get_india_time():
    return datetime.now(IST).strftime('%d %b %Y, %I:%M:%S %p IST')

scheduler = BackgroundScheduler(timezone=IST)

def schedule_auto_scrape():
    interval = get_scrape_interval()
    for job in scheduler.get_jobs():
        if job.id == 'auto_scrape':
            scheduler.remove_job('auto_scrape')
            break
    scheduler.add_job(func=run_auto_scrape, trigger="interval", minutes=interval, id='auto_scrape')
    print(f"Auto-scrape scheduled every {interval} minutes")

def schedule_daily_posts():
    for job in scheduler.get_jobs():
        if job.id == 'daily_posts':
            scheduler.remove_job('daily_posts')
            break
    scheduler.add_job(func=run_daily_post_scheduler, trigger="interval", minutes=1, id='daily_posts')
    print("Daily post scheduler running (checks every minute)")

schedule_auto_scrape()
schedule_daily_posts()
scheduler.start()

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
