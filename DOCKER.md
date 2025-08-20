# Docker Quickstart Guide für REDAXO Modern Structure

Dieser Guide hilft dir dabei, schnell mit REDAXO und der modernen Ordnerstruktur zu starten.

## Voraussetzungen

- [Docker](https://docs.docker.com/get-docker/) installiert
- [Docker Compose](https://docs.docker.com/compose/install/) installiert

## Schnellstart

### 1. Repository klonen

```bash
git clone https://github.com/skerbis/REDAXO_MODERN_STRUCTURE.git
cd REDAXO_MODERN_STRUCTURE
```

### 2. REDAXO starten

**Für Produktion:**
```bash
docker compose up -d
```

**Für Entwicklung:**
```bash
docker compose -f docker-compose.dev.yml up -d
```

### 3. REDAXO Setup aufrufen

Nach dem Start der Container ist REDAXO unter folgenden URLs verfügbar:

- **Frontend**: http://localhost:8080
- **Backend Setup**: http://localhost:8080/redaxo
- **phpMyAdmin**: http://localhost:8081

### 4. REDAXO Installation

1. Gehe zu http://localhost:8080/redaxo
2. Folge dem Setup-Assistenten
3. Verwende diese Datenbankeinstellungen:
   - **Server**: `database`
   - **Datenbank**: `redaxo`
   - **Benutzer**: `redaxo`
   - **Passwort**: `redaxo`

## Container verwalten

### Container stoppen
```bash
# Produktionsumgebung
docker compose down

# Entwicklung  
docker compose -f docker-compose.dev.yml down
```

### Container neustarten
```bash
# Produktionsumgebung
docker compose restart

# Entwicklung
docker compose -f docker-compose.dev.yml restart
```

### Logs anzeigen
```bash
# Alle Container
docker compose logs -f

# Nur REDAXO Container
docker compose logs -f redaxo

# Nur Datenbank
docker compose logs -f database
```

## Daten persistieren

Die Docker-Setup verwendet Volumes um Daten persistent zu speichern:
- `redaxo_db`: MySQL Datenbank
- `redaxo_media`: REDAXO Media-Dateien  
- `redaxo_var`: REDAXO Konfiguration und Cache

Diese Volumes bleiben erhalten, auch wenn die Container gestoppt werden.

## Addons anpassen

1. Bearbeite die Datei `.github/files/addons.txt`
2. Füge gewünschte Addon-Repository-URLs hinzu
3. Rebuild das Docker Image:
   ```bash
   docker compose build --no-cache
   docker compose up -d
   ```

## Troubleshooting

### Port bereits belegt
Falls Port 8080 bereits verwendet wird:
```bash
# In docker-compose.yml oder docker-compose.dev.yml ändern
ports:
  - "8090:80"  # Statt 8080:80
```

### Container startet nicht
```bash
# Container-Status prüfen
docker compose ps

# Logs prüfen
docker compose logs redaxo
```

### Neustart mit frischen Daten
```bash
# Alle Container und Volumes löschen
docker compose down -v
docker compose up -d
```

## Entwicklung

Für die Entwicklung ist es empfehlenswert, die Development-Version zu verwenden:

```bash
docker compose -f docker-compose.dev.yml up -d
```

Diese Version mountet das Projektverzeichnis, sodass Änderungen direkt sichtbar sind.