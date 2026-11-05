# MediaPlace – Entwicklung am Overlay-Kern (mediaplace.js)

Dieses Dokument beschreibt zwei Dinge: den Build-Workflow für den
Overlay-Kern (`window.MP`) und die Schnittstellen, über die andere Addons
mit MediaPlace zusammenarbeiten (eigene Erweiterungspunkte, JS-API,
Berechtigungen, REST-Endpunkte).

Die Quelle liegt als echte ES-Module unter `src/mediaplace/` und wird per
[esbuild](https://esbuild.github.io/) zu der einen ausgelieferten Datei
`assets/mediaplace.js` gebündelt – nach demselben Muster, das im
`code`-Addon (Monaco-Editor) bereits produktiv läuft.

Alle anderen Assets des Addons (`mediaplace-helpers.js`, `mediaplace-api.js`,
`mediaplace-i18n.js`, `mediaplace_widget.js`, `mediaplace_classic.js`,
`mediaplace.css`, ...) sind davon **nicht** betroffen – die bleiben normale,
von Hand editierte Dateien wie bisher, kein Build-Step nötig.

## Verzeichnis-Layout

```
src/addons/mediaplace/
  src/mediaplace/          <- Quelle (echte ES-Module, hier wird entwickelt)
    index.js                  Einstiegspunkt für den Bundle (importiert nur core.js)
    core.js                   Hub: importiert alle modules/*.js, verdrahtet sie
                               per ctx-Objekt, enthaelt daneben noch nicht
                               extrahierten Code (Data-Loading, build()/open()/
                               close(), zentrale Event-Delegation)
    modules/                  Feature-Module (siehe unten)
  assets/mediaplace.js     <- Build-OUTPUT, von esbuild generiert.
                               NICHT VON HAND EDITIEREN – jede Änderung geht beim
                               nächsten `npm run build` verloren.
  package.json, build.js   <- Build-Tooling
```

**Wichtig:** `src/mediaplace/` liegt bewusst **außerhalb** von `assets/`, nicht
darunter. `bin/console assets:sync` synct den kompletten `assets/`-Ordner 1:1
in den öffentlichen Assets-Pfad – läge die Quelle dort drin, würde
unminifizierter Quellcode mit veröffentlicht.

## Setup

Voraussetzung: Node.js (irgendeine aktuelle LTS-Version reicht, getestet mit
Node 24). Einmalig im Addon-Verzeichnis:

```bash
cd src/addons/mediaplace
npm install
```

## Bauen

```bash
npm run build   # einmaliger, minifizierter Build -> assets/mediaplace.js (Auslieferungsstand)
npm run watch   # Inkrementalbau bei jeder Änderung, unminifiziert + Inline-Sourcemap (Entwicklung)
```

**Deploy-Reihenfolge ist entscheidend:** immer erst `npm run build`, **dann**
`assets:sync` + `cache:clear`. Wird das vergessen, landet ein veralteter Stand
von `assets/mediaplace.js` im Backend (die Datei ändert sich ja nicht von
selbst, nur weil sich `src/mediaplace/*.js` geändert hat).

```bash
npm run build
docker exec -w /var/www/html fairplayweb php bin/console assets:sync
docker exec -w /var/www/html fairplayweb php bin/console cache:clear
```

Der committete `assets/mediaplace.js` ist der tatsächliche Auslieferungsstand
des Addons (wie bei `code`/`cropper`/`mblock` in diesem Repo) – Endnutzer, die
das Addon installieren, führen kein `npm install`/`npm run build` aus. Jede
Änderung an `src/mediaplace/*.js` muss also **vor dem Commit** neu gebaut
werden, sonst weicht das Verhalten des committeten Bundles von der Quelle ab.

## Architektur: Hub-and-Spoke

Die ursprünglich geplante Kernel/State/Bus-Architektur (zentraler Event-Bus,
`ctx.state.*`-Getter/Setter-Layer) wurde beim tatsächlichen Umbau durch ein
einfacheres, direkteres Muster ersetzt:

- **`core.js` ist der Hub.** Er importiert benannte Funktionen direkt aus
  jedem `modules/*.js` und ruft sie als normale Funktionen auf (kein ctx nötig
  für die Richtung core.js → Modul).
- **Jedes Modul exportiert eine `initX(ctx)`-Funktion**, die `core.js` einmal
  innerhalb von `build()` aufruft. `ctx` ist ein einfaches Objekt aus
  DOM-Referenzen, Gettern/Settern für State, der noch in `core.js` lebt, und
  Funktionsreferenzen (z. B. `loadFiles`, `refreshDisplay`) – das deckt die
  Richtung Modul → core.js ab. Jede Extraktion dokumentiert ihren `ctx`
  explizit in einem "ctx-Vertrag"-Docblock direkt über `initX()`.
- **Module, die etwas von einem Geschwistermodul brauchen, importieren es
  direkt** (z. B. importiert `filters.js` `applyCollectionFilter` aus
  `collections.js`) – kein Umweg über core.js oder einen Event-Bus.
- **Kein Laufzeit-Registrierungsmechanismus** (`MP.registerModule()` o. ä.):
  Bildbearbeitung, Fokuspunkt- und Optimieren-Integration sind normale Module wie
  jedes andere, per `import` fest in `core.js` verdrahtet und Teil desselben
  Haupt-Bundles – nicht (wie ursprünglich angedacht) separate, nur bei
  installiertem Fremd-Addon geladene Zusatz-Bundles. Das wäre ein größerer,
  eigenständiger Architekturumbau; bislang nicht umgesetzt.

## Module (Stand: alle 12 Extraktionsphasen abgeschlossen)

`providers.js`, `modals.js`, `lightbox.js`, `focuspoint.js`, `image_editor.js`,
`optimize.js`, `collections.js`, `categories.js`, `filters.js`, `grid.js`,
`detail.js`, `upload.js`, `multiselect.js`.

Bewusst weiterhin in `core.js` (nicht extrahiert, siehe jeweilige
ctx-Vertrag-Kommentare in den Modulen für die genaue Begründung):
Data-Loading (`loadFiles()`/`buildMediaEndpoint()`/`fetchTypeCounts()`),
`build()`/`open()`/`close()` selbst, die zentrale Event-Delegation auf
`overlay`, sowie diverses noch geteiltes State (`currentCat`, `selectedFile`,
`multiMode`, `viewMode`, ...), das an zu vielen Stellen direkt gelesen/
geschrieben wird, um sich ohne unverhältnismäßigen ctx-Umweg sauber
auslagern zu lassen.

## Schnittstellen für Drittanbieter-Addons

MediaPlace ist an mehreren Stellen bewusst offen für andere Addons gebaut,
statt Fähigkeiten hart zu verdrahten. Vier Kategorien:

### 1. Eigene PHP-Erweiterungspunkte (`rex_extension::register`)

| Erweiterungspunkt | Definiert in | Zweck |
|---|---|---|
| `MEDIAPLACE_WIDGET_TYPES` | `lib/MetainfoWidget.php` (`getRegisteredTypes()`) | Eigenen Feldtyp fürs Detail-Panel registrieren (Widget-Typ-Dropdown, Speicherlogik, Rendering) |
| `MEDIAPLACE_STORAGE_PROVIDERS` | `lib/StorageProviderRegistry.php` (`getAllProviders()`) | Eigene Cloud-/externe Quelle als zusätzlichen Sidebar-Baum einklinken (Browsen + Import) |
| `MEDIAPLACE_UPLOAD_PROVIDERS` | `lib/UploadProviderRegistry.php` (`getAllProviders()`) | Eigenen Upload-Dialog als Ersatz für den eingebauten Upload-Button/Drag&Drop anbieten (nur EIN Provider gleichzeitig aktiv, siehe Einstellung "Upload-Anbieter") |
| `YFORM_MEDIA_IS_IN_USE` | `lib/yform/value/yform_value_mediaplace.php` (`isMediaInUse()`) | Zusätzliche YForm-Tabellen/Felder in die "Datei in Verwendung"-Prüfung einbeziehen |
| `FOCUSPOINT_PREVIEW_SELECT` | `lib/FocuspointIntegration.php` (`getTypesForImage()`) | Dieselbe EP wie im eigenständigen `focuspoint`-Addon, hier innerhalb des MediaPlace-Canvas re-gefeuert – bestehende Registrierungen auf diese EP funktionieren unverändert auch hier |

**`MEDIAPLACE_WIDGET_TYPES`** – Subject ist
`array<string, array{label:string, class:class-string, fragment:string}>`,
Startwert die 6 eingebauten Typen (`text`/`textarea`/`checkbox`/`select`/
`alt`/`media_link`). Registrierte Klasse muss `MetainfoWidgetInterface`
implementieren (eine einzige Methode `normalizeValue()`, siehe
`lib/MetainfoWidgetInterface.php` – bewusst kein Zwang, von der abstrakten
`MetainfoWidget` zu erben) und einen Konstruktor mit `MetainfoField`
akzeptieren. Das `fragment` bekommt dieselben Variablen wie die eingebauten
`detail_field_body_*.php` (`$field`, `$value`, `$info`, `$clangs`). Für
Feldwerte, die nicht ins generische Sammel-Muster passen (ein
`data-json-field`-Element, skalar oder pro Sprache), siehe
`MP.registerFieldCollector()` unten.

```php
rex_extension::register('MEDIAPLACE_WIDGET_TYPES', function (rex_extension_point $ep) {
    $types = $ep->getSubject();
    $types['my_widget'] = [
        'label' => 'Mein Feldtyp',
        'class' => MyAddon\MyWidget::class, // implements MetainfoWidgetInterface
        'fragment' => 'my_addon/detail_field_body_my_widget.php',
    ];
    return $types;
});
```

**`MEDIAPLACE_STORAGE_PROVIDERS`** – Subject ist
`array<string, array{label:string, icon:string, perm:string, class:class-string<StorageProviderInterface>}>`,
Startwert `[]`. Jeder Provider bringt sein **eigenes** Recht mit (kein
globaler MediaPlace-Schalter) – `getAvailableProviders()` filtert per
`$user->hasPerm($provider['perm'])`. Registrierte Klasse muss
`StorageProviderInterface` implementieren (`listEntries()`, `hasSearch()`,
`getThumbnail()`, `importToMediaPool()` – siehe
`lib/StorageProviderInterface.php` für die exakten Rückgabeformen). `$path`
ist provider-intern und wird von MediaPlace nur durchgereicht. Reales
Beispiel: das `nextcloud`-Addon.

Der Massenimport (Mehrfachauswahl oder „Alle im aktuellen Ordner“ im
Overlay, `func=import_batch` am `mediaplace_provider`-Endpunkt) braucht
**keine** Erweiterung dieses Interfaces – `Api\Provider::handleImportBatch()`
ruft lediglich `importToMediaPool()` je ausgewähltem Pfad in einer Schleife
auf. Ein neuer Provider funktioniert dadurch automatisch mit, sobald er die
vier Interface-Methoden implementiert.

```php
rex_extension::register('MEDIAPLACE_STORAGE_PROVIDERS', function (rex_extension_point $ep) {
    $providers = $ep->getSubject();
    $providers['my_provider'] = [
        'label' => 'Mein Cloud-Speicher',
        'icon' => 'fa-solid fa-cloud',
        'perm' => 'my_addon[mediaplace_browse]', // EIGENES Recht
        'class' => MyAddon\MyStorageProvider::class, // implements StorageProviderInterface
    ];
    return $providers;
});
```

**`MEDIAPLACE_UPLOAD_PROVIDERS`** – Subject ist
`array<string, array{label:string, perm:string}>`, Startwert `[]`. Kein
`class`-Feld (anders als bei Storage-Providern) – die Übernahme läuft rein
clientseitig über `MP.registerUploadProvider()` (siehe unten), PHP liefert
hier nur Label + Recht für die Auswahlliste auf der Einstellungsseite. Jeder
Provider bringt wieder sein **eigenes** Recht mit. Anders als bei
Storage-Providern (koexistieren alle gleichzeitig) ist immer nur **ein**
Upload-Provider aktiv – welcher, entscheidet die Einstellung
"Upload-Anbieter" (`rex_config`-Key `upload_provider`, leer = eingebauter
Upload). Reales Beispiel: das `filepond_uploader`-Addon.

```php
rex_extension::register('MEDIAPLACE_UPLOAD_PROVIDERS', function (rex_extension_point $ep) {
    $providers = $ep->getSubject();
    $providers['my_uploader'] = [
        'label' => 'Mein Uploader',
        'perm' => 'my_addon[mediaplace_upload]', // EIGENES Recht
    ];
    return $providers;
});
```

Auf der JS-Seite muss dasselbe Addon zusätzlich `MP.registerUploadProvider()`
aufrufen (siehe unten) – erst wenn BEIDES vorhanden ist (hier registriert
**und** als aktiver Provider eingestellt **und** clientseitig tatsächlich
registriert), greift die Delegation. Ist der eingestellte Provider (noch)
nicht registriert (Addon deaktiviert, Script nicht geladen), bleibt es beim
eingebauten Upload – kein Fehlerzustand.

### 2. Client-seitige Erweiterung (`window.MP`)

Drei echte Registrierungs-Mechanismen im JS-Kern:

**`MP.registerFieldCollector(widgetType, collector)`** – das
JS-Gegenstück zu `MEDIAPLACE_WIDGET_TYPES` für Widget-Typen, deren Wert sich
nicht über das generische Ein-`data-json-field`-Element-Muster einsammeln
lässt. `collector(key, panelEl)` liefert den zu speichernden Wert zurück
(`null` löscht das Feld). Siehe `src/mediaplace/modules/detail.js`
(`fieldCollectors`) für den Konsumenten.

**`MP.registerUploadProvider(id, handler)`** – siehe Abschnitt 1 oben
(`MEDIAPLACE_UPLOAD_PROVIDERS`).

**`MP.registerAdminMenuItem(id, { label, icon, onClick })`** – eigener
Eintrag im Zahnrad-Menü, der JS-Code INNERHALB des laufenden Overlays
ausführt (`onClick()`, ohne Argumente), statt wie die klassische
Unterseiten-Liste im selben Menü immer eine echte Seite/ein Popup zu öffnen.
`icon` ist eine `fa-solid`-Klasse (optional, Default ein Zauberstab-Icon).
Rendert in `#mp-admin-menu-extensions`, oberhalb der klassischen
Unterseiten-Links. Registrierung ist bewusst mehrfach-sicher: kann vor ODER
nach dem ersten `open()` aufgerufen werden (Eintrag bleibt über
open()/close()-Zyklen hinweg bestehen). Noch kein externer Nutzer – mediaplace's
eigener "KI-Alt-Text-Generator" (optionale KI-Alt-Text-Generierung, siehe
Einstellungen → "KI-Funktionen") ist direkt im Kern eingebaut, nicht über
diesen Erweiterungspunkt angebunden (die Funktion braucht ohnehin Zugriff auf
mediaplace-interne Klassen wie `AltTextStatus`, eine externe Anbindung hätte
dort keinen Vorteil gebracht).

```js
MP.registerAdminMenuItem('my_addon_bulk', {
    label: 'Mein Bulk-Feature',
    icon: 'fa-solid fa-wand-magic-sparkles',
    onClick: function () {
        // eigenes Modal/Panel öffnen, eigene API-Calls, ...
    }
});
```

Ansonsten ist `window.MP` bewusst schlank und bietet keine weitere
Plugin-Registrierung, nur Aufruf-/Daten-Methoden:

- `MP.open(opts)` / `MP.close()` – Overlay öffnen/schließen.
- `MP.openFile(filename, callback, opts)` – Overlay öffnen und direkt die
  Detailansicht einer Datei zeigen.
- `MP.showFileDetail(filename)` – Detailansicht in einem bereits offenen
  Overlay wechseln.
- `MP.startMetainfoPick(wrapper, isList)` – öffnet das Grid im
  Auswahl-Modus für klassische `REX_MEDIA[n]`/`REX_MEDIALIST[n]`-Widgets
  *innerhalb* des Metainfo-Canvas (siehe `assets/mediaplace_classic.js`).

`window.MPWidget.init(scope)` initialisiert `input.mp-widget`-Elemente in
einem gegebenen DOM-Bereich neu (z. B. nach dynamisch nachgeladenem HTML).

`window.MPCore` (`i18n`/`helpers`/`api`, aufgebaut aus
`mediaplace-i18n.js`/`-helpers.js`/`-api.js`) ist zwar global erreichbar und
wird intern von `mediaplace.js`/`mediaplace_widget.js`/`mediaplace_classic.js`
gemeinsam genutzt, aber **keine stabile Drittanbieter-API** – keine
Versionsgarantie, kann sich zwischen Releases ändern.

**Nicht vorhanden, trotz Erwähnung an anderer Stelle im Code:**
`MP.registerModule()` taucht nur als Kommentar in
`src/mediaplace/modules/focuspoint.js` auf (Idee: Fokuspunkt-/
Optimieren-Integration als optional nachladbare, eigene Bundles statt fest
im Hauptbundle) – siehe "Architektur: Hub-and-Spoke" oben, bislang nicht
umgesetzt. Ebenso existiert aktuell **kein** Erweiterungspunkt, um den
eingebauten Upload-Dialog durch einen eines Drittanbieter-Addons (z. B.
filepond/uppy) zu ersetzen.

### 3. Wie MediaPlace selbst in REDAXO eingreift (native Extension Points)

Relevant, wenn man MediaPlace' eigenes Verhalten verstehen oder gezielt
zusätzlich beeinflussen will (`boot.php`, sofern nicht anders angegeben):

| Extension Point | Was MediaPlace dort tut |
|---|---|
| `MEDIA_IS_IN_USE` | Registriert `rex_yform_value_mediaplace::isMediaInUse()` (nur wenn `yform` verfügbar) – durchsucht alle YForm-Felder vom Typ `mediaplace` nach Referenzen auf die Datei; feuert intern selbst nochmal `YFORM_MEDIA_IS_IN_USE` (siehe oben) |
| `MEDIA_MANAGER_FILTERSET` | Liefert für MediaPlace's Video-Thumbnail-Typen ein leeres Effekt-Set, falls `ffmpeg` gerade nicht verfügbar ist (verhindert Fatal Error) |
| `PAGES_PREPARED` | Biegt den klassischen "Medienpool"-Menüpunkt auf `MP.open()` um (abschaltbar über die Einstellung "Klassischen Medienpool ersetzen") |
| `METAINFO_CUSTOM_FIELD` | Zeigt `med_json_data` read-only im klassischen Medien-Bearbeiten-Formular (nur wenn `metainfo` verfügbar) |
| `OUTPUT_FILTER` | Injiziert `#mp-root` (API-URLs, Feature-Flags, Rechte, i18n-JSON) vor `</body>` jeder Backend-Seite – der Bootstrap-Mechanismus des gesamten Frontends |
| `MEDIA_FORM_EDIT` / `MEDIA_UPDATED` | Nativer Metainfo-Canvas: `lib/Api/MetainfoForm.php` rendert/speichert echte `med_*`-Felder über REDAXOs eigenen Pfad (kein eigenes Feldtyp-System); `lib/FocuspointIntegration.php` feuert `MEDIA_UPDATED` manuell nach einem Direkt-SQL-Save, damit Listener denselben Effekt sehen wie bei einem normalen Update |

### 4. REDAXO-Berechtigungen

Alle über `rex_perm::register()` in `boot.php`, unconditional registriert
(Rollenverwaltung muss sie unabhängig vom eingeloggten User auflisten
können):

| Recht | Bedeutung |
|---|---|
| `mediaplace[view_unused_media]` | Filter "Nur unbenutzte Medien" nutzen |
| `mediaplace[manage_categories]` | Ordner (Kategorien) umbenennen/verschieben/löschen |
| `mediaplace[optimize_video]` | Videos optimieren (ffmpeg-Addon) |
| `mediaplace[optimize_image]` | Bilder optimieren |
| `mediaplace[manage_tags]` | Tags anlegen/umbenennen/Farbe ändern/löschen/für KI-Vorschläge freigeben |
| `mediaplace[bulk_operations]` | Massenaktionen für ganze Kategorien (alle Dateien verschieben/löschen/taggen) |

### 5. Eigene REST-Endpunkte (`lib/Api/*.php`)

Alle als `rex_api_function`-Subklassen unter dem Namespace
`FriendsOfRedaxo\Mediaplace\Api`, explizit in `boot.php` registriert (folgen
nicht der `rex_api_<name>`-Namenskonvention). Relevant beim Bauen eines
eigenen, neuen Endpunkts: **`protected $published = true;`** setzen, sonst
schlägt der Aufruf per `fetch()` fehl, sobald die aufrufende Seite nicht
unter `/redaxo/...` läuft (siehe `rex_api_function::$published`-Verhalten) –
keine Sicherheitslockerung, die eigentliche Rechteprüfung passiert weiterhin
in `execute()` selbst (siehe `lib/MediaPermission.php`-Muster, das jeder
dieser Endpunkte nutzt).

| `rex-api-call` | Klasse | Zweck |
|---|---|---|
| `mediaplace_ai_alt_bulk` | `AiAltBulk` | KI-ALT-Text-Massengenerierung (nur Vorschläge, siehe `apply`-Aktion fürs eigentliche Schreiben), gated auf `mediaplace[bulk_operations]` |
| `mediaplace_ai_alt_text` | `AiAltText` | KI-ALT-Text-Vorschlag für eine einzelne Datei, schreibt nicht selbst |
| `mediaplace_ai_auto_tag` | `AiAutoTag` | KI-Tag-Vorschläge für eine einzelne Datei (geschlossenes Vokabular, siehe `AiAutoTagService`), schreibt nicht selbst |
| `mediaplace_categories` | `Categories` | Kategoriebaum + CRUD (eigene Rechteprüfung statt `api`-Addon-Routen) |
| `mediaplace_category_bulk` | `CategoryBulk` | Massenaktionen für alle Dateien einer Kategorie (verschieben/löschen/taggen/Sammlung) |
| `mediaplace_image_edit` | `ImageEdit` | Bildbearbeitung (siehe `ImageEditor`): Info, Speichern als Kopie/ersetzend, Original wiederherstellen |
| `mediaplace_focuspoint` | `Focuspoint` | Fokuspunkt-Info/Speichern |
| `mediaplace_image_optimize` | `ImageOptimize` | "Bild optimieren"-Button |
| `mediaplace_json_metainfo` | `JsonMetainfo` | Speichert MediaPlace's eigene JSON-Metadaten |
| `mediaplace_media_list` | `MediaList` | Medienliste: Übergangs-Fallback bei zu alter `api`-Addon-Version, UND immer (unabhängig davon) für die MediaPlace-eigenen Filter Sammlung/„Medien ohne ALT-Text“/Tags (`filter[collection]`/`filter[alt_missing]`/`filter[tags]`), die die generische `api`-Route nicht kennt – siehe `apiFetchOwnMediaList()` in `mediaplace-api.js` |
| `mediaplace_metainfo_form` | `MetainfoForm` | Nativer Metainfo-Canvas (`med_*`-Felder) über `MEDIA_FORM_EDIT`/`MEDIA_UPDATED` |
| `mediaplace_provider` | `Provider` | Dispatcher für `StorageProviderInterface`-Provider (Browsen/Suche/Thumbnail/Einzel- und Massenimport `func=import_batch`, max. `IMPORT_BATCH_MAX=25` Pfade/Request, Chunking siehe `providers.js::runProviderBulkImport()`) |
| `mediaplace_schema` | `Schema` | Feld-Schema (Präfix `med_` per Default) als JSON |
| `mediaplace_tags` | `Tags` | System-Tags/Sammlungen-API |
| `mediaplace_unused` | `Unused` | "Nur unbenutzte Medien"-Prüfung, gated auf `mediaplace[view_unused_media]` |
| `mediaplace_video_info` | `VideoInfo` | Technische Videodaten (ffprobe) fürs Detail-Panel |
| `mediaplace_video_optimize` | `VideoOptimize` | "Video optimieren"-Button (Wrapper um `ffmpeg`-Addon-Jobs) |

## Bekannte Stolperfallen

- `assets/mediaplace.js` ist generiert – Änderungen dort gehen beim nächsten
  Build verloren. Immer in `src/mediaplace/*.js` arbeiten.
- `npm run watch` läuft im Vordergrund (esbuild-Kontext mit aktivem File-
  Watcher) – für einen einmaligen Build in CI/Deploy-Skripten `npm run build`
  verwenden, nicht `watch`.
- `node_modules/` wird nicht committet (siehe `.gitignore`) – nach einem
  frischen Checkout erst `npm install` laufen lassen.
- `src/` (die esbuild-Quelle) landet trotzdem NICHT im GitHub-Release-Zip:
  `.gitattributes` markiert den Ordner als `export-ignore`, `git archive`
  (was Release-Zips baut) lässt ihn dadurch weg. Der Auslieferungsstand
  (`assets/mediaplace.js`) bleibt davon unberührt – nur unminifizierter
  Quellcode fliegt raus, kein Funktionsverlust. `node_modules` braucht dort
  keinen eigenen Eintrag, da es ohnehin nie committet wird und `git archive`
  nur getrackte Dateien einpackt.
