# VSCode Extension Compatibility Guide

Diese Anleitung erklärt, wie du REDAXO Modern Structure mit der [redaxo-multi-instances-vscode](https://github.com/FriendsOfREDAXO/redaxo-multi-instances-vscode) Extension verwenden kannst.

## 🎯 Überblick

Die VSCode Extension ermöglicht die einfache Verwaltung mehrerer REDAXO-Instanzen direkt in VS Code. Diese moderne Struktur ist jetzt vollständig kompatibel mit der Extension und **behält die moderne Ordnerstruktur bei**:

- ✅ **Moderne Ordnerstruktur** mit src/, public/, var/, bin/ Verzeichnissen
- ✅ **Alle vorinstallierten Addons** aus addons.txt verfügbar
- ✅ **Konfigurierbare Ports** für Apache, MySQL und phpMyAdmin
- ✅ **SSL/HTTPS Support** mit benutzerdefinierten Domains
- ✅ **Hosts-Datei Integration** für lokale Domains
- ✅ **Flexible PHP/MariaDB Versionen**
- ✅ **VSCode Extension Kompatibilität** (Service-Namen: redaxo + mysql)
- ✅ **Bestehende Funktionalität bleibt erhalten**

## 🚀 Schnellstart

### 1. VSCode Extension installieren

Installiere die [REDAXO Multi-Instances Manager Extension](https://github.com/FriendsOfREDAXO/redaxo-multi-instances-vscode) in VS Code.

### 2. Modern Structure für VSCode vorbereiten

```bash
# Repository klonen
git clone https://github.com/skerbis/REDAXO_MODERN_STRUCTURE.git
cd REDAXO_MODERN_STRUCTURE

# VSCode-kompatible Konfiguration kopieren
cp .env.vscode.example .env

# Notwendige Verzeichnisse erstellen
mkdir -p data/redaxo data/mysql mysql-init ssl
```

### 3. Mit VSCode-kompatiblem Docker Compose starten

```bash
# Mit VSCode-kompatiblem Docker Compose starten
docker-compose -f docker-compose.vscode.yml up -d

# Oder mit der Extension direkt aus VS Code verwalten
```

## ⚙️ Konfiguration

### Umgebungsvariablen (.env)

Die VSCode-kompatible `.env` Datei unterstützt alle wichtigen Konfigurationsoptionen:

```env
# Instanz-Konfiguration
INSTANCE_NAME=my-project
REDAXO_SERVERNAME=my-project

# Port-Konfiguration (muss eindeutig sein)
REDAXO_PORT=8080
HTTPS_PORT=8443
DATABASE_PORT=3306
PHPMYADMIN_PORT=8081

# SSL aktivieren
SSL_ENABLED=true
```

### Port-Verwaltung

Die moderne Struktur nutzt das bestehende Port-System:

```bash
# Port-Verfügbarkeit prüfen
./check-ports.sh

# Alternative Ports automatisch finden lassen
REDAXO_PORT=8090 PHPMYADMIN_PORT=8082 docker-compose -f docker-compose.vscode.yml up -d
```

### SSL/HTTPS Setup

Für SSL-Support mit benutzerdefinierten Domains:

```bash
# 1. mkcert installieren (macOS)
brew install mkcert nss
mkcert -install

# 2. SSL-Zertifikate generieren
mkdir -p ssl
mkcert -key-file ssl/my-project.local-key.pem -cert-file ssl/my-project.local.pem my-project.local

# 3. SSL in .env aktivieren
echo "SSL_ENABLED=true" >> .env

# 4. Domain zur Hosts-Datei hinzufügen
echo "127.0.0.1 my-project.local" | sudo tee -a /etc/hosts
```

## 🔄 Modi vergleichen

### Standard Modern Structure
- Custom Docker Images mit allen Addons
- Named Volumes für Datenpersistenz  
- Service-Namen: `redaxo`, `database`
- Optimiert für Produktion

### Entwicklungs-Modus 
- Custom Docker Images mit allen Addons
- Bind Mount des gesamten Projekts für Live-Entwicklung
- Service-Namen: `redaxo`, `database`  
- Optimiert für lokale Code-Entwicklung

### VSCode-kompatible Modus
- **Custom Docker Images mit moderner Struktur und allen Addons** 
- Bind Mounts nach `./data/redaxo/` mit vollständiger moderner Ordnerstruktur
- Service-Namen: `redaxo`, `mysql` (VSCode Extension Kompatibilität)
- **Enthält src/, public/, var/, bin/ Struktur mit allen vorinstallierten Addons**
- Optimiert für VSCode Extension Integration

## 📂 Verzeichnisstruktur (VSCode-Modus)

```
REDAXO_MODERN_STRUCTURE/
├── docker-compose.vscode.yml    # VSCode-kompatible Docker Compose
├── .env                         # Konfiguration (von .env.vscode.example)
├── custom-setup.sh              # Setup-Script für Container
├── apache-ssl.conf              # SSL Apache-Konfiguration
├── data/                        # Bind Mount Verzeichnisse
│   ├── redaxo/                 # Vollständige moderne REDAXO Struktur
│   │   ├── src/                # Quellcode (Addons, Core)
│   │   ├── public/             # Öffentliches Web-Verzeichnis
│   │   ├── var/                # Variable Daten (Cache, Logs)
│   │   └── bin/                # Konsolenscripts
│   └── mysql/                  # MySQL Daten
├── mysql-init/                 # MySQL Initialisierungsscripts  
└── ssl/                        # SSL Zertifikate
```

## 🎮 VS Code Extension verwenden

Nach der Installation der Extension:

1. **Command Palette**: `Cmd+Shift+P` → "REDAXO: Show Dashboard"
2. **Neue Instanz**: "REDAXO: Create New Instance"
3. **Instanz verwalten**: Start/Stop über TreeView oder Commands
4. **SSL einrichten**: "REDAXO: Setup HTTPS/SSL"
5. **Browser öffnen**: "REDAXO: Open Frontend/Backend"

## 🔧 Erweiterte Konfiguration

### Verschiedene PHP/MariaDB Versionen

```env
# .env Datei
REDAXO_IMAGE_TAG=5-edge        # Für neueste PHP/REDAXO Versionen
DB_VERSION=11.4                # MariaDB 11.4
```

### Custom REDAXO Downloads

```env
RELEASE_TYPE=custom
DOWNLOAD_URL=https://example.com/my-custom-redaxo.zip
```

### Mehrere Instanzen parallel

Für mehrere Instanzen einfach verschiedene Verzeichnisse mit unterschiedlichen Ports verwenden:

```bash
# Projekt 1
cd projekt1
REDAXO_PORT=8080 INSTANCE_NAME=projekt1 docker-compose -f docker-compose.vscode.yml up -d

# Projekt 2  
cd ../projekt2
REDAXO_PORT=8090 INSTANCE_NAME=projekt2 docker-compose -f docker-compose.vscode.yml up -d
```

## 🚨 Troubleshooting

### Port bereits belegt

```bash
# Automatische Port-Erkennung nutzen
./check-ports.sh

# Oder manuell andere Ports setzen
REDAXO_PORT=8090 docker-compose -f docker-compose.vscode.yml up -d
```

### SSL-Zertifikat nicht vertrauenswürdig

```bash
# mkcert neu installieren
mkcert -uninstall
mkcert -install

# Zertifikate neu generieren
rm -rf ssl/*
mkcert -key-file ssl/my-project.local-key.pem -cert-file ssl/my-project.local.pem my-project.local
```

### MySQL-Verbindungsfehler

```bash
# Container-Logs prüfen
docker-compose -f docker-compose.vscode.yml logs mysql

# MySQL-Passwort in .env prüfen
grep DB_PASSWORD .env
```

### Extension erkennt Instanz nicht

Stelle sicher, dass:
- Service-Namen `redaxo` und `mysql` verwendet werden
- `./data/` Verzeichnisstruktur existiert  
- `.env` Datei korrekt konfiguriert ist
- Docker Container laufen (`docker ps`)

## 🔄 Zwischen Modi wechseln

### Von Standard zu VSCode-Modus

```bash
# Bestehende Container stoppen
docker-compose down

# Daten sichern (falls gewünscht)
docker cp redaxo-app:/var/www/html/public/media ./backup-media
docker cp redaxo-app:/var/www/html/var ./backup-var

# VSCode-Modus starten
cp .env.vscode.example .env
mkdir -p data/redaxo data/mysql
docker-compose -f docker-compose.vscode.yml up -d
```

### Von VSCode zu Standard-Modus

```bash
# VSCode Container stoppen
docker-compose -f docker-compose.vscode.yml down

# Standard-Modus starten
cp .env.example .env
docker-compose up -d
```

## 💡 Tipps & Tricks

### Extension-spezifische Funktionen nutzen

- **TreeView**: Alle Instanzen in der Seitenleiste verwalten
- **Port Usage**: `REDAXO: Show Port Usage` zeigt belegte Ports
- **Container Terminal**: Direkter Zugang zur Shell
- **Database Info**: Zeigt alle DB-Verbindungsdetails
- **Automatic Repair**: Repariert defekte Instanzen

### Performance optimieren

```bash
# Named Volumes für bessere Performance (optional)
# In docker-compose.vscode.yml:
volumes:
  - redaxo_data:/var/www/html    # Statt ./data/redaxo
  - mysql_data:/var/lib/mysql    # Statt ./data/mysql
```

### Backup-Automatisierung

```bash
#!/bin/bash
# backup-instance.sh
INSTANCE=${1:-modern-structure}
BACKUP_DIR="./backups/$(date +%Y%m%d_%H%M%S)"

mkdir -p $BACKUP_DIR
docker-compose -f docker-compose.vscode.yml exec mysql mysqldump -u redaxo -predaxo redaxo > $BACKUP_DIR/database.sql
cp -r data/redaxo/public/media $BACKUP_DIR/
echo "Backup created in $BACKUP_DIR"
```

## 🤝 Support

Bei Problemen oder Fragen:

- **GitHub Issues**: [REDAXO_MODERN_STRUCTURE Issues](https://github.com/skerbis/REDAXO_MODERN_STRUCTURE/issues)
- **VSCode Extension Issues**: [redaxo-multi-instances-vscode Issues](https://github.com/FriendsOfREDAXO/redaxo-multi-instances-vscode/issues)
- **REDAXO Community**: [REDAXO Slack](https://redaxo.org/slack/)

---

**Die Kombination aus Modern Structure und VSCode Extension bietet die beste lokale REDAXO-Entwicklungsumgebung! 🚀**