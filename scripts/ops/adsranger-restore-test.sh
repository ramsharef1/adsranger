#!/usr/bin/env bash
# A-5 (IDEA-043): monthly restore test. Restores the newest nightly backup into a temporary database, checks it,
# compares row counts with the live database (read only), drops the temporary database and writes the result for
# /admin. Installed by the cl93 deploy at /usr/local/bin/adsranger-restore-test.sh; cron /etc/cron.d/adsranger-restore-test.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
set -uo pipefail
D="/root/archive/db-backups"
TEST_DB="adssaas_restore_test"
OUT="/home/adssaas/app/storage/app/restore-test.json"
PG="/usr/sbin/runuser -u postgres --"
START=$(date +%s)
NOW=$(date -u +%Y-%m-%dT%H:%M:%SZ)

write() { # ok backup message tables_json
  printf '{"at":"%s","ok":%s,"backup":"%s","seconds":%s,"message":"%s","tables":%s}\n' "$NOW" "$1" "$2" "$(( $(date +%s) - START ))" "$3" "$4" > "$OUT.tmp"
  mv "$OUT.tmp" "$OUT"; chown adssaas:adssaas "$OUT"
  echo "$(TZ=Asia/Amman date '+%F %H:%M') ok=$1 $2 $3"
}

F=$(ls -t "$D"/adssaas-*.sql.gz 2>/dev/null | head -1)
[ -n "$F" ] || { write false "" "no backup file found in $D" "[]"; exit 1; }
B=$(basename "$F")

$PG dropdb --if-exists "$TEST_DB" >/dev/null 2>&1
if ! $PG createdb "$TEST_DB" >/dev/null 2>&1; then write false "$B" "could not create the temporary database" "[]"; exit 1; fi
if ! gunzip -c "$F" | $PG psql -q -v ON_ERROR_STOP=1 -d "$TEST_DB" >/dev/null 2>/tmp/adsranger-restore-test.err; then
  MSG=$(head -c 160 /tmp/adsranger-restore-test.err | tr '"\n\\' "' /")
  $PG dropdb --if-exists "$TEST_DB" >/dev/null 2>&1
  write false "$B" "restore failed: $MSG" "[]"; exit 1
fi

count() { $PG psql -tAq -d "$1" -c "select count(*) from $2" 2>/dev/null || echo -1; }
TABLES="agencies users clients ad_accounts daily_metrics audit_logs"
JSON="["; OK=true
for t in $TABLES; do
  R=$(count "$TEST_DB" "$t"); L=$(count adssaas "$t")
  [ "$R" = "-1" ] && OK=false
  JSON="$JSON{\"table\":\"$t\",\"restored\":$R,\"live\":$L},"
done
JSON="${JSON%,}]"
for t in agencies users; do [ "$(count "$TEST_DB" "$t")" -gt 0 ] 2>/dev/null || OK=false; done

$PG dropdb --if-exists "$TEST_DB" >/dev/null 2>&1
if [ "$OK" = true ]; then write true "$B" "restored and checked" "$JSON"; else write false "$B" "restored, but a core table is missing or empty" "$JSON"; exit 1; fi
