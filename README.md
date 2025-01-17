# REDAXO Distro-Builder inspired by YAK

Diese GitHub Action automatisiert das Setup einer neuen REDAXO-Installation mit einer bestimmten Ordnerstruktur und der Möglichkeit, Addons zu installieren. Die Action ist für die manuelle Ausführung konzipiert.

## Funktionsweise

Die Action führt folgende Schritte aus:

1.  **Checkout Repository:** Checkt den aktuellen Code des Repository aus.
2.  **PHP Umgebung einrichten:** Richtet eine PHP 8.1 Umgebung mit den benötigten Extensions (zip, curl, gd) ein.
3.  **Node.js installieren:** Installiert Node.js
4.  **Yarn installieren:** Installiert Yarn
5.  **Setup Dateien herunterladen:** Lädt die benötigten Setup Dateien (`setup.ini`, `addon.project.boot.php`, `console`, `index.backend.php`, `index.frontend.php`, `AppPathProvider.php`) aus dem `yakamara/yak` Repository herunter.
6.  **ini Modul installieren:** Installiert das `ini` Modul für Node.js, um die INI Datei auszulesen.
7.  **Neueste REDAXO Version ermitteln:** Ermittelt die neueste verfügbare REDAXO Version von der Github API.
8.  **Konfiguration einlesen:** Liest die `setup.ini` Datei aus und speichert die REDAXO Version und die SHA Summe.
9.  **Addons einlesen:** Liest die Liste der Addons aus der `addons.txt` Datei.
10. **Temporäre Verzeichnisse erstellen:** Erstellt temporäre Verzeichnisse für die Bearbeitung.
11. **REDAXO herunterladen:** Lädt die REDAXO ZIP Datei der aktuellen Version herunter.
12. **REDAXO entpacken:** Entpackt die REDAXO ZIP Datei in das `public` Verzeichnis.
13. **Yakamara Ordnerstruktur erstellen:** Erstellt die spezifische Ordnerstruktur für Yakamara.
14. **Setup Dateien kopieren:** Kopiert die notwendigen Setup Dateien an die richtige Position.
15. **Addons herunterladen und installieren:** Lädt die konfigurierten Addons aus der `addons.txt` Datei herunter und installiert sie in das `src/addons` Verzeichnis.
16. **Temporäre Verzeichnisse entfernen:** Entfernt alle temporären Verzeichnisse.
17. **Pull Request erstellen:** Erstellt einen Pull Request mit den erstellten Änderungen.

## Konfiguration

### Eingabeparameter

Diese Action hat keine Eingabeparameter. Die Konfiguration wird über Dateien im Repository gesteuert.

### Dateien

*   **`.github/workflows/setup-redaxo.yml`**: Die Workflow Datei der Action, die im Root des Projekts erstellt werden muss.
*  **`addons.txt`**: Eine Textdatei, die die URLs der Addon Repositories enthält. Jede URL steht in einer neuen Zeile. Diese muss im Root des Repository angelegt werden.

#### Beispiel für `addons.txt`

```
https://api.github.com/repos/FriendsOfREDAXO/adminer
https://api.github.com/repos/FriendsOfREDAXO/developer
https://api.github.com/repos/FriendsOfREDAXO/focuspoint
https://api.github.com/repos/FriendsOfREDAXO/mblock
https://api.github.com/repos/FriendsOfREDAXO/quick_navigation
https://api.github.com/repos/tbaddade/redaxo_sprog
https://api.github.com/repos/tbaddade/redaxo_url
https://api.github.com/repos/tbaddade/redaxo_watson
https://api.github.com/repos/yakamara/ydeploy
https://api.github.com/repos/yakamara/yform
https://api.github.com/repos/yakamara/redaxo_yrewrite
```

## Verwendung

1.  **Erstelle einen Personal Access Token (PAT):**
    *   Gehe zu deinen GitHub-Einstellungen -> Entwicklereinstellungen -> Personal access tokens.
    *   Erstelle einen neuen Token mit dem `repo` Scope.
    *   Kopiere den Token.
2.  **Füge den PAT als Secret hinzu:**
    *   Navigiere zu deinem Repository -> Einstellungen -> Secrets -> Actions
    *   Erstelle ein neues Secret mit dem Namen `PAT_TOKEN` und füge den kopierten Token als Wert ein.
