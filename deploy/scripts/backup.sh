#!/usr/bin/env bash
# Nightly encrypted backup of the database and private uploads.
# cron (root): 30 2 * * * /var/www/school/deploy/scripts/backup.sh >> /var/log/school-backup.log 2>&1
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/school}"
DEST="${BACKUP_DIR:-/var/backups/school}"        # outside the web root
GPG_RECIPIENT="${BACKUP_GPG_RECIPIENT:?set BACKUP_GPG_RECIPIENT (public key id)}"
STAMP="$(TZ=Asia/Dhaka date +%Y%m%d-%H%M)"

set -a; source <(grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' "$APP_DIR/.env" | sed 's/"//g'); set +a
mkdir -p "$DEST/daily" "$DEST/weekly"
umask 077

mysqldump --single-transaction --routines --triggers --set-gtid-purged=OFF \
  -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip | gpg --encrypt --recipient "$GPG_RECIPIENT" > "$DEST/daily/db-$STAMP.sql.gz.gpg"

tar -C "$APP_DIR/storage/app" -czf - private public \
  | gpg --encrypt --recipient "$GPG_RECIPIENT" > "$DEST/daily/files-$STAMP.tar.gz.gpg"

# Weekly copy on Fridays, retention 14 daily / 8 weekly
if [ "$(date +%u)" = "5" ]; then cp "$DEST/daily/"*"$STAMP"* "$DEST/weekly/"; fi
find "$DEST/daily" -type f -mtime +14 -delete
find "$DEST/weekly" -type f -mtime +56 -delete

echo "Backup $STAMP OK"
