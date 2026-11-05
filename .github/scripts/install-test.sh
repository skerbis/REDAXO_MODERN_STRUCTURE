#!/usr/bin/env bash
#
# Installationstest: installiert die gebaute REDAXO-Struktur in einer Kopie
# gegen eine echte Datenbank, installiert alle Addons und ruft das Backend
# über den PHP-Webserver auf. Das Arbeitsverzeichnis bleibt unverändert.
#
# Umgebungsvariablen (mit Standardwerten):
#   DB_HOST=127.0.0.1  DB_PORT=3306  DB_USER=root  DB_PASSWORD=root  DB_NAME=redaxo_test
#
set -euo pipefail

ROOT="$(pwd)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-root}"
DB_NAME="${DB_NAME:-redaxo_test}"
HTTP_PORT="${HTTP_PORT:-8089}"

WORK="$(mktemp -d)"
SERVER_PID=""
cleanup() {
  if [[ -n "$SERVER_PID" ]]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$WORK"
}
trap cleanup EXIT

log() { echo -e "\n==> $*"; }

log "Kopie anlegen"
cp -R "$ROOT"/{public,src,var,bin} "$WORK"/
cd "$WORK"
# Bestehende Konfiguration/Caches (Update-Modus) ignorieren -> frische Installation
rm -rf var/cache/* var/data/core/config.yml

log "Datenbank vorbereiten"
DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_USER="$DB_USER" DB_PASSWORD="$DB_PASSWORD" DB_NAME="$DB_NAME" php -r '
  for ($i = 0; $i < 30; $i++) {
    try {
      $pdo = new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USER"), getenv("DB_PASSWORD"));
      $db = str_replace("`", "", getenv("DB_NAME"));
      $pdo->exec("DROP DATABASE IF EXISTS `$db`");
      $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
      exit(0);
    } catch (PDOException $e) {
      fwrite(STDERR, "Warte auf Datenbank: ".$e->getMessage()."\n");
      sleep(2);
    }
  }
  exit(1);
'

log "REDAXO Setup"
php bin/console setup:run -n \
  --lang=de_de \
  --agree-license \
  --server="http://127.0.0.1:${HTTP_PORT}/" \
  --servername="CI Test" \
  --error-email="ci@example.org" \
  --timezone="Europe/Berlin" \
  --db-host="${DB_HOST}:${DB_PORT}" \
  --db-login="$DB_USER" \
  --db-password="$DB_PASSWORD" \
  --db-name="$DB_NAME" \
  --db-createdb=no \
  --db-setup=override \
  --db-charset=utf8mb4 \
  --admin-username=admin \
  --admin-password='CiTest!2026-pass'

log "Addons installieren"
# Alle vorhandenen Addons; mehrere Durchläufe, damit Abhängigkeiten aufgelöst werden
PENDING=()
while IFS= read -r name; do PENDING+=("$name"); done < <(find src/addons -mindepth 1 -maxdepth 1 -type d -exec basename {} \; | sort)
for pass in 1 2 3 4 5; do
  NEXT=()
  for addon in "${PENDING[@]}"; do
    if php bin/console package:install -n "$addon" >/dev/null 2>&1 </dev/null; then
      echo "    ok: $addon"
    else
      NEXT+=("$addon")
    fi
  done
  if (( ${#NEXT[@]} == 0 )); then
    PENDING=()
    break
  fi
  if (( ${#NEXT[@]} == ${#PENDING[@]} )); then
    break
  fi
  echo "    Durchlauf $pass: ${#NEXT[@]} Addon(s) warten auf Abhängigkeiten"
  PENDING=("${NEXT[@]}")
done

if (( ${#PENDING[@]} )); then
  echo "ERROR: Folgende Addons ließen sich nicht installieren:" >&2
  for addon in "${PENDING[@]}"; do
    echo "--- $addon" >&2
    php bin/console package:install "$addon" >&2 || true
  done
  exit 1
fi

log "Installierte Pakete"
php bin/console package:list --installed-only

log "HTTP-Test"
php -S "127.0.0.1:${HTTP_PORT}" -t public >"$WORK/server.log" 2>&1 &
SERVER_PID=$!
sleep 2

check_url() {
  local url="$1" status
  status=$(curl -s -o "$WORK/body.html" -w '%{http_code}' "$url")
  echo "    $status $url"
  if [[ "$status" -ge 500 || "$status" == "000" ]] || grep -qiE 'fatal error|whoops|exception' "$WORK/body.html"; then
    echo "ERROR: Fehler beim Aufruf von $url" >&2
    head -c 3000 "$WORK/body.html" >&2 || true
    cat "$WORK/server.log" >&2 || true
    exit 1
  fi
}
# Frontend wird bewusst nicht geprüft: ohne Startartikel liefert yrewrite in einer
# leeren Installation keinen Inhalt.
check_url "http://127.0.0.1:${HTTP_PORT}/redaxo/index.php"

if ! grep -q 'rex-page-login' "$WORK/body.html"; then
  echo "ERROR: Backend-Login-Seite nicht gefunden." >&2
  head -c 3000 "$WORK/body.html" >&2
  exit 1
fi
echo "    Backend-Login erreichbar"

log "Installationstest erfolgreich"
