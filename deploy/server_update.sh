#!/bin/bash

# =====================================================
#   Satta King - Direct Server Update Script
#   Server par run karein:
#   cd /home/digitalcash24/sattaking.com.im
#   bash deploy/server_update.sh
# =====================================================

INSTALL_DIR="/home/digitalcash24/sattaking.com.im"
SERVICE_NAME="sattaking"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}   Satta King - Server Update${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

cd $INSTALL_DIR || { echo -e "${RED}[ERROR] Directory nahi mili: $INSTALL_DIR${NC}"; exit 1; }

# ── BACKUP ──────────────────────────────────────────
echo -e "${YELLOW}[1/5] Backup le raha hai...${NC}"
BACKUP_DIR="$INSTALL_DIR/backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR/templates"
cp templates/base.html "$BACKUP_DIR/templates/" 2>/dev/null
cp templates/daily_updates.html "$BACKUP_DIR/templates/" 2>/dev/null
cp templates/post.html "$BACKUP_DIR/templates/" 2>/dev/null
cp app.py "$BACKUP_DIR/" 2>/dev/null
echo -e "${GREEN}[OK] Backup: $BACKUP_DIR${NC}"
echo ""

# ── FIX 1: base.html - robots meta tag dynamic banao ─
echo -e "${YELLOW}[2/5] base.html update kar raha hai...${NC}"

sed -i 's|<meta name="robots" content="index, follow">|<meta name="robots" content="{% block robots %}index, follow{% endblock %}">|g' \
    templates/base.html

if grep -q '{% block robots %}' templates/base.html; then
    echo -e "${GREEN}[OK] base.html updated!${NC}"
else
    echo -e "${RED}[WARN] base.html mein change already tha ya kuch alag hai - check karein${NC}"
fi
echo ""

# ── FIX 2: daily_updates.html - noindex for page > 1 ─
echo -e "${YELLOW}[3/5] daily_updates.html update kar raha hai...${NC}"

# Canonical line fix
sed -i 's|{% block canonical %}/daily-updates{% if current_page > 1 %}?page={{ current_page }}{% endif %}{% endblock %}|{% block canonical %}/daily-updates{% endblock %}|g' \
    templates/daily_updates.html

# Robots block add karo (agar nahi hai to)
if ! grep -q '{% block robots %}' templates/daily_updates.html; then
    sed -i 's|{% block canonical %}/daily-updates{% endblock %}|{% block canonical %}/daily-updates{% endblock %}\n{% block robots %}{% if current_page > 1 %}noindex, follow{% else %}index, follow{% endif %}{% endblock %}|g' \
        templates/daily_updates.html
fi

if grep -q 'noindex' templates/daily_updates.html; then
    echo -e "${GREEN}[OK] daily_updates.html updated!${NC}"
else
    echo -e "${RED}[WARN] daily_updates.html check karein${NC}"
fi
echo ""

# ── FIX 3: post.html - duplicate robots tag hatao ────
echo -e "${YELLOW}[4/5] post.html update kar raha hai...${NC}"

# Purana hardcoded robots tag hatao aur block se replace karo
sed -i 's|<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">|{% block robots %}index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1{% endblock %}|g' \
    templates/post.html

# Agar already naya wala tha
if grep -q 'max-video-preview' templates/post.html; then
    echo -e "${GREEN}[OK] post.html updated!${NC}"
else
    echo -e "${RED}[WARN] post.html check karein${NC}"
fi
echo ""

# ── FIX 4: app.py - sitemap priority improve karo ───
echo -e "${YELLOW}[5/5] app.py sitemap priority update kar raha hai...${NC}"

# Check karein ki purana code hai ya naya
if grep -q "changefreq>weekly</changefreq><priority>0.5</priority>" app.py; then

python3 << 'PYEOF'
import re

with open('app.py', 'r') as f:
    content = f.read()

old_code = "            xml += f'<url><loc>{base_url}/post/{post[\"slug\"]}</loc><lastmod>{lastmod}</lastmod><changefreq>weekly</changefreq><priority>0.5</priority></url>\\n'"

new_code = """            today_date = datetime.now(IST).date()
            post_date_raw = post.get('post_date')
            try:
                from datetime import date as dt_date
                if hasattr(post_date_raw, 'date'):
                    post_date_obj = post_date_raw.date()
                elif hasattr(post_date_raw, 'year'):
                    post_date_obj = post_date_raw
                else:
                    post_date_obj = dt_date.fromisoformat(str(post_date_raw)[:10])
                days_old = (today_date - post_date_obj).days
            except:
                days_old = 999
            if days_old <= 3:
                changefreq = 'daily'
                priority = '0.9'
            elif days_old <= 30:
                changefreq = 'daily'
                priority = '0.8'
            elif days_old <= 90:
                changefreq = 'weekly'
                priority = '0.7'
            else:
                changefreq = 'monthly'
                priority = '0.5'
            xml += f'<url><loc>{base_url}/post/{post["slug"]}</loc><lastmod>{lastmod}</lastmod><changefreq>{changefreq}</changefreq><priority>{priority}</priority></url>\\n'"""

if old_code in content:
    content = content.replace(old_code, new_code)
    with open('app.py', 'w') as f:
        f.write(content)
    print("app.py sitemap updated!")
else:
    print("app.py mein sitemap code nahi mila (shayad already updated hai)")
PYEOF

    echo -e "${GREEN}[OK] app.py updated!${NC}"
else
    echo -e "${YELLOW}[SKIP] app.py sitemap already updated hai${NC}"
fi
echo ""

# ── SERVICE RESTART ──────────────────────────────────
echo -e "${BLUE}Service restart kar raha hai...${NC}"
systemctl restart $SERVICE_NAME
sleep 3

STATUS=$(systemctl is-active $SERVICE_NAME)
if [ "$STATUS" = "active" ]; then
    echo -e "${GREEN}[OK] Service chal rahi hai!${NC}"
else
    echo -e "${RED}[ERROR] Service start nahi hui!${NC}"
    systemctl status $SERVICE_NAME --no-pager -l
    echo ""
    echo -e "${YELLOW}Rollback kar raha hai backup se...${NC}"
    cp "$BACKUP_DIR/templates/base.html" templates/
    cp "$BACKUP_DIR/templates/daily_updates.html" templates/
    cp "$BACKUP_DIR/templates/post.html" templates/
    cp "$BACKUP_DIR/app.py" .
    systemctl restart $SERVICE_NAME
    echo -e "${YELLOW}Rollback complete. Purana version restore ho gaya.${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   Update Successfully Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Ye changes apply hue:"
echo -e "  ${GREEN}✓${NC} Daily Updates pagination pages: noindex (Google waste index nahi karega)"
echo -e "  ${GREEN}✓${NC} Post pages: duplicate robots tag hataya"
echo -e "  ${GREEN}✓${NC} Sitemap: naye posts ko high priority (0.9), purane ko low (0.5)"
echo ""
echo -e "Backup yahan save hai: ${YELLOW}$BACKUP_DIR${NC}"
echo ""
