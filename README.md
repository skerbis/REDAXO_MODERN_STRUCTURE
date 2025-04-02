# REDAXO Distro-Builder inspired by YAK / REPO Template

Diese GitHub Action automatisiert das Setup einer neuen REDAXO-Installation mit einer bestimmten Ordnerstruktur und der Möglichkeit, Addons zu installieren. Die Action ist für die manuelle Ausführung konzipiert.

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
13. **Pull Request erstellen:** Erstellt einen Pull Request mit den erstellten Änderungen.

## Konfiguration

### Eingabeparameter

Diese Action hat keine Eingabeparameter. Die Konfiguration wird über Dateien im Repository gesteuert.

### Dateien

*   **`.github/workflows/setup-redaxo.yml`**: Die Workflow Datei der Action, die im Root des Projekts erstellt werden muss.
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

1. Forken 
2. addons.txt anpassen

2.  **Erstelle einen Personal Access Token (PAT):**
    *   Gehe zu deinen GitHub-Einstellungen -> Entwicklereinstellungen -> Personal access tokens.
    *   Erstelle einen neuen Token mit dem `repo` Scope.
    *   Kopiere den Token.
3.  **Füge den PAT als Secret hinzu:**
    *   Navigiere zu deinem Repository -> Einstellungen -> Secrets -> Actions
    *   Erstelle ein neues Secret mit dem Namen `PAT_TOKEN` und füge den kopierten Token als Wert ein.

4.  **Führe die Action manuell aus:**
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
│   ├── module/          # REDAXO Module Dateien
│   └── templates/      # REDAXO Template Dateien
│   └── AppPathProvider.php # PathProvider Datei
├── var/                   # Variable Daten
│   ├── cache/             # Cache Dateien
│   ├── data/             # Daten Dateien
│   └── log/              # Log Dateien
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