3.  **Erstelle die `addons.txt` Datei im Root des Repository**.
4. **Füge den folgenden Workflow in `.github/workflows/setup-redaxo.yml` Datei ein**

    ```yaml
    name: Setup REDAXO

    on:
      workflow_dispatch:

    jobs:
      setup:
        runs-on: ubuntu-latest
        steps:
          # 1. Checkout des Repository
          - name: Checkout Repository
            uses: actions/checkout@v4
            with:
              ref: ${{ github.head_ref }}

          # 2. PHP Umgebung einrichten
          - name: Set up PHP
            uses: shivammathur/setup-php@v2
            with:
              php-version: '8.1'
              extensions: zip, curl, gd

          # 3. Node.js installieren
          - name: Install Node.js
            uses: actions/setup-node@v4
            with:
              node-version: '20'

          # 4. Yarn installieren
          - name: Install Yarn
            run: |
              sudo apt-get update
              sudo apt-get install -y yarn

          # 5. Setup Dateien herunterladen
          - name: Download setup files from yakamara/yak
            run: |
              SETUP_URL="https://raw.githubusercontent.com/yakamara/yak/main/setup"
              mkdir -p setup
              curl -Ls "$SETUP_URL/setup.ini" -o setup/setup.ini
              curl -Ls "$SETUP_URL/addon.project.boot.php" -o setup/addon.project.boot.php
              curl -Ls "$SETUP_URL/console" -o setup/console
              curl -Ls "$SETUP_URL/index.backend.php" -o setup/index.backend.php
              curl -Ls "$SETUP_URL/index.frontend.php" -o setup/index.frontend.php
              curl -Ls "$SETUP_URL/AppPathProvider.php" -o setup/AppPathProvider.php

          # 6. ini Modul für Node.js installieren
          - name: Install ini module for Node.js
            run: npm install ini

          # 7. Neuste REDAXO Version ermitteln
          - name: Get latest REDAXO release
            id: get_redaxo_release
            run: |
              RELEASE_URL=$(curl -s "https://api.github.com/repos/redaxo/redaxo/releases/latest" | jq -r ".tag_name")
              echo "::set-output name=redaxo_version::$RELEASE_URL"
              SHA=$(curl -Ls "https://github.com/redaxo/redaxo/releases/download/$RELEASE_URL/redaxo_$RELEASE_URL.zip" | shasum -a 1 | awk '{print $1}')
              echo "::set-output name=redaxo_sha::$SHA"

          # 8. Konfiguration einlesen
          - name: Read and prepare Configuration from setup.ini
            id: config
            uses: actions/github-script@v7
            with:
              script: |
                const fs = require('fs');
                const ini = require('ini');
                const configString = fs.readFileSync('./setup/setup.ini', 'utf-8');
                const config = ini.parse(configString);
                core.setOutput('redaxo_version', "${{ steps.get_redaxo_release.outputs.redaxo_version }}");
                core.setOutput('redaxo_sha', "${{ steps.get_redaxo_release.outputs.redaxo_sha }}");
    
          # 9. Temporäre Verzeichnisse erstellen
          - name: Create temporary directories
            run: |
              mkdir -p tmp/redaxo

          # 10. REDAXO herunterladen
          - name: Download REDAXO
            run: |
              VERSION="${{ steps.get_redaxo_release.outputs.redaxo_version }}"
              SHA="${{ steps.get_redaxo_release.outputs.redaxo_sha }}"
              FILE="tmp/redaxo/redaxo_${VERSION}.zip"
              curl -Ls "https://github.com/redaxo/redaxo/releases/download/${VERSION}/redaxo_${VERSION}.zip" -o "${FILE}"
              echo "File SHA: $(sha1sum ${FILE})"
              if [[ "$(sha1sum ${FILE} | awk '{print $1}')" != "${SHA}" ]]; then
                echo "ERROR: SHA-Hash is incorrect."
                exit 1
              fi

          # 11. REDAXO entpacken
          - name: Unzip REDAXO
            run: |
              VERSION="${{ steps.get_redaxo_release.outputs.redaxo_version }}"
              FILE="tmp/redaxo/redaxo_${VERSION}.zip"
              unzip "${FILE}" -d public

          # 12. Yakamara Ordnerstruktur erstellen
          - name: Create Yakamara file structure
            run: |
              mkdir -p var
              mkdir -p src
              mv public/redaxo/bin/ bin
              mv public/redaxo/cache var/cache
              mv public/redaxo/data var/data
              mv public/redaxo/src/addons src/addons
              mv public/redaxo/src/core src/core
              mv public/LICENSE.md LICENSE.md
              rm public/README.md
              rm public/README.de.md

          # 13. Setup Dateien kopieren
          - name: Copy setup files
            run: |
              cp setup/addon.project.boot.php src/addons/project/boot.php
              cp setup/console bin/console
              cp setup/index.backend.php public/redaxo/index.php
              cp setup/index.frontend.php public/index.php
              cp setup/AppPathProvider.php src/AppPathProvider.php

          # 14. Addons herunterladen und installieren
          - name: Download and Install Addons
            run: |
              while IFS= read -r addon_url; do
                echo "installing addon: $addon_url"
                
                ADDON_NAME=$(echo "$addon_url" | sed -E 's/.*\///g')
                ADDON_REAL_NAME=$(echo "$addon_url" | sed -E 's/.*\///g')

                RELEASE_URL="$addon_url/releases/latest"
                echo  "RELEASE_URL: $RELEASE_URL"
                ZIP_URL=$(curl -s -H 'User-Agent: PHP' "$RELEASE_URL" | jq -r '.zipball_url')
                echo  "ZIP_URL: $ZIP_URL"
                
                curl -Ls "$ZIP_URL" -o "tmp/$ADDON_REAL_NAME.zip"
                unzip "tmp/$ADDON_REAL_NAME.zip" -d tmp
                 
                ADDON_DIR=$(find tmp -maxdepth 1 -type d -name "*-${ADDON_REAL_NAME}-*")
                if [[ -n "$ADDON_DIR" ]]; then
                   echo "Found addon dir: $ADDON_DIR"
                   mv "$ADDON_DIR" "src/addons/$ADDON_NAME"
                else
                   echo "Addon directory not found for $ADDON_REAL_NAME"
                fi
                rm "tmp/$ADDON_REAL_NAME.zip"
              done < addons.txt
            shell: bash

          # 15. Temporäre Verzeichnisse entfernen
          - name: Remove temporary directories
            run: |
              rm -rf tmp/redaxo
              rm -rf setup
              find tmp -maxdepth 1 -type d -exec rm -rf {} +
            
          # 16. Pull Request erstellen
          - name: Create Pull Request
            id: create_pr
            uses: peter-evans/create-pull-request@v5
            with:
              token: ${{ secrets.PAT_TOKEN }}
              branch: redaxo-setup
              title: "Setup REDAXO structure"
              body: "Automatischer Setup der REDAXO Struktur"
              base: ${{ github.head_ref || github.ref_name }}
              commit-message: "Setup REDAXO structure"
    ```

