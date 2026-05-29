#!/bin/bash
# ================================================================
# pView Alert System — daily backup
# ----------------------------------------------------------------
# Dumps the MySQL database AND tarballs writable/uploads/.
# Keeps the last RETENTION_DAYS days of backups.
#
# Schedule via cron (see docs/cron.example):
#   30 2 * * *  /home/pview/apache_pview/htdocs/pview_alerts/docs/backup.sh >> /var/log/pview-backup.log 2>&1
#
# Prereqs: mysqldump, tar, gzip in PATH.
# ================================================================

set -euo pipefail

# ----- CONFIG (edit before first run) ---------------------------
PROJECT_DIR="/home/pview/apache_pview/htdocs/pview_alerts"
BACKUP_DIR="/home/pview/backups/pview_alerts"
RETENTION_DAYS=14

DB_HOST="127.0.0.1"
DB_NAME="pview_alerts"
DB_USER="pview_alerts"
# Read DB password from .env so this script doesn't carry secrets.
# .env line we look for:  database.default.password = secret123
DB_PASS=$(grep -E '^database\.default\.password\s*=' "$PROJECT_DIR/.env" | sed -E 's/^[^=]+=\s*//; s/^"//; s/"$//')

if [ -z "$DB_PASS" ]; then
  echo "[$(date '+%F %T')] ERROR: could not read DB password from .env" >&2
  exit 1
fi

mkdir -p "$BACKUP_DIR"

STAMP=$(date '+%Y%m%d-%H%M%S')
DB_FILE="$BACKUP_DIR/db-$STAMP.sql.gz"
UP_FILE="$BACKUP_DIR/uploads-$STAMP.tar.gz"

# ----- DB dump --------------------------------------------------
echo "[$(date '+%F %T')] dumping database $DB_NAME → $DB_FILE"
mysqldump \
  --host="$DB_HOST" \
  --user="$DB_USER" \
  --password="$DB_PASS" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  "$DB_NAME" | gzip -9 > "$DB_FILE"

# ----- uploads tarball ------------------------------------------
if [ -d "$PROJECT_DIR/writable/uploads" ]; then
  echo "[$(date '+%F %T')] archiving uploads → $UP_FILE"
  tar -czf "$UP_FILE" -C "$PROJECT_DIR/writable" uploads/
else
  echo "[$(date '+%F %T')] no uploads directory yet, skipping tarball"
fi

# ----- retention -------------------------------------------------
echo "[$(date '+%F %T')] pruning backups older than $RETENTION_DAYS days"
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'db-*.sql.gz'      -mtime +$RETENTION_DAYS -print -delete
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'uploads-*.tar.gz' -mtime +$RETENTION_DAYS -print -delete

# ----- summary ---------------------------------------------------
DB_SIZE=$(stat -c%s "$DB_FILE" 2>/dev/null || echo 0)
echo "[$(date '+%F %T')] done. db_bytes=$DB_SIZE"
