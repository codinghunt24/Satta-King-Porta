# Satta King Website - Production Deployment Guide
## Ubuntu Server with ZesleCP Panel

### Server Requirements
- Ubuntu 20.04/22.04
- Python 3.11+
- PostgreSQL database
- Nginx (for reverse proxy)

---

## STEP 1: Backup & Delete Old Website

```bash
# SSH login to server
ssh root@your-server-ip

# Backup old website (optional)
cd /home/digitalcash24
tar -czvf sattaking_backup_$(date +%Y%m%d).tar.gz sattaking.com.im

# Delete old website files (keep domain folder)
rm -rf /home/digitalcash24/sattaking.com.im/*
```

---

## STEP 2: Install Python 3.11 (if not installed)

```bash
# Check Python version
python3 --version

# If Python < 3.11, install Python 3.11
apt update
apt install -y software-properties-common
add-apt-repository -y ppa:deadsnakes/ppa
apt update
apt install -y python3.11 python3.11-venv python3.11-dev
```

---

## STEP 3: Upload New Website Files

### Option A: Using SCP (from your local machine)
```bash
# Download from Replit first, then upload
scp -r ./sattaking_files/* root@your-server-ip:/home/digitalcash24/sattaking.com.im/
```

### Option B: Using Git
```bash
cd /home/digitalcash24/sattaking.com.im
git clone your-repo-url .
```

### Option C: Manual Upload via SFTP
- Use FileZilla or WinSCP
- Connect to server
- Upload all files to /home/digitalcash24/sattaking.com.im/

---

## STEP 4: Create Virtual Environment & Install Dependencies

```bash
cd /home/digitalcash24/sattaking.com.im

# Create virtual environment with Python 3.11
python3.11 -m venv venv

# Activate virtual environment
source venv/bin/activate

# Install dependencies
pip install --upgrade pip
pip install -r requirements.txt
```

---

## STEP 5: Setup PostgreSQL Database

```bash
# Login to PostgreSQL
sudo -u postgres psql

# Create database and user
CREATE DATABASE sattaking;
CREATE USER sattauser WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE sattaking TO sattauser;
\q
```

---

## STEP 6: Configure Environment Variables

```bash
cd /home/digitalcash24/sattaking.com.im

# Create .env file
nano .env
```

Add these lines:
```
DATABASE_URL=postgresql://sattauser:your_secure_password@localhost:5432/sattaking
SESSION_SECRET=your_admin_password_here
```

Save and exit (Ctrl+X, Y, Enter)

---

## STEP 7: Import Database Data

```bash
cd /home/digitalcash24/sattaking.com.im
source venv/bin/activate

# Run database setup (creates tables)
python -c "from app import app; print('Database initialized')"

# Import data from SQL dump if needed
# psql -U sattauser -d sattaking -f attached_assets/data_dump.sql
```

---

## STEP 8: Create Systemd Service

```bash
sudo nano /etc/systemd/system/sattaking.service
```

Paste this content:
```ini
[Unit]
Description=Satta King Gunicorn Service
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/home/digitalcash24/sattaking.com.im
Environment="PATH=/home/digitalcash24/sattaking.com.im/venv/bin"
EnvironmentFile=/home/digitalcash24/sattaking.com.im/.env
ExecStart=/home/digitalcash24/sattaking.com.im/venv/bin/gunicorn --workers 3 --bind 127.0.0.1:5000 --timeout 120 app:app
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Save and exit.

```bash
# Set correct permissions
chown -R www-data:www-data /home/digitalcash24/sattaking.com.im

# Enable and start service
sudo systemctl daemon-reload
sudo systemctl enable sattaking
sudo systemctl start sattaking

# Check status
sudo systemctl status sattaking
```

---

## STEP 9: Configure Nginx (Reverse Proxy)

```bash
sudo nano /etc/nginx/sites-available/sattaking.com.im
```

Paste this content:
```nginx
server {
    listen 80;
    server_name sattaking.com.im www.sattaking.com.im;

    location / {
        proxy_pass http://127.0.0.1:5000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_connect_timeout 300s;
        proxy_read_timeout 300s;
    }

    location /static {
        alias /home/digitalcash24/sattaking.com.im/static;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    client_max_body_size 10M;
}
```

Save and exit.

```bash
# Enable site
sudo ln -sf /etc/nginx/sites-available/sattaking.com.im /etc/nginx/sites-enabled/

# Test nginx config
sudo nginx -t

# Reload nginx
sudo systemctl reload nginx
```

---

## STEP 10: Setup SSL Certificate (HTTPS)

```bash
# Install certbot if not installed
apt install -y certbot python3-certbot-nginx

# Get SSL certificate
certbot --nginx -d sattaking.com.im -d www.sattaking.com.im
```

---

## Useful Commands

```bash
# Check application logs
sudo journalctl -u sattaking -f

# Restart application
sudo systemctl restart sattaking

# Stop application
sudo systemctl stop sattaking

# Check if port 5000 is in use
sudo lsof -i :5000

# Check nginx logs
sudo tail -f /var/log/nginx/error.log
```

---

## Troubleshooting

### Port Conflict
If port 5000 is already in use, change to another port (e.g., 5001):
1. Edit systemd service: `--bind 127.0.0.1:5001`
2. Update nginx config: `proxy_pass http://127.0.0.1:5001`
3. Restart both services

### Permission Issues
```bash
chown -R www-data:www-data /home/digitalcash24/sattaking.com.im
chmod -R 755 /home/digitalcash24/sattaking.com.im
```

### Database Connection Error
Check DATABASE_URL in .env file and verify PostgreSQL is running:
```bash
sudo systemctl status postgresql
```
