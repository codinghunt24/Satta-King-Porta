#!/bin/bash

# =====================================================
#   Satta King - One Command Update Script
#   Usage: bash update.sh
#   Ye GitHub par push karega + server update karega
# =====================================================

SERVER_IP="185.202.238.243"
SERVER_USER="root"
INSTALL_DIR="/home/digitalcash24/sattaking.com.im"
SERVICE_NAME="sattaking"
GITHUB_REPO="https://github.com/codinghunt24/Satta-King-Porta"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}   Satta King - Full Update Script${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# ── STEP 1: GitHub par push karo ────────────────────
echo -e "${BLUE}[1/3] GitHub par latest changes push kar raha hai...${NC}"
git add -A
git diff --cached --quiet
if [ $? -eq 0 ]; then
    echo -e "${YELLOW}[SKIP] Koi naya change nahi hai push karne ke liye${NC}"
else
    git commit -m "Update: $(date '+%d %b %Y %H:%M')"
    git push origin main
    if [ $? -ne 0 ]; then
        echo -e "${RED}[ERROR] GitHub push nahi hua! Token/credentials check karein.${NC}"
        exit 1
    fi
    echo -e "${GREEN}[OK] GitHub par push ho gaya!${NC}"
fi
echo ""

# ── STEP 2: Server connection check ─────────────────
echo -e "${BLUE}[2/3] Server se connect ho raha hai...${NC}"
if ! ssh -o ConnectTimeout=10 -o BatchMode=yes ${SERVER_USER}@${SERVER_IP} "echo ok" &>/dev/null; then
    echo -e "${RED}[ERROR] Server se connect nahi ho pa raha!${NC}"
    echo ""
    echo -e "Manual update ke liye server par ye chalao:"
    echo -e "${YELLOW}  cd $INSTALL_DIR && git pull origin main && systemctl restart $SERVICE_NAME${NC}"
    exit 1
fi
echo -e "${GREEN}[OK] Server connected!${NC}"
echo ""

# ── STEP 3: Server par git pull + restart ───────────
echo -e "${BLUE}[3/3] Server update kar raha hai...${NC}"
ssh ${SERVER_USER}@${SERVER_IP} "
    set -e
    cd ${INSTALL_DIR}
    echo 'Git pull kar raha hai...'
    git pull origin main
    echo 'Service restart kar raha hai...'
    systemctl restart ${SERVICE_NAME}
    sleep 2
    STATUS=\$(systemctl is-active ${SERVICE_NAME})
    if [ \"\$STATUS\" = 'active' ]; then
        echo 'SERVICE_RUNNING'
    else
        echo 'SERVICE_FAILED'
        systemctl status ${SERVICE_NAME} --no-pager -l
    fi
"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}[OK] Server update ho gaya aur service chal rahi hai!${NC}"
else
    echo -e "${RED}[ERROR] Server par kuch problem aayi!${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   Update Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "GitHub: ${YELLOW}${GITHUB_REPO}${NC}"
echo -e "Site:   ${YELLOW}https://sattaking.com.im${NC}"
echo ""
