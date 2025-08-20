# REDAXO Distro-Builder inspired by YAK / REPO Template

Diese GitHub Action automatisiert das Setup einer neuen REDAXO-Installation mit einer bestimmten Ordnerstruktur und der Möglichkeit, Addons zu installieren. Die Action ist für die manuelle Ausführung konzipiert und erstellt entweder ein Release mit einer ZIP-Datei (primärer Workflow) oder einen Pull Request (sekundärer Workflow).

## 🐳 Docker Setup (Empfohlen)

**Schnellster Weg zu REDAXO mit moderner Struktur:**

```bash
git clone https://github.com/skerbis/REDAXO_MODERN_STRUCTURE.git
cd REDAXO_MODERN_STRUCTURE
docker compose up -d
```

Fertig! REDAXO ist verfügbar unter: http://localhost:8080

📖 **Weitere Informationen:**
- [Docker Quickstart Guide](DOCKER.md)
- [Docker Examples](DOCKER-EXAMPLES.md)

## Funktionsweise

Die Action führt folgende Schritte aus:

1.  **Checkout Repository:** Checkt den aktuellen Code des Repository aus.
2.  **PHP Umgebung einrichten:** Richtet eine PHP 8.1 Umgebung mit den benötigten Extensions (zip, curl, gd) ein.
3.  **Node.js installieren:** Installiert Node.js
4.  **Yarn installieren:** Installiert Yarn
5.  **Neueste REDAXO Version ermitteln:** Ermittelt die neueste verfügbare REDAXO Version von der Github API.
6.  **Temporäre Verzeichnisse erstellen:** Erstellt temporäre Verzeichnisse für die Bearbeitung.
7.  **REDAXO herunterladen:** Lädt die REDAXO ZIP Datei der aktuellen Version herunter.
8.  **REDAXO entpacken:** Entpackt die REDAXO ZIP Datei in das `public` Verzeichnis.
9.  **Yakamara Ordnerstruktur erstellen:** Erstellt die spezifische Ordnerstruktur für Yakamara.
10. **Setup Dateien kopieren:** Kopiert die notwendigen Setup Dateien aus `.github/files` an die richtige Position.
11. **Addons herunterladen und installieren:** Lädt die konfigurierten Addons aus der `addons.txt` Datei herunter und installiert sie in das `src/addons` Verzeichnis.
12. **Temporäre Verzeichnisse entfernen:** Entfernt alle temporären Verzeichnisse.
13. **Release erstellen (primärer Workflow):** Erstellt ein GitHub Release mit einer ZIP-Datei, die die Version im Namen enthält (z.B. `redaxo-setup-5.15.0.zip`).
   
    *Alternative:* **Pull Request erstellen (sekundärer Workflow):** Erstellt einen Pull Request mit den erstellten Änderungen.

## Konfiguration

### Eingabeparameter

Diese Action hat keine Eingabeparameter. Die Konfiguration wird über Dateien im Repository gesteuert.

### Dateien

*   **`.github/workflows/create_release.yml`**: Die primäre Workflow-Datei, die ein Release mit ZIP-Datei erstellt.
*   **`.github/workflows/setup-redaxo.yml`**: Die sekundäre Workflow-Datei, die einen PR erstellt.
*   **`.github/workflows/docker-build.yml`**: Workflow zum Erstellen und Veröffentlichen von Docker Images.
*   **`Dockerfile`**: Produktions-Docker-Image für REDAXO mit allen Addons.
*   **`Dockerfile.dev`**: Entwicklungs-Docker-Image mit schnelleren Build-Zeiten.
*   **`docker-compose.yml`**: Docker Compose Konfiguration für Produktionsumgebung.
*   **`docker-compose.dev.yml`**: Docker Compose Konfiguration für Entwicklung.
*   **`.github/files/`**: Verzeichnis mit den benötigten Setup-Dateien:
    * `addon.project.boot.php`
    * `console`
    * `index.backend.php`
    * `index.frontend.php`
    * `AppPathProvider.php`
    * **`addons.txt`**: Eine Textdatei, die die URLs der Addon Repositories enthält. Jede URL steht in einer neuen Zeile. Diese muss im Root des Repository angelegt werden.

#### Beispiel für `addons.txt`

