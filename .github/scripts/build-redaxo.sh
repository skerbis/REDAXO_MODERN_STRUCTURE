#!/usr/bin/env bash
#
# Baut oder aktualisiert eine REDAXO-Installation in moderner (Yakamara-)
# Ordnerstruktur im aktuellen Arbeitsverzeichnis (= Repository-Root).
#
# Optionale Umgebungsvariablen:
#   BUILD_MODE               auto (Standard) | fresh | update
#                              fresh:  komplette Neuinstallation, bricht ab, wenn schon eine existiert
#                              update: ersetzt nur Core, System-Addons und Addons aus addons.txt;
#                                      project-Addon, var/, public/media, eigene Dateien bleiben unberührt
#                              auto:   update, wenn src/core existiert, sonst fresh
#   GH_TOKEN / GITHUB_TOKEN  Token für die GitHub-API (vermeidet Rate-Limits)
#   REDAXO_VERSION           Feste REDAXO-Version statt "latest"
#   GITHUB_OUTPUT            Wird von GitHub Actions gesetzt; erhält redaxo_version und build_mode
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
output() { if [[ -n "${GITHUB_OUTPUT:-}" ]]; then echo "$1=$2" >> "$GITHUB_OUTPUT"; fi; }

# Verzeichnis ersetzen: Ziel löschen, Quelle verschieben
replace_dir() {
  rm -rf "$2"
  mkdir -p "$(dirname "$2")"
  mv "$1" "$2"
}

# ---------------------------------------------------------------------------
# 0. Modus bestimmen
# ---------------------------------------------------------------------------
MODE="${BUILD_MODE:-auto}"
if [[ "$MODE" == "auto" ]]; then
  if [[ -d src/core ]]; then MODE=update; else MODE=fresh; fi
fi
case "$MODE" in
  fresh)
    for p in public src var bin; do
      if [[ -e "$p" ]]; then
        echo "ERROR: '$p' existiert bereits. Für bestehende Projekte BUILD_MODE=update verwenden," >&2
        echo "       oder die Ordner vorher löschen." >&2
        exit 1
      fi
    done
    ;;
  update)
    if [[ ! -d src/core ]]; then
      echo "ERROR: BUILD_MODE=update, aber src/core existiert nicht." >&2
      exit 1
    fi
    ;;
  *)
    echo "ERROR: Unbekannter BUILD_MODE '$MODE' (erlaubt: auto, fresh, update)." >&2
    exit 1
    ;;
esac
echo "Modus: $MODE"
output build_mode "$MODE"

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
output redaxo_version "$VERSION"

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

# ---------------------------------------------------------------------------
# 3. Ordnerstruktur in einem Staging-Verzeichnis erstellen
# ---------------------------------------------------------------------------
log "Ordnerstruktur erstellen"
STAGE="$TMP_DIR/stage"
mkdir -p "$STAGE/public"
unzip -q "$ZIP" -d "$STAGE/public"
(
  cd "$STAGE"
  mkdir -p var/log src
  mv public/redaxo/bin bin
  mv public/redaxo/cache var/cache
  mv public/redaxo/data var/data
  mv public/redaxo/src/addons src/addons
  mv public/redaxo/src/core src/core
  rm -rf public/redaxo/src
  mv public/LICENSE.md LICENSE.md
  rm -f public/README.md public/README.de.md

  cp "$FILES_DIR/addon.project.boot.php" src/addons/project/boot.php
  cp "$FILES_DIR/console" bin/console
  chmod +x bin/console
  cp "$FILES_DIR/index.backend.php" public/redaxo/index.php
  cp "$FILES_DIR/index.frontend.php" public/index.php
  cp "$FILES_DIR/AppPathProvider.php" src/AppPathProvider.php
)

if [[ "$MODE" == "fresh" ]]; then
  mv "$STAGE"/{public,src,var,bin,LICENSE.md} "$ROOT"/
  cp -R "$FILES_DIR/docker/." "$ROOT"/
else
  # Nur von REDAXO verwaltete Teile ersetzen
  replace_dir "$STAGE/src/core" src/core
  for dir in "$STAGE"/src/addons/*/; do
    name="$(basename "$dir")"
    [[ "$name" == "project" ]] && continue
    echo "    System-Addon aktualisiert: $name"
    replace_dir "$dir" "src/addons/$name"
  done
  replace_dir "$STAGE/public/assets/core" public/assets/core
  [[ -d src/addons/project ]] || mv "$STAGE/src/addons/project" src/addons/project
  [[ -f src/AppPathProvider.php ]] || cp "$STAGE/src/AppPathProvider.php" src/AppPathProvider.php
  [[ -f bin/console ]] || { mkdir -p bin; cp "$STAGE/bin/console" bin/console; }
  [[ -f public/index.php ]] || cp "$STAGE/public/index.php" public/index.php
  [[ -f public/redaxo/index.php ]] || { mkdir -p public/redaxo; cp "$STAGE/public/redaxo/index.php" public/redaxo/index.php; }
  mkdir -p var/log
  cp "$STAGE/LICENSE.md" LICENSE.md
  # Docker-Rezept nur ergänzen, nie eigene Anpassungen überschreiben
  [[ -e compose.yaml ]] || { cp -R "$FILES_DIR/docker/docker" "$ROOT"/; cp "$FILES_DIR/docker/compose.yaml" "$ROOT"/; }
  [[ -e .gitignore ]] || cp "$FILES_DIR/docker/.gitignore" "$ROOT"/
fi

# ---------------------------------------------------------------------------
# 4. Addons laden
# ---------------------------------------------------------------------------
log "Addons laden"
FAILED=()
ADDONS_JSON="{}"

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

  # Neuestes Release, sonst aktueller Commit des Default-Branches
  if RELEASE=$("${CURL_API[@]}" "$API_URL/releases/latest" 2>/dev/null); then
    REF=$(jq -r '.tag_name' <<<"$RELEASE")
    LABEL="$REF"
    echo "    Release: $REF"
  else
    BRANCH=$("${CURL_API[@]}" "$API_URL" | jq -r '.default_branch')
    REF=$("${CURL_API[@]}" "$API_URL/commits/$BRANCH" | jq -r '.sha')
    LABEL="$BRANCH@${REF:0:7}"
    echo "    Kein Release, nutze $LABEL"
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

  replace_dir "$ADDON_DIR" "src/addons/$ADDON_NAME"
  ADDONS_JSON=$(jq --arg n "$ADDON_NAME" --arg r "$REPO_PATH" --arg v "$LABEL" \
    '.[$n] = {repo: $r, version: $v}' <<<"$ADDONS_JSON")
done < "$ADDONS_FILE"

if (( ${#FAILED[@]} )); then
  echo "ERROR: Folgende Addons konnten nicht geladen werden: ${FAILED[*]}" >&2
  exit 1
fi

# ---------------------------------------------------------------------------
# 5. Manifest schreiben
# ---------------------------------------------------------------------------
log "versions.json schreiben"
jq -n --arg v "$VERSION" --argjson a "$ADDONS_JSON" '{redaxo: $v, addons: $a}' > versions.json
cat versions.json

# ---------------------------------------------------------------------------
# 6. Smoke-Test
# ---------------------------------------------------------------------------
log "Smoke-Test"
for f in src/AppPathProvider.php public/index.php public/redaxo/index.php bin/console; do
  php -l "$f" >/dev/null
done
php bin/console --version

log "Fertig: REDAXO $VERSION ($MODE)"
