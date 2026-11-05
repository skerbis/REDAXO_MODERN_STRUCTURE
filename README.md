# REDAXO Distro-Builder inspired by YAK / REPO Template

Diese GitHub Action automatisiert das Setup einer neuen REDAXO-Installation mit einer bestimmten Ordnerstruktur und der Möglichkeit, Addons zu installieren. Die Action ist für die manuelle Ausführung konzipiert und erstellt entweder ein Release mit einer ZIP-Datei (primärer Workflow) oder einen Pull Request (sekundärer Workflow).

## Funktionsweise

Die Build-Logik liegt in `.github/scripts/build-redaxo.sh` und wird von beiden Workflows genutzt (lokal ausführbar mit `bash .github/scripts/build-redaxo.sh`). Sie führt folgende Schritte aus:

1.  **Neueste REDAXO Version ermitteln:** über die GitHub API (`redaxo/core`); optional kann beim Start des Workflows eine feste Version angegeben werden.
2.  **REDAXO herunterladen:** und die SHA256-Prüfsumme gegen den von GitHub gelieferten Asset-Digest prüfen.
3.  **Yakamara Ordnerstruktur erstellen:** REDAXO entpacken und in `public/`, `src/`, `var/` und `bin/` aufteilen.
4.  **Setup Dateien kopieren:** aus `.github/files` an die richtige Position.
5.  **Addons herunterladen:** aus `addons.txt` (neuestes Release, sonst Default-Branch) nach `src/addons`. Schlägt ein Download fehl, bricht der Build ab.
6.  **Smoke-Test:** PHP-Syntaxcheck der Einstiegsdateien und `php bin/console --version`.
7.  **Release erstellen (primärer Workflow):** GitHub Release mit ZIP-Datei, die die Version im Namen enthält (z.B. `redaxo-setup-5.21.6.zip`).

    *Alternative:* **Pull Request erstellen (sekundärer Workflow):** Erstellt einen Pull Request mit den erstellten Änderungen.

Die Workflows nutzen PHP 8.4. Node.js und Yarn werden nicht benötigt.

## Konfiguration

### Eingabeparameter

Diese Action hat keine Eingabeparameter. Die Konfiguration wird über Dateien im Repository gesteuert.

### Dateien

*   **`.github/workflows/create_release.yml`**: Die primäre Workflow-Datei, die ein Release mit ZIP-Datei erstellt.
*   **`.github/workflows/setup-redaxo.yml`**: Die sekundäre Workflow-Datei, die einen PR erstellt.
*   **`.github/files/`**: Verzeichnis mit den benötigten Setup-Dateien:
    * `addon.project.boot.php`
    * `console`
    * `index.backend.php`
    * `index.frontend.php`
    * `AppPathProvider.php`
    * **`addons.txt`**: Eine Textdatei, die die URLs der Addon Repositories enthält. Jede URL steht in einer neuen Zeile. Sie liegt unter `.github/files/addons.txt`.

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

3.  **Optional: Personal Access Token (PAT) anlegen.** Ohne PAT wird der eingebaute `GITHUB_TOKEN` genutzt. Für den PR-Workflow muss dann unter *Settings → Actions → General* die Option „Allow GitHub Actions to create and approve pull requests“ aktiv sein. Mit PAT:
    *   Gehe zu deinen GitHub-Einstellungen -> Entwicklereinstellungen -> Personal access tokens.
    *   Erstelle einen neuen Token mit dem `repo` Scope.
    *   Kopiere den Token.
    **PAT als Secret hinzufügen:**
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
└── LICENSE.md             # Lizenzdatei
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
*   **`LICENSE.md`**:
    *   Die Lizenzdatei des Projekts.
*   **`README.md`**:
    *   Die Readme-Datei des Projekts.

## Beiträge

Beiträge und Verbesserungsvorschläge sind herzlich willkommen!

## Lizenz

Diese Action ist unter der [MIT Lizenz](https://opensource.org/licenses/MIT) lizenziert.