```
https://api.github.com/repos/FriendsOfREDAXO/adminer
https://api.github.com/repos/FriendsOfREDAXO/developer
https://api.github.com/repos/FriendsOfREDAXO/focuspoint
https://api.github.com/repos/FriendsOfREDAXO/mblock
https://api.github.com/repos/FriendsOfREDAXO/quick_navigation
https://api.github.com/repos/tbaddade/redaxo_sprog sprog
https://api.github.com/repos/tbaddade/redaxo_url
https://api.github.com/repos/tbaddade/redaxo_watson watson
https://api.github.com/repos/yakamara/ydeploy
https://api.github.com/repos/yakamara/yform
https://api.github.com/repos/yakamara/redaxo_yrewrite yrewrite
```
Die Ordner können umbenannt werden, wenn das original dem key nicht entspricht. Hierzu nach der URL ein Leerzeichen gefolgt vom gewünschten Ordnernamen angeben. 

## Verwendung

### Docker Verwendung (Empfohlen)

Die einfachste Möglichkeit, REDAXO mit der modernen Struktur zu verwenden, ist über Docker:

#### Schnellstart mit Docker

```bash
# Repository klonen
git clone https://github.com/skerbis/REDAXO_MODERN_STRUCTURE.git
cd REDAXO_MODERN_STRUCTURE

# Mit Docker Compose starten (Produktion)
docker-compose up -d

# Oder für Entwicklung mit automatischem Rebuild
docker-compose -f docker-compose.dev.yml up -d
```

Nach dem Start ist REDAXO verfügbar unter:
- **REDAXO Frontend**: http://localhost:8080
- **REDAXO Backend**: http://localhost:8080/redaxo
- **phpMyAdmin**: http://localhost:8081

**Standard Datenbank-Konfiguration für Docker:**
- Host: `database`
- Datenbank: `redaxo`
- Benutzer: `redaxo`
- Passwort: `redaxo`

#### Docker Images verwenden

```bash
# Produktions-Image verwenden
docker run -p 8080:80 ghcr.io/skerbis/redaxo_modern_structure:latest

# Entwicklungs-Image verwenden
docker run -p 8080:80 ghcr.io/skerbis/redaxo_modern_structure:dev
```

#### Eigene Addons konfigurieren

1. Passe die Datei `.github/files/addons.txt` an
2. Rebuild das Docker Image:
   ```bash
   docker-compose build --no-cache
   docker-compose up -d
   ```

### GitHub Actions Verwendung

1. Forken 
2. addons.txt anpassen

2.  **Erstelle einen Personal Access Token (PAT):**
    *   Gehe zu deinen GitHub-Einstellungen -> Entwicklereinstellungen -> Personal access tokens.
    *   Erstelle einen neuen Token mit dem `repo` Scope.
    *   Kopiere den Token.
3.  **Füge den PAT als Secret hinzu:**
    *   Navigiere zu deinem Repository -> Einstellungen -> Secrets -> Actions
    *   Erstelle ein neues Secret mit dem Namen `PAT_TOKEN` und füge den kopierten Token als Wert ein.

4.  **Führe den primären Release-Workflow manuell aus:**
    *   Gehe in deinem Repository zu "Actions".
    *   Wähle den Workflow "Create REDAXO Release" aus und klicke auf "Run workflow".
5.  **Lade die Release-ZIP herunter:**
    *   Nach erfolgreicher Ausführung des Workflows wird ein Release mit einer ZIP-Datei erstellt, die die REDAXO-Version im Namen trägt (z.B. `redaxo-setup-5.15.0.zip`).
    *   Das Release enthält alle Dateien, die für ein neues REDAXO-Projekt benötigt werden.

**Alternativ (sekundärer Workflow):**

4.  **Führe den PR-Workflow manuell aus:**
    *   Gehe in deinem Repository zu "Actions".
    *   Wähle den Workflow "Setup REDAXO" aus und klicke auf "Run workflow".
5.  **Überprüfe den Pull Request:**
    *   Nach erfolgreicher Ausführung der Action solltest du einen neuen Pull Request in deinem Repository sehen.
    *   Überprüfe die Änderungen, um sicherzustellen, dass alles korrekt ist.
