#!/usr/bin/env bash
#
# Baut eine REDAXO-Installation in moderner (Yakamara-)Ordnerstruktur
# im aktuellen Arbeitsverzeichnis (= Repository-Root).
#
# Optionale Umgebungsvariablen:
#   GH_TOKEN / GITHUB_TOKEN  Token für die GitHub-API (vermeidet Rate-Limits)
#   REDAXO_VERSION           Feste REDAXO-Version statt "latest"
#   GITHUB_OUTPUT            Wird von GitHub Actions gesetzt; erhält redaxo_version
#
set -euo pipefail

ROOT="$(pwd)"
FILES_DIR="$ROOT/.github/files"
ADDONS_FILE="$FILES_DIR/addons.txt"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

TOKEN="${GH_TOKEN:-${GITHUB_TOKEN:-}}"
CURL_API=(curl -fsSL -H "Accept: application/vnd.github+json" -H "User-Agent: redaxo-modern-structure")
if [[ -n "$TOKEN" ]]; then
  CURL_API+=(-H "Authorization: Bearer $TOKEN")
fi

log() { echo -e "\n==> $*"; }

# ---------------------------------------------------------------------------
# 1. REDAXO Release ermitteln
# ---------------------------------------------------------------------------
log "REDAXO Release ermitteln"
if [[ -n "${REDAXO_VERSION:-}" ]]; then
  RELEASE_JSON=$("${CURL_API[@]}" "https://api.github.com/repos/redaxo/core/releases/tags/${REDAXO_VERSION}")
else
  RELEASE_JSON=$("${CURL_API[@]}" "https://api.github.com/repos/redaxo/core/releases/latest")
fi
VERSION=$(jq -r '.tag_name' <<<"$RELEASE_JSON")
ASSET_NAME="redaxo_${VERSION}.zip"
ASSET_URL=$(jq -r --arg n "$ASSET_NAME" '.assets[] | select(.name == $n) | .browser_download_url' <<<"$RELEASE_JSON")
ASSET_DIGEST=$(jq -r --arg n "$ASSET_NAME" '.assets[] | select(.name == $n) | .digest // empty' <<<"$RELEASE_JSON")

if [[ -z "$VERSION" || "$VERSION" == "null" || -z "$ASSET_URL" ]]; then
  echo "ERROR: REDAXO Release oder Asset $ASSET_NAME nicht gefunden." >&2
  exit 1
fi
echo "Version: $VERSION"
if [[ -n "${GITHUB_OUTPUT:-}" ]]; then
  echo "redaxo_version=$VERSION" >> "$GITHUB_OUTPUT"
fi

# ---------------------------------------------------------------------------
# 2. REDAXO herunterladen und Prüfsumme verifizieren
# ---------------------------------------------------------------------------
log "REDAXO $VERSION herunterladen"
ZIP="$TMP_DIR/$ASSET_NAME"
curl -fsSL "$ASSET_URL" -o "$ZIP"

if [[ "$ASSET_DIGEST" == sha256:* ]]; then
  EXPECTED="${ASSET_DIGEST#sha256:}"
  ACTUAL=$( (sha256sum "$ZIP" 2>/dev/null || shasum -a 256 "$ZIP") | awk '{print $1}')
  echo "SHA256 erwartet: $EXPECTED"
  echo "SHA256 aktuell:  $ACTUAL"
  if [[ "$EXPECTED" != "$ACTUAL" ]]; then
    echo "ERROR: SHA256-Prüfsumme stimmt nicht." >&2
    exit 1
  fi
else
  echo "WARNUNG: Kein Digest von GitHub geliefert, Prüfsumme wird übersprungen."
fi
unzip -tq "$ZIP" >/dev/null

# ---------------------------------------------------------------------------
# 3. Entpacken und Ordnerstruktur erstellen
# ---------------------------------------------------------------------------
log "Ordnerstruktur erstellen"
rm -rf public src var bin LICENSE.md
unzip -q "$ZIP" -d public

