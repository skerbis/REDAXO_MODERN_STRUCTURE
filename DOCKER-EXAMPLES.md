# Docker Examples für REDAXO Modern Structure

Diese Datei enthält praktische Beispiele für die Verwendung der Docker-Setup.

## Beispiel 1: Produktionsumgebung starten

```bash
# Repository klonen
git clone https://github.com/skerbis/REDAXO_MODERN_STRUCTURE.git
cd REDAXO_MODERN_STRUCTURE

# Container starten
docker compose up -d

# Status prüfen
docker compose ps

# Logs verfolgen
docker compose logs -f

# REDAXO Setup öffnen
# http://localhost:8080/redaxo
```

## Beispiel 2: Entwicklungsumgebung mit eigenen Addons

1. **Addons konfigurieren:**
   ```bash
   # .github/files/addons.txt bearbeiten
   echo "https://api.github.com/repos/FriendsOfREDAXO/mform" >> .github/files/addons.txt
   ```

2. **Image neu bauen:**
   ```bash
   docker compose build --no-cache
   docker compose up -d
   ```

## Beispiel 3: Entwicklung mit Live-Reload

```bash
# Entwicklungsumgebung starten
docker compose -f docker-compose.dev.yml up -d

# Jetzt sind deine lokalen Dateien im Container verfügbar
# Änderungen werden sofort sichtbar
```

## Beispiel 4: Verschiedene Ports verwenden

Wenn Port 8080 bereits belegt ist:

```yaml
# In docker-compose.yml ändern:
services:
  redaxo:
    ports:
      - "8090:80"  # Statt 8080:80
```

## Beispiel 5: Backup erstellen

```bash
# Datenbank-Backup
docker compose exec database mysqldump -u redaxo -predaxo redaxo > backup.sql

# Media-Dateien sichern
docker compose cp redaxo:/var/www/html/public/media ./media-backup
```

## Beispiel 6: Backup wiederherstellen

```bash
# Datenbank wiederherstellen
docker compose exec -T database mysql -u redaxo -predaxo redaxo < backup.sql

# Media-Dateien wiederherstellen
docker compose cp ./media-backup/. redaxo:/var/www/html/public/media/
```

## Beispiel 7: Container in Shell öffnen

```bash
# REDAXO Container
docker compose exec redaxo bash

# Datenbank Container
docker compose exec database mysql -u redaxo -predaxo redaxo
```

## Beispiel 8: Logs filtern

```bash
# Nur Fehler anzeigen
docker compose logs --level ERROR

# Nur die letzten 100 Zeilen
docker compose logs --tail=100

# Bestimmter Service
docker compose logs database
```

## Beispiel 9: Container-Ressourcen begrenzen

```yaml
# In docker-compose.yml
services:
  redaxo:
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: '0.5'
```

## Beispiel 10: Mehrere Umgebungen parallel

```bash
# Projekt 1
cd projekt1
docker compose -p redaxo-projekt1 up -d

# Projekt 2 (andere Ports)
cd ../projekt2
# In docker-compose.yml: ports: "8081:80"
docker compose -p redaxo-projekt2 up -d
```

## Beispiel 11: PHP 8.3 mit MariaDB verwenden

```bash
# .env Datei erstellen
cat > .env << EOF
PHP_VERSION=8.3
DB_TYPE=mariadb
DB_VERSION=11.4
DB_ROOT_PASSWORD=redaxo
DB_NAME=redaxo
DB_USER=redaxo
DB_PASSWORD=redaxo
EOF

# Container bauen und starten
docker compose build --no-cache
docker compose up -d
```

## Beispiel 12: PHP 8.2 mit MySQL 8.4 verwenden

```bash
# .env Datei erstellen
cat > .env << EOF
PHP_VERSION=8.2
DB_TYPE=mysql
DB_VERSION=8.4
EOF

# Container bauen und starten
docker compose build --no-cache
docker compose up -d
```

## Beispiel 13: Entwicklung mit PHP 8.1 und MariaDB

```bash
# .env Datei für Entwicklung
cat > .env << EOF
PHP_VERSION=8.1
DB_TYPE=mariadb
DB_VERSION=10.11
EOF

# Entwicklungsumgebung starten
docker compose -f docker-compose.dev.yml build --no-cache
docker compose -f docker-compose.dev.yml up -d
```

## Beispiel 14: Version-Matrix testen

```bash
# Test verschiedener PHP/DB Kombinationen
versions=(
  "PHP_VERSION=8.1 DB_TYPE=mysql DB_VERSION=8.0"
  "PHP_VERSION=8.2 DB_TYPE=mariadb DB_VERSION=10.11"
  "PHP_VERSION=8.3 DB_TYPE=mysql DB_VERSION=8.4"
)

for version in "${versions[@]}"; do
  echo "Testing: $version"
  echo "$version" > .env
  docker compose build --no-cache
  docker compose up -d
  # Test deine Anwendung hier
  docker compose down
done
```