5.  **Führe die Action manuell aus:**
    *   Gehe in deinem Repository zu "Actions".
    *   Wähle den Workflow "Setup REDAXO" aus und klicke auf "Run workflow".
6.  **Überprüfe den Pull Request:**
    *   Nach erfolgreicher Ausführung der Action solltest du einen neuen Pull Request in deinem Repository sehen.
    *   Überprüfe die Änderungen, um sicherzustellen, dass alles korrekt ist.
7.  **Merge den Pull Request:**
    *   Wenn du mit den Änderungen zufrieden bist, merge den Pull Request in deinen Hauptbranch.

## Wichtige Hinweise

*   Stelle sicher, dass die Dateistruktur in deinem Repository korrekt ist (`.github/workflows`, `setup.ini`, `addons.txt`).
*   Der `PAT_TOKEN` muss Schreibzugriff auf dein Repository haben.
*   Die Addon URLs in der `addons.txt` Datei müssen vollständig und korrekt sein und zu einem gültigen Github Repository führen.


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
        *    **`redaxo/`**: Das REDAXO Backend.
           *  **`index.php`**: Die Einstiegsdatei für das Backend.
*  **`src/`**:
    *  Das Verzeichnis für den Quellcode der Anwendung.
       *   **`addons/`**: Enthält die installierten REDAXO-Addons.
           *   **`[addon_name]/`**: Hier landen die heruntergeladenen Addons. Die Ordner haben den gleichen Namen wie das Addon selbst.
       *    **`core/`**: Enthält den Quellcode des REDAXO-Core.
       *    **`module/`**: Enthält die Dateien der REDAXO Module.
      *    **`templates/`**: Enthält die Dateien der REDAXO Templates.
        *    **`AppPathProvider.php`**: Der Path Provider, der zur Auflösung von Pfaden innerhalb der Anwendung genutzt wird.
*   **`var/`**:
    *   Enthält variable Daten, die während der Laufzeit der Anwendung benötigt werden.
    *   **`cache/`**: Enthält Cache-Dateien.
    *   **`data/`**: Enthält Daten-Dateien.
    *   **`log/`**: Enthält Log-Dateien.
*   **`.gitignore`**:
    *  Definiert Dateien und Ordner, die von Git ignoriert werden sollen..
*   **`LICENSE`**:
    *   Die Lizenzdatei des Projekts.
*   **`package.json`**:
    *   Eine Datei, die Informationen über die Node.js-Pakete enthält, die in deinem Projekt verwendet werden.
*   **`README.md`**:
    *   Die Readme-Datei des Projekts..
*   **`addons.txt`**:
    *   Eine Textdatei, die eine Liste von Addon-URLs enthält. Jede URL steht in einer neuen Zeile.





## Beiträge

Beiträge und Verbesserungsvorschläge sind herzlich willkommen!

## Lizenz

Diese Action ist unter der [MIT Lizenz](https://opensource.org/licenses/MIT) lizenziert.