mkdir -p var src
mv public/redaxo/bin bin
mv public/redaxo/cache var/cache
mv public/redaxo/data var/data
mv public/redaxo/src/addons src/addons
mv public/redaxo/src/core src/core
rmdir public/redaxo/src 2>/dev/null || true
mv public/LICENSE.md LICENSE.md
rm -f public/README.md public/README.de.md

cp "$FILES_DIR/addon.project.boot.php" src/addons/project/boot.php
cp "$FILES_DIR/console" bin/console
chmod +x bin/console
cp "$FILES_DIR/index.backend.php" public/redaxo/index.php
cp "$FILES_DIR/index.frontend.php" public/index.php
cp "$FILES_DIR/AppPathProvider.php" src/AppPathProvider.php

# ---------------------------------------------------------------------------
# 4. Addons laden
# ---------------------------------------------------------------------------
log "Addons laden"
FAILED=()

while IFS= read -r line || [[ -n "$line" ]]; do
  line="${line%$'\r'}"
  [[ -z "${line// }" || "$line" =~ ^[[:space:]]*# ]] && continue

  ADDON_URL=$(awk '{print $1}' <<<"$line")
  TARGET_DIR=$(awk '{print $2}' <<<"$line")
  REPO_PATH="${ADDON_URL#https://api.github.com/repos/}"
  REPO_PATH="${REPO_PATH#https://github.com/}"
  REPO_PATH="${REPO_PATH%/}"
  REPO_NAME="${REPO_PATH##*/}"
  API_URL="https://api.github.com/repos/$REPO_PATH"

  echo "--- $REPO_PATH"

  # Neuestes Release, sonst Default-Branch
  if RELEASE=$("${CURL_API[@]}" "$API_URL/releases/latest" 2>/dev/null); then
    REF=$(jq -r '.tag_name' <<<"$RELEASE")
    echo "    Release: $REF"
  else
    REF=$("${CURL_API[@]}" "$API_URL" | jq -r '.default_branch')
    echo "    Kein Release, nutze Branch: $REF"
  fi

  WORK="$TMP_DIR/addon-$REPO_NAME"
  mkdir -p "$WORK"
  if ! "${CURL_API[@]}" "$API_URL/zipball/$REF" -o "$WORK/addon.zip"; then
    echo "    ERROR: Download fehlgeschlagen"
    FAILED+=("$REPO_PATH")
    continue
  fi
  unzip -q "$WORK/addon.zip" -d "$WORK/x"
  ADDON_DIR=$(find "$WORK/x" -mindepth 1 -maxdepth 1 -type d | head -1)

  if [[ -n "$TARGET_DIR" ]]; then
    ADDON_NAME="$TARGET_DIR"
  elif [[ -f "$ADDON_DIR/package.yml" ]]; then
    ADDON_NAME=$(grep -m1 -E '^package:' "$ADDON_DIR/package.yml" | sed -E "s/^package:[[:space:]]*//; s/[\"'[:space:]]//g")
    ADDON_NAME="${ADDON_NAME:-$REPO_NAME}"
  else
    ADDON_NAME="$REPO_NAME"
  fi
  echo "    -> src/addons/$ADDON_NAME"

  rm -rf "src/addons/$ADDON_NAME"
  mv "$ADDON_DIR" "src/addons/$ADDON_NAME"
done < "$ADDONS_FILE"

if (( ${#FAILED[@]} )); then
  echo "ERROR: Folgende Addons konnten nicht geladen werden: ${FAILED[*]}" >&2
  exit 1
fi

# ---------------------------------------------------------------------------
# 5. Smoke-Test
# ---------------------------------------------------------------------------
log "Smoke-Test"
for f in src/AppPathProvider.php public/index.php public/redaxo/index.php bin/console; do
  php -l "$f" >/dev/null
done
php bin/console --version

log "Fertig: REDAXO $VERSION"
