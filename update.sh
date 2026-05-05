#!/bin/bash

# =====================================================
#   Satta King - Server Update Script
#   Usage: bash update.sh
#   Pehli baar: SERVER_IP set karein neeche
# =====================================================

# ---- APNA SERVER IP YAHAN LIKHEN ----
SERVER_IP="YOUR_SERVER_IP"
SERVER_USER="root"
INSTALL_DIR="/home/digitalcash24/sattaking.com.im"
SERVICE_NAME="sattaking"
# --------------------------------------

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}   Satta King Server Update Script${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check karein ki SERVER_IP set hai
if [ "$SERVER_IP" = "YOUR_SERVER_IP" ]; then
    echo -e "${RED}[ERROR] SERVER_IP set nahi hai!${NC}"
    echo -e "update.sh file kholein aur line 9 par apna server IP likhen."
    exit 1
fi

echo -e "${YELLOW}[INFO] Server: ${SERVER_USER}@${SERVER_IP}${NC}"
echo -e "${YELLOW}[INFO] Install Dir: ${INSTALL_DIR}${NC}"
echo ""

# Step 1: Server se connection check
echo -e "${BLUE}[1/4] Server connection check kar raha hai...${NC}"
if ! ssh -o ConnectTimeout=10 -o BatchMode=yes ${SERVER_USER}@${SERVER_IP} "echo ok" &>/dev/null; then
    echo -e "${RED}[ERROR] Server se connect nahi ho pa raha!${NC}"
    echo "SSH key setup hai? ya password required hai?"
    exit 1
fi
echo -e "${GREEN}[OK] Server connected!${NC}"
echo ""

# Step 2: Files sync karein (rsync se - only changed files)
echo -e "${BLUE}[2/4] Files sync kar raha hai (changed files only)...${NC}"
rsync -avz --progress \
    --exclude='.env' \
    --exclude='__pycache__/' \
    --exclude='*.pyc' \
    --exclude='venv/' \
    --exclude='.git/' \
    --exclude='*.log' \
    --exclude='attached_assets/' \
    --exclude='static/uploads/' \
    --exclude='.local/' \
    --exclude='*.sh' \
    ./ ${SERVER_USER}@${SERVER_IP}:${INSTALL_DIR}/

if [ $? -ne 0 ]; then
    echo -e "${RED}[ERROR] Files sync karne mein problem aayi!${NC}"
    exit 1
fi
echo -e "${GREEN}[OK] Files sync ho gayi!${NC}"
echo ""

# Step 3: Server par packages update karein (agar requirements.txt badla ho)
echo -e "${BLUE}[3/4] Python packages update kar raha hai...${NC}"
ssh ${SERVER_USER}@${SERVER_IP} "
    cd ${INSTALL_DIR}
    source venv/bin/activate
    pip install -r requirements.txt -q --upgrade
    echo 'Packages updated!'
"
echo -e "${GREEN}[OK] Packages updated!${NC}"
echo ""

# Step 4: Service restart karein
echo -e "${BLUE}[4/4] Service restart kar raha hai...${NC}"
ssh ${SERVER_USER}@${SERVER_IP} "
    systemctl restart ${SERVICE_NAME}
    sleep 2
    STATUS=\$(systemctl is-active ${SERVICE_NAME})
    if [ \"\$STATUS\" = \"active\" ]; then
        echo 'SERVICE_OK'
    else
        echo 'SERVICE_FAIL'
        systemctl status ${SERVICE_NAME} --no-pager -l
    fi
"

if ssh ${SERVER_USER}@${SERVER_IP} "systemctl is-active ${SERVICE_NAME}" | grep -q "active"; then
    echo -e "${GREEN}[OK] Service successfully restart ho gayi!${NC}"
else
    echo -e "${RED}[ERROR] Service start nahi hui! Status check karein:${NC}"
    echo "  ssh ${SERVER_USER}@${SERVER_IP} 'systemctl status ${SERVICE_NAME}'"
    exit 1
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   Update Complete! Site live hai.${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Site check karein: ${YELLOW}https://sattaking.com.im${NC}"
echo ""