6.  **Merge den Pull Request:**
    *   Wenn du mit den Änderungen zufrieden bist, merge den Pull Request in deinen Hauptbranch.

## Das Ergebnis 

**Übersicht der neuen Ordnerstruktur**

Die neue Ordnerstruktur zielt darauf ab, eine moderne Basis für REDAXO-Projekte zu schaffen, mit einer klaren Trennung von Ressourcen.

```
.
├── bin/                   # Binärdateien und Konsolenanwendungen
│   └── console            # REDAXO Konsole Anwendung
├── public/                # Öffentliches Verzeichnis (Webroot)
│   ├── assets/          # Öffentliche Assets
│   │   ├── addons/      # Öffentliche Addon Assets
│   │   └── core/       # Öffentliche Core Assets
│   ├── media/           # Medien Dateien
│   └── redaxo/          # REDAXO Backend
│       └── index.php      # Backend Index Datei
├── src/                   # Quellcode
│   ├── addons/           # REDAXO Addons
│   │   └── [addon_name]/  # Hier landen die runtergeladenen Addons
│   ├── core/            # REDAXO Core Dateien
│   └── AppPathProvider.php # PathProvider Datei
├── var/                   # Variable Daten
│   ├── cache/             # Cache Dateien
│   ├── data/              # Daten Dateien
│   └── log/               # Log Dateien
├── .gitignore             # Git Ignorier Datei
├── LICENSE                # Lizenzdatei
├── package.json           # Node.js Package Datei
└── addons.txt             # Liste der Addon URLs
```

**Erläuterung der Verzeichnisse und Dateien**

*   **`bin/`**:
    *   Enthält ausführbare Dateien und Konsolenanwendungen.
    *   **`console`**: Die REDAXO-Console-Anwendung, die Befehle ausführen kann.
*   **`public/`**:
    *   Das öffentliche Verzeichnis, das als Webroot dient und direkt vom Webserver zugänglich ist.
        *   **`assets/`**: Hier liegen die statischen Assets, die direkt über die Webseite ausgeliefert werden.
            *   **`addons/`**: Hier liegen die Assets der installierten Addons.
            *   **`core/`**: Hier liegen die Assets des REDAXO Cores.
        *   **`media/`**: Hier werden die hochgeladenen Medien-Dateien gespeichert.
        *   **`redaxo/`**: Das REDAXO Backend.
            *   **`index.php`**: Die Einstiegsdatei für das Backend.
*   **`src/`**:
    *   Das Verzeichnis für den Quellcode der Anwendung.
        *   **`addons/`**: Enthält die installierten REDAXO-Addons.
            *   **`[addon_name]/`**: Hier landen die heruntergeladenen Addons. Die Ordner haben den gleichen Namen wie das Addon selbst.
        *   **`core/`**: Enthält den Quellcode des REDAXO-Core.
        *   **`module/`**: Enthält die Dateien der REDAXO Module.
        *   **`templates/`**: Enthält die Dateien der REDAXO Templates.
        *   **`AppPathProvider.php`**: Der Path Provider, der zur Auflösung von Pfaden innerhalb der Anwendung genutzt wird.
*   **`var/`**:
    *   Enthält variable Daten, die während der Laufzeit der Anwendung benötigt werden.
    *   **`cache/`**: Enthält Cache-Dateien.
    *   **`data/`**: Enthält Daten-Dateien.
    *   **`log/`**: Enthält Log-Dateien.
*   **`.gitignore`**:
    *   Definiert Dateien und Ordner, die von Git ignoriert werden sollen.
*   **`LICENSE`**:
    *   Die Lizenzdatei des Projekts.
*   **`package.json`**:
    *   Eine Datei, die Informationen über die Node.js-Pakete enthält, die in deinem Projekt verwendet werden.
*   **`README.md`**:
    *   Die Readme-Datei des Projekts.
*   **`addons.txt`**:
    *   Eine Textdatei, die eine Liste von Addon-URLs enthält. Jede URL steht in einer neuen Zeile.

## Beiträge

Beiträge und Verbesserungsvorschläge sind herzlich willkommen!

## Lizenz

Diese Action ist unter der [MIT Lizenz](https://opensource.org/licenses/MIT) lizenziert.
