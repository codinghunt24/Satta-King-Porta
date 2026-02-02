#!/bin/bash

# Satta King Production Installation Script
# Run this script on your Ubuntu server

set -e

echo "=== Satta King Installation Script ==="
echo ""

# Configuration
INSTALL_DIR="/home/digitalcash24/sattaking.com.im"
SERVICE_NAME="sattaking"

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

echo "[1/7] Installing Python 3.11..."
apt update
apt install -y python3.11 python3.11-venv python3.11-dev

echo "[2/7] Creating virtual environment..."
cd $INSTALL_DIR
python3.11 -m venv venv
source venv/bin/activate

echo "[3/7] Installing Python dependencies..."
pip install --upgrade pip
pip install -r requirements.txt

echo "[4/7] Setting up environment file..."
if [ ! -f .env ]; then
    echo "Creating .env file..."
    echo "DATABASE_URL=postgresql://sattauser:your_password@localhost:5432/sattaking" > .env
    echo "SESSION_SECRET=change_this_password" >> .env
    echo ""
    echo "IMPORTANT: Edit .env file with your database credentials!"
    echo "nano $INSTALL_DIR/.env"
fi

echo "[5/7] Setting permissions..."
chown -R www-data:www-data $INSTALL_DIR
chmod -R 755 $INSTALL_DIR

echo "[6/7] Installing systemd service..."
cp deploy/sattaking.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable $SERVICE_NAME

echo "[7/7] Installing Nginx configuration..."
cp deploy/nginx-sattaking.conf /etc/nginx/sites-available/sattaking.com.im
ln -sf /etc/nginx/sites-available/sattaking.com.im /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

echo ""
echo "=== Installation Complete ==="
echo ""
echo "Next steps:"
echo "1. Edit .env file: nano $INSTALL_DIR/.env"
echo "2. Setup PostgreSQL database"
echo "3. Start service: systemctl start $SERVICE_NAME"
echo "4. Check status: systemctl status $SERVICE_NAME"
echo "5. Setup SSL: certbot --nginx -d sattaking.com.im"
echo ""
