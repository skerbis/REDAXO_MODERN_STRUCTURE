# MediaPlace
**Eine neue GUI für den Medienpool**

![Screenshot](https://github.com/FriendsOfREDAXO/mediaplace/blob/assets/mediaplace.jpg?raw=true)

![REDAXO](https://img.shields.io/badge/REDAXO-%3E%3D5.20-red) ![PHP](https://img.shields.io/badge/PHP-%3E%3D8.4-blue) ![API](https://img.shields.io/badge/API_AddOn-%3E%3D1.3-green)

Hey! Ihr wünscht Euch den Medienpool 3? Das können wir nicht bieten. Aber hier ist **MediaPlace** – ein moderner Medienpool-Ersatz fürs REDAXO-Backend, mit dem sich das Warten schon mal ganz gut aushalten lässt.

Vollbild-Overlay statt Popup-Gefrickel, dazu ein Eingabe-Widget für Module/Formulare und eine nahtlose Übernahme aller klassischen Zugangspunkte (Hauptmenü, `REX_MEDIA[n]`/`REX_MEDIALIST[n]`). Unter der Haube läuft die REST-API des [FriendsOfREDAXO/api](https://github.com/FriendsOfREDAXO/api)-Addons.

Es ist kein extra Uploader-AddOn mehr erforderlich. MediaPlace unterstützt chunked Uploads via API-AddOn. Einfach Dateien in die aktuell geöffnete Kategorie ziehen oder mit STRG+V / CMD+V einfügen. 


## Features

**Overlay & Medienverwaltung**
- Kategorie-Baum mit Suche, Verschieben, Anlegen/Umbenennen
- Serverseitige Suche über Titel, Dateiname, Originalname und Metadaten
- Typ- und Tag-Filter, Verwendungs-Filter („Nur benutzte“ / „Nur unbenutzte“), 8 Sortieroptionen
- Grid, Liste & Media Wall (Masonry), Kachelgröße per Slider
- Detail-Panel mit editierbarem Titel, eigenen Metadaten-Feldern, Verwendungsstatus, Datei tauschen/umbenennen/löschen/downloaden
- Fokuspunkt-Editor direkt im Detail-Panel, sobald das [focuspoint](https://github.com/FriendsOfREDAXO/focuspoint)-Addon installiert ist
- Eigene Bildbearbeitung im Detail-Panel: Zuschneiden, Drehen, Spiegeln, Ausrichten, Perspektive korrigieren, Helligkeit/Kontrast/Gamma/Sättigung – als neue Datei oder ersetzend mit wiederherstellbarem Original
- Upload per Drag & Drop, Button oder einfach **Cmd+V/Ctrl+V** pasten
- Responsive Compact-Mode fürs schmale Fenster, Dark Mode Toggle
- Sieht aus wie REDAXO, weil es sich an `be_style` orientiert

**ffmpeg-Integration** (sobald das [ffmpeg](https://github.com/FriendsOfREDAXO/ffmpeg)-Addon installiert ist)
- Echte Video-Vorschau im Grid statt Datei-Icon – wahlweise animiert oder als (deutlich günstigeres) Einzelbild, komplett abschaltbar
- „Video optimieren“-Button: ersetzt eine zu große Videodatei in-place durch eine kleinere, gleicher Dateiname
- Aufklappbare technische Details (Auflösung, Codec, Bitrate, Framerate, …)

**pdfout-Integration** (sobald das [pdfout](https://github.com/FriendsOfREDAXO/pdfout)-Addon installiert ist)
- Erste PDF-Seite als Vorschau im Grid und im Detail-Panel statt Datei-Icon
- PDF direkt im pdf.js-Viewer von pdfout ansehen, ohne MediaPlace zu verlassen

**Bilder optimieren**
- „Bild optimieren“-Button im Detail-Panel für Bestandsbilder, die größer als die konfigurierte Upload-Grenze sind
- Cronjob „Vorschaubilder vorwärmen“ erzeugt Grid-Thumbnails (Bild & Video) im Hintergrund vor, statt bei jedem ersten Betrachten live zu generieren

**Tags**
- Eigene Combobox mit Mehrfachauswahl + „neu anlegen“-Option im Detail-Panel
- Zentrale Tag-Verwaltung (eigene Backend-Seite): neue Tags direkt anlegen, umbenennen (kaskadiert automatisch auf alle Dateien), Farbe zentral ändern, löschen

**Sammlungen**
- Eigene Sammlungen anlegen, Medien per Lesezeichen-Button oder Drag & Drop zuordnen
- Auch als Batch: mehrere Medien mit Cmd/Ctrl+Klick markieren und gemeinsam ziehen

**Cloud-Speicher durchsuchen und importieren** (sobald ein Storage-Provider-Addon wie [nextcloud](https://github.com/FriendsOfREDAXO/nextcloud) installiert und berechtigt ist)
- Eigener Sidebar-Bereich pro angebundenem Speicher, Navigation per Ordner-Kacheln und eigener Breadcrumb, serverseitige Suche sofern der Provider sie unterstützt
- Einzeldatei-Import direkt aus der Mini-Detailansicht, im Picker-Modus mit sofortiger Übernahme
- Massenimport: Mehrfachauswahl per Checkbox oder „Alle im aktuellen Ordner“, einmalige Zielkategorie-Wahl, Fortschrittsanzeige mit Fehlerliste je Datei

**KI-Funktionen** (optional, sobald das separate [ai_platform](https://github.com/FriendsOfREDAXO/ai_platform)-Addon installiert/konfiguriert und in den Einstellungen aktiviert ist)
- **ALT-Text-Generierung**: „AI generieren“-Button neben dem ALT-Text-Feld (Einzeldatei) sowie „KI-Alt-Text-Generator“ im Zahnrad-Menü (mehrere Dateien mit fehlendem ALT-Text nacheinander abarbeiten). Schreibt nie automatisch – Vorschläge werden erst nach Prüfung durch einen expliziten Klick übernommen.
- **KI-Tag-Vorschläge**: schlägt beim Bearbeiten einer Datei passende Tags vor – ausschließlich aus Tags, die zuvor in der Tag-Verwaltung gezielt dafür freigegeben wurden (geschlossenes Vokabular, die KI legt nie eigenständig neue Tags an).

**Mehrfachauswahl**
- Im Picker-Modus: Dateien markieren, „Übernehmen“ liefert die Auswahl als Array zurück
- Im Normalmodus: Cmd/Ctrl+Klick markiert mehrere Medien für Batch-Löschen oder Sammlungs-Zuordnung

**Input-Widget**
- Vorschau, Hinzufügen/Entfernen, Drag & Drop-Sortierung bei Mehrfachauswahl
- Initialisiert sich automatisch, auch bei dynamisch nachgeladenen Feldern (MBlock & Co.)

## Anwenderhilfe

### Installieren

1. AddOn installieren und aktivieren.
2. Das [FriendsOfREDAXO/api](https://github.com/FriendsOfREDAXO/api)-Addon muss ebenfalls installiert sein – unter **API → Konfiguration** die `backend/media*`-Endpunkte (Liste, Get, Add, Delete, Update, Category) aktivieren.
3. Fertig. Der Hauptmenüpunkt „Medienpool“ öffnet ab sofort direkt den neuen Overlay.

Unter **MediaPlace** finden sich die Admin-Seiten **Einstellungen**, **Metainfo Felder**, **Demo** und **Hilfe**. Der Picker/Overlay selbst bleibt für alle Backend-User mit Medienrecht nutzbar – nur der Verwendungs-Filter („Nur benutzte“ / „Nur unbenutzte“) braucht zusätzlich ein eigenes Recht, das sich über Benutzer → Rollen vergeben lässt.

### Klassischen Medienpool zurückholen

Falls doch lieber der alte Medienpool gewünscht ist: unter **MediaPlace → Einstellungen** die Checkbox „Klassischen Medienpool-Menüpunkt ersetzen“ abschalten. Dann verhält sich alles wieder wie im REDAXO-Standard.

> **Hinweis:** Der alte Medienpool bleibt als Picker-Seite erreichbar – für AddOns, die MediaPlace noch nicht unterstützen (z. B. TinyMCE/CKEditor5), springt der klassische Auswahl-Popup weiterhin ein.

### Tagging und Sammlungen einzeln abschalten

Ebenfalls in den Einstellungen: „Tagging (System-Tags) deaktivieren“ und „Sammlungen deaktivieren“ blenden die jeweiligen Bereiche unabhängig voneinander aus. Bereits gespeicherte Daten gehen dabei nicht verloren, nur die Bedienoberfläche verschwindet.

### Tags verwalten

Unter **MediaPlace → Tag-Verwaltung** (Recht `mediaplace[manage_tags]`) lassen sich System-Tags zentral verwalten, unabhängig davon, ob sie schon einer Datei zugewiesen sind:

- **Neuen Tag anlegen** – Name, Farbe und optional direkt „Für KI-Vorschläge freigeben“ (siehe unten). So lässt sich eine Tag-Liste vorab kuratieren, statt sie erst durch Zuweisen an eine Datei entstehen zu lassen.
- **Umbenennen** – kaskadiert automatisch auf alle Dateien, die den Tag tragen.
- **Farbe ändern** – zentral für den Tag, gilt danach überall. Die Farbe eines *bestehenden* Tags ist nur noch hier änderbar; das Farb-Swatch im Tag-Widget einer Datei erscheint nur noch bei der Neuanlage.
- **Löschen** – entfernt den Tag von allen Dateien.

Sammlungen (`collection:`-Präfix) tauchen hier bewusst nicht auf – die haben ihre eigene Verwaltung („Sammlungen verwalten“ im Overlay).

### Dateien umbenennen

Mit dem Recht `mediaplace[rename_files]` (Admins immer) erscheint im Detail-Panel der Knopf **Umbenennen**. Der Dialog zeigt beim Tippen den endgültigen Namen – normalisiert wie im Medienpool, die Endung bleibt – und meldet, wenn der Name schon vergeben ist.

- **Sofort:** Die Datei heißt anders, MediaPlace zeigt den neuen Namen. Die alte Datei bleibt als Übergangskopie liegen, damit Seiten mit dem alten Namen bis zum Ende weiter funktionieren.
- **Im Hintergrund:** Alle Verweise in den REDAXO-Tabellen werden ersetzt – Artikel, Module, Metadaten, YForm-Tabellen, `rex_config` usw. Ersetzt wird nur der ganze Dateiname (`bild.jpg` trifft nicht `alt-bild.jpg`), auch in JSON und PHP-serialisierten Daten. Ausgenommen sind temporäre Tabellen, Tabellen mit Endung `_log` und der Originalname der Datei.
- **Ohne offenen Browser:** Der Dialog darf geschlossen werden. Weiter geht es direkt nach dem Start, auf jeder Backend-Seite, per Cronjob „MediaPlace: Umbenennungen fortsetzen“ oder per Konsole `php redaxo/bin/console mediaplace:rename-work`. Für große Datenbanken den Cronjob einrichten.
- **Zum Schluss:** Übergangskopie löschen, Media-Manager-Cache und die Cache-Dateien der betroffenen Artikel leeren.

Links von außen (andere Websites, Suchmaschinen, verschickte PDFs) auf den alten Namen funktionieren danach nicht mehr.

Für Entwickler: Extension Point `MEDIAPLACE_RENAME_TABLES` (Tabellenliste anpassen, je Eintrag `t`, `pk`, `cols`) und `MEDIAPLACE_MEDIA_RENAMED` (nach Abschluss, Parameter `old_filename`, `new_filename`, `replaced`).

### Bilder beim Upload verkleinern

Unter **MediaPlace → Einstellungen → Upload** lässt sich „Bilder beim Upload verkleinern“ aktivieren (standardmäßig aus), mit maximaler Breite/Höhe in Pixeln. Zu große Bilder werden im Browser per Canvas herunterskaliert, bevor sie hochgeladen werden – Seitenverhältnis bleibt erhalten, kleinere Bilder werden nie vergrößert, und das Dateiformat bleibt unverändert. GIFs (könnten animiert sein) und SVGs (kein Rasterbild) werden dabei nie angefasst.

Ist der Schalter aktiv, taucht im Detail-Panel bei bereits gespeicherten Bildern, die größer als diese Grenze sind (z. B. von vor der Aktivierung), ein **„Bild optimieren“-Button** auf – verkleinert die Datei nachträglich in-place (gleicher Dateiname, per GD, unterstützt JPG/PNG/WebP, nicht GIF/SVG). Der Button verschwindet von selbst, sobald das Bild innerhalb der Grenze liegt. Braucht das Rollenrecht `mediaplace[optimize_image]` (Benutzer → Rollen).

### Videos: Vorschau, Optimieren, technische Details

Ist das separate [ffmpeg](https://github.com/FriendsOfREDAXO/ffmpeg)-Addon installiert, zeigt das Grid für Videos eine echte Vorschau statt des Datei-Icons. Der Modus ist unter **MediaPlace → Einstellungen** einstellbar:

- **Aus** – nur das Datei-Icon, keine Vorschau-Generierung
- **Einzelbild** – ein einzelnes Standbild, deutlich günstiger als eine Animation (kleines Video-Symbol auf der Kachel macht trotzdem klar, dass es sich um ein Video handelt)
- **Animiert** – kurze, bewegte Vorschau (Standard, bisheriges Verhalten)

Im Detail-Panel eines Videos gibt es außerdem, sofern ffmpeg lauffähig ist:

- **„Video optimieren“** (Rollenrecht `mediaplace[optimize_video]`): ersetzt die Videodatei in-place durch eine kleinere, gleicher Dateiname. Läuft im Hintergrund weiter, eine Statuszeile zeigt den Fortschritt; bereits optimierte Dateien zeigen die erreichte Kompressionsrate.
- **„Technische Details“**: aufklappbare Auflösung/Dauer/Codec/Bitrate/Framerate-Angaben, erst beim Aufklappen nachgeladen.

### PDFs: Vorschau und Viewer

Ist das [pdfout](https://github.com/FriendsOfREDAXO/pdfout)-Addon installiert, zeigen Grid und Detail-Panel die erste Seite eines PDFs. MediaPlace legt dafür den Media-Manager-Typ `mediaplace_pdf_thumb` selbst an (pdfout-Effekt „PDF-Thumbnail“ + Resize auf 600 px), es muss nichts eingerichtet werden. Gerendert wird mit dem Konverter, den pdfout findet: poppler-utils (empfohlen), Ghostscript oder Imagick. Findet pdfout keinen, bleibt es beim Datei-Icon; ein PDF, das sich nicht rendern lässt, fällt einzeln aufs Icon zurück.

Im Detail-Panel öffnet ein Klick auf die Vorschau oder den Buch-Button das PDF im pdf.js-Viewer von pdfout (Toolbar wie unter **pdfout → Einstellungen → Toolbar** konfiguriert). Der Viewer braucht keinen Konverter.

### Bildbearbeitung

Bei JPEG-, PNG-, WebP- (und, wenn der Server es kann, AVIF-) Bildern öffnet der Regler-Button in der Vorschau des Detail-Panels die Bildbearbeitung im Hauptbereich:

- **Zuschneiden** frei oder mit festem Seitenverhältnis (Original, 1:1, 4:3, 3:2, 16:9 und Hochformate), per Maus, Touch oder Tastatur
- **Drehen** in 90°-Schritten, **Spiegeln** horizontal und vertikal
- **Ausrichten** um bis zu ±45°; leere Ecken werden automatisch weggeschnitten
- **Perspektive korrigieren**: vier Punkte auf die Ecken einer Fläche ziehen (Fassade, Dokument, Schild), die rechteckig werden soll – mit mitlaufender Vorschau und großer Ergebnisansicht
- **Weißabgleich** per Pipette (neutrale Stelle anklicken) oder Farbtemperatur/Tönung
- **Tonwerte**: automatische Korrektur, Schwarz- und Weißpunkt, Helligkeit, Kontrast, Lichter, Tiefen, Gamma (Mitteltöne)
- **Farbe und Effekte**: Sättigung, Schwarzweiß, Sepia, Warm, Kühl, Ausgeblichen (jeweils mit Stärke), Vignette
- **Vorher/Nachher**-Vergleich mit dem Original

Die Vorschau rechnet live auf einer verkleinerten Kopie, gespeichert wird am Original mit denselben Schritten. Gespeichert wird standardmäßig **ersetzend** – dann bleiben Dateiname und Verwendungen erhalten, das Original wird aufbewahrt und lässt sich über „Original wiederherstellen“ zurückholen. Alternativ **als neue Datei** (Titel und alle Metadaten werden übernommen). Ein gesetzter Fokuspunkt wird mitgerechnet bzw. geleert, wenn er nach dem Zuschnitt außerhalb liegt.

Rechte: `mediaplace[edit_image]` (bearbeiten, als neue Datei speichern), `mediaplace[edit_image_overwrite]` (ersetzen, Original wiederherstellen). Mit der PHP-Erweiterung Imagick stehen alle Werkzeuge zur Verfügung; nur mit GD fehlen Perspektive, Weißabgleich, Tonwerte, Farbe und Effekte.

### Cloud-Speicher durchsuchen und importieren

Ist ein Storage-Provider-Addon installiert (z. B. [nextcloud](https://github.com/FriendsOfREDAXO/nextcloud)) und hat der Nutzer dessen eigenes Recht, taucht in der Sidebar ein zusätzlicher Bereich mit einem Eintrag je angebundenem Speicher auf. Ein Klick öffnet die entfernte Ordnerstruktur direkt im gewohnten Grid/Liste-Layout, inklusive eigener Breadcrumb und – sofern der Provider das unterstützt – Suche.

- **Einzelne Datei importieren**: Klick auf eine Datei öffnet eine schlanke Mini-Detailansicht mit einem „In Medienpool importieren“-Button. Zielkategorie wird einmalig abgefragt.
- **Mehrere Dateien auf einmal importieren**: Der Auswahl-Modus-Button in der Werkzeugleiste (gleicher Button wie für die lokale Mehrfachauswahl) blendet Checkboxen auf den Dateien ein. Eine Fußleiste bietet „Alle auswählen“ (nur Dateien im gerade angezeigten Ordner, nicht rekursiv), die Anzahl der Auswahl sowie „Auswahl importieren“. Nach der Zielkategorie-Wahl läuft der Import in kleinen Chunks mit Fortschrittsbalken; einzelne fehlgeschlagene Dateien brechen die restlichen nicht ab, sondern erscheinen als eigene Fehlerzeile.
- Der Import kopiert die Datei in den lokalen Medienpool – die entfernte Originaldatei bleibt beim Provider unverändert bestehen (kein Verschieben/Löschen).

### Vorschaubilder im Hintergrund vorwärmen

Ist das `cronjob`-Addon installiert, steht unter **Cronjobs** der Typ „MediaPlace: Vorschaubilder vorwärmen“ zur Verfügung: erzeugt Grid-Thumbnails (Bilder und, falls ffmpeg bzw. pdfout installiert ist, auch Videos und PDFs) im Hintergrund vor, statt sie erst beim ersten Betrachten live zu generieren – schont den Server bei großen, noch nicht durchgewärmten Kategorien. Pro Lauf wird je Typ nur eine begrenzte, einstellbare Anzahl neuer Vorschaubilder erzeugt (bereits gecachte werden übersprungen), neueste Dateien zuerst.

### Klassisches ALT-Text-Feld: Bilder als dekorativ auszeichnen

Nutzt du das klassische Metainfo-Feld `med_alt` statt eines eigenen JSON-Alt-Feldes, kannte MediaPlace bisher keine Möglichkeit, ein rein dekoratives Bild (bei dem laut Barrierefreiheits-Richtlinien bewusst `alt=""` korrekt ist) von einem tatsächlich fehlenden ALT-Text zu unterscheiden – ein leeres Feld zählte immer als "fehlt".

Unter **MediaPlace → Einstellungen** findet sich dafür eine eigene Box „Klassisches ALT-Text-Feld“: fehlt `med_alt` und/oder das zusätzliche Dekorativ-Feld, lässt es sich dort per Klick anlegen (nutzt REDAXOs eigene Metainfo-API, keine manuelle Feldanlage nötig). Ist das Dekorativ-Feld bei einem Bild aktiviert, zählt es in der "Medien ohne ALT-Text"-Sidebar-Ansicht, im "ALT-Text fehlt"-Hinweis im Detail-Panel sowie überall sonst in MediaPlace nicht mehr als fehlend, unabhängig vom Inhalt von `med_alt`. Wird ein eigenes JSON-Alt-Feld genutzt (Widget-Typ „alt“), ist das nicht relevant – das hat sein eigenes „Dekorativ“-Flag bereits eingebaut.

### KI-Funktionen: ALT-Text & Tag-Vorschläge

Optional, sobald das separate [ai_platform](https://github.com/FriendsOfREDAXO/ai_platform)-Addon installiert und konfiguriert ist (mindestens ein Profil vom Typ „Bildverständnis“) – ohne `ai_platform` bleiben beide Funktionen unter **MediaPlace → Einstellungen → „KI-Funktionen“** einfach ausgeblendet.

**ALT-Text-Generierung** (Schalter „KI-Alt-Text aktivieren“):
- „AI generieren“-Button neben dem ALT-Text-Feld im Detail-Panel (eigenes JSON-Feld oder klassisches `med_alt`) – schreibt nur ins sichtbare Feld, gespeichert wird weiterhin über den normalen Speichern-Button.
- „KI-Alt-Text-Generator“ im Zahnrad-Menü: erzeugt zunächst nur Vorschläge für alle Dateien ohne ALT-Text (Thumbnail + editierbares Textfeld je Datei, größere Vorschau per Klick, einzelne Einträge verwerfbar), geschrieben wird erst nach „Übernehmen“. Ein Lauf ist auf 25 Dateien begrenzt (jede ist ein echter KI-Aufruf), „Weitere generieren“ holt bei Bedarf nach.
- Prompt-Profil (Barrierefreiheit/Neutral/SEO) und eigener Prompt konfigurierbar. SVG-Dateien werden beim Einzeldatei-Button clientseitig auf Canvas gerendert (der Browser kann SVG selbst rendern, ein Server-Rasterizer wäre unzuverlässig) – in der Massengenerierung werden sie stattdessen übersprungen und separat gezählt. Zu große Bilder werden vor dem Senden automatisch verkleinert (Einstellung „Maximale Bildkantenlänge für die KI-Analyse“).

**KI-Tag-Vorschläge** (Schalter „KI-Tag-Vorschläge aktivieren“):
- „KI-Tags vorschlagen“-Button im Tag-Widget des Detail-Panels, Vorschläge erscheinen als anklickbare Chips – ein Klick fügt den Tag hinzu, gespeichert wird wie gewohnt über den Speichern-Button.
- **Geschlossenes Vokabular**: die KI schlägt ausschließlich Tags vor, die in der Tag-Verwaltung explizit über die Spalte „KI-Vorschläge“ freigegeben wurden (Default: kein Tag freigegeben) – sie legt nie eigenständig neue Tags an. Obergrenze pro Datei konfigurierbar (Standard 3).

Beide Funktionen teilen sich das konfigurierte Bildverständnis-Profil und die Bildgrößen-Einstellung. Zugriff ist rechtegeprüft: der Einzeldatei-Button braucht normalen Medienzugriff, „KI-Alt-Text-Generator“ zusätzlich das granularere Recht `mediaplace[bulk_operations]`.

### Als Eingabefeld in Modulen/Formularen nutzen

```html
<!-- Einzelbild -->
<input class="mp-widget" name="REX_INPUT_VALUE[1]" value="REX_VALUE[1]">

<!-- Galerie (mehrere Dateien, kommasepariert) -->
<input class="mp-widget" data-mp-multiple="true" name="REX_INPUT_VALUE[2]" value="REX_VALUE[2]">

<!-- Mit Direkt-Upload: Dateien per Drag&Drop oder Klick direkt hochladen
     (Kategorie-Auswahl-Dialog vor dem Upload), zusätzlich zum normalen
     Picker. Nur Bilder erlauben mit data-mp-types (Syntax wie natives
     <input accept>). -->
<input class="mp-widget" data-mp-multiple="true" data-mp-upload="true" data-mp-types="image/*" name="REX_INPUT_VALUE[3]" value="REX_VALUE[3]">
```

Klick auf ➕ öffnet den Picker. Werden Felder dynamisch nachgeladen (z. B. MBlock), einfach `MPWidget.init()` erneut aufrufen. Bei Mehrfachauswahl lässt sich die Galerie per Umschalter im Toolbar zwischen Kacheln- und Listenansicht wechseln (Einstellung gilt geteilt für alle Widgets auf der Seite).

### Den Picker direkt per JavaScript öffnen

```javascript
MP.open(function (filename) {
    console.log('Gewählt:', filename);
});

// Mehrfachauswahl
MP.open(function (filenames) {
    console.log('Gewählt:', filenames);
}, { multiple: true });
```

## Für Entwickler

### Eigene Picker-Integration (TinyMCE, CKEditor5, eigene Widgets)

Automatisch abgefangen werden nur klickbare klassische Widgets – `REX_MEDIA[n]`/`REX_MEDIALIST[n]`, mform `+media+`/`+medialist+`, der Hauptmenüpunkt (`mediaplace_classic.js`, Event-Delegation) – die öffnen den MediaPlace-Overlay statt des alten Popups.

TinyMCE und CKEditor5 rufen `openREXMedia()`/`openMediaPool()` dagegen direkt per JavaScript auf, ohne abfangbaren Klick. Das bleibt bewusst unverändert, damit ihr Popup-Fenster-Vertrag (`jQuery(window).on('rex:selectMedia', ...)`) weiter funktioniert – dort springt also weiterhin der klassische Medienpool ein (siehe Hinweis oben).

Baut ihr einen eigenen Picker oder Feldtyp und wollt aktiv MediaPlace nutzen, statt die alte Popup-Kompatibilität nachzubauen, ruft einfach direkt die JS-API auf:

```javascript
MP.open(function (filename) {
    // Datei ausgewählt, z.B. ins eigene Eingabefeld schreiben
});

// bestehende Auswahl im Detail-Panel ansehen, ohne sie zu ändern
MP.openFile('bild.jpg');
```

### Eigene API-Endpunkte

Alle `mediaplace_*`-Endpunkte laufen über REDAXOs `rex-api-call`-Mechanismus und sind **Backend-Session-Endpunkte, keine öffentliche/token-basierte API** – sie prüfen `rex::isBackend()` + `rex::getUser()` und funktionieren deshalb nur mit einer eingeloggten Backend-Session (gleicher Origin, z.B. `fetch(..., { credentials: 'same-origin' })` aus eigenem Backend-JS heraus). Für externen/headless-Zugriff auf REDAXO-Standarddaten (Titel, echte Metainfo-Felder) ist stattdessen das [FriendsOfREDAXO/api](https://github.com/FriendsOfREDAXO/api)-Addon mit Bearer-Token gedacht.

| Methode | Endpunkt | Zweck |
|---|---|---|
| GET | `?rex-api-call=mediaplace_json_metainfo&filename={f}` | Metadaten + Felder + Tags eines Mediums laden |
| PATCH | `?rex-api-call=mediaplace_json_metainfo&filename={f}` | Metadaten + Tags speichern |
| GET/PATCH | `?rex-api-call=mediaplace_tags` | Tag-Katalog laden, Sammlungen verwalten |
| GET/PATCH | `?rex-api-call=mediaplace_categories` | Kategorien laden, verschieben |
| GET | `?rex-api-call=mediaplace_unused&filenames=a,b,c` | Prüft, welche Dateien unbenutzt sind |
| GET/POST | `?rex-api-call=mediaplace_focuspoint` | Fokuspunkt lesen/speichern (nur mit `focuspoint`-Addon) |
| GET/POST | `?rex-api-call=mediaplace_image_edit&action=info\|save\|restore&file={f}` | Bildbearbeitung (POST mit CSRF-Token) |
| GET | `?rex-api-call=mediaplace_video_optimize&func=start\|status` | Video optimieren starten/pollen (nur mit `ffmpeg`-Addon) |
| GET | `?rex-api-call=mediaplace_video_info&file={f}` | Technische Videodaten lazy nachladen (nur mit `ffmpeg`-Addon) |
| GET | `?rex-api-call=mediaplace_image_optimize&func=optimize&file={f}` | Übergroßes Bild in-place verkleinern |
| GET | `?rex-api-call=mediaplace_metainfo_form&filename={f}` | Formular für echte Metainfo-Felder laden/speichern (Einstellung „Metadaten bearbeiten“) |

**Beispiel: Metadaten eines Mediums auslesen** (aus eigenem Backend-JS, gleiche Session):

```javascript
fetch('index.php?rex-api-call=mediaplace_json_metainfo&filename=beispiel.jpg', {
    credentials: 'same-origin',
})
    .then(r => r.json())
    .then(json => console.log(json.data)); // { copyright: "...", alt_text: {...}, ... } -- Schlüssel je nach MediaPlace → Metainfo Felder
```

Antwortform (`handleGet()` in `lib/rex_api_mediaplace_json_metainfo.php`): `{ success, data, fields, clangs, system_tags, system_tag_catalog, title }` – `data` enthält die aktuellen Werte (Schlüssel = Feld-`key` aus **MediaPlace → Metainfo Felder**), `fields` die zugehörigen Felddefinitionen (Label/Widget-Typ/Optionen).

Läuft der Code bereits serverseitig in REDAXO (eigenes AddOn, kein HTTP-Umweg nötig), direkt die PHP-Klasse dahinter nutzen:

```php
$media = rex_media::get('beispiel.jpg');
$data = \FriendsOfRedaxo\Mediaplace\MetainfoJsonStorage::loadFromMedia($media);
echo $data['copyright'] ?? '';
```

Wichtig: `med_json_data` ist ein MediaPlace-eigenes Feld und taucht **nicht** in den generischen Medien-Antworten des `api`-Addons auf (das kennt nur echte Metainfo-Spalten). Für Letztere siehe `GET /api/backend/media/{filename}/metainfo` des `api`-Addons.

### Medien nach Tags oder Sammlungen auslesen

System-Tags und Sammlungen liegen in zwei eigenen Tabellen, nicht im `api`-Addon. Eine Sammlung ist dabei technisch nur ein Tag mit `collection:`-Präfix im Namen (`collection:Sommerkampagne`), angezeigt/verwaltet wird das über dasselbe Tag-Feld im Detail-Panel.

Einen fertigen "gib mir alle Dateien mit Tag X"-API-Endpunkt gibt es nicht – `?rex-api-call=mediaplace_tags` liest Tags nur pro (bekannter) Datei, keine Rückwärtssuche. Serverseitig (eigenes AddOn, Modul, Cronjob) übernimmt `\FriendsOfRedaxo\Mediaplace\SystemTagManager` das:

```php
use FriendsOfRedaxo\Mediaplace\SystemTagManager;

// Alle Dateien mit einem bestimmten Tag
$filenames = SystemTagManager::getFilenamesForTag('Sommerkampagne');

// Alle Dateien einer Sammlung (Praefix wird automatisch ergaenzt)
$filenames = SystemTagManager::getFilenamesForCollection('Projekt X');

foreach ($filenames as $filename) {
    $media = rex_media::get($filename);
    // ...
}
```

Weitere Methoden dort: `getCatalog()` liefert den kompletten Tag-/Sammlungs-Katalog (Name + Farbe, ungetrennt), `getTags()`/`getCollections()` filtern ihn in echte Tags bzw. Sammlungen (bei Sammlungen bereits ohne `collection:`-Präfix), `isCollectionTagName(string $name)` prüft einen einzelnen Namen. Über die API entspricht das GET `?rex-api-call=mediaplace_tags` ohne `filename`/`filenames` (Antwort-Feld `catalog`, ungefiltert wie `getCatalog()`).

### Eigene Feldtypen registrieren

Das Detail-Panel ist über den Extension Point `MEDIAPLACE_WIDGET_TYPES` erweiterbar – andere Addons können so eigene Feldtypen anmelden, ohne dass MediaPlace sie kennen muss:

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

`MyWidget` implementiert nur `normalizeValue(mixed $value): mixed` fürs Speichern; das Fragment bekommt `$field`, `$value`, `$info` und `$clangs` und liefert nur den Feldkörper (die Hülle kommt vom Dispatcher). Passt der Wert nicht ins generische Einzelfeld-Schema, lässt sich das Auslesen beim Speichern per `MP.registerFieldCollector(widgetType, function (key, panelEl) { return value; })` anpassen.

### Eigenes Backend-Theme mit MediaPlace kombinieren

Das Overlay ist komplett über CSS-Custom-Properties (`--mp-*`) eingefärbt, keine Farbe steht hart im Markup oder JS. Ob und wie es auf Dark Mode reagiert, entscheiden vier Blöcke in `assets/mediaplace.css`:

```css
:root { --mp-modal-bg: #fff; /* ... */ }                 /* Light-Default */

body.rex-theme-dark { --mp-modal-bg: #1a202c; /* ... */ } /* manuell gewähltes Dark-Theme */

@media (prefers-color-scheme: dark) {
    body.rex-has-theme:not(.rex-theme-light) { --mp-modal-bg: #1a202c; /* ... */ }
}                                                            /* System-Präferenz, sofern nicht explizit "Hell" gewählt */

#mp-overlay.mp-dark-mode { --mp-modal-bg: #1a202c; /* ... */ } /* eigener Dark-Mode-Toggle im Overlay, unabhängig vom Backend-Theme */
```

`rex-theme-dark` / `rex-theme-light` / `rex-has-theme` sind **REDAXOs eigene Backend-Konvention** (gesetzt in `core/layout/top.php`, ausgewertet z. B. vom mitgelieferten `be_style`-Addon) – kein MediaPlace-Spezifikum. Setzt euer eigenes Backend-Theme-Addon beim manuellen Umschalten auf Dark ebenfalls die Klasse `rex-theme-dark` auf `<body>` (statt eines eigenen Mechanismus), greift MediaPlaces Dark Mode automatisch mit, ganz ohne Anpassung hier.

Soll MediaPlace stattdessen eine andere Dark-Palette als die eingebaute bekommen, oder nutzt euer Theme eine abweichende Erkennung (eigene Body-Klasse, eigener Selector), reicht es, die betroffenen `--mp-*`-Variablen im eigenen, **nach** `mediaplace/assets/mediaplace.css` geladenen Stylesheet neu zu definieren:

```css
/* eigenes Backend-Theme-AddOn, eigene CSS-Datei, nach MediaPlace geladen */
body.mein-theme-dark {
    --mp-modal-bg: #101010;
    --mp-header-bg: #181818;
    /* weitere --mp-*-Variablen nach Bedarf, siehe :root-Block in mediaplace.css für die vollständige Liste */
}
```

Die vollständige Variablenliste steht am Anfang von `assets/mediaplace.css` im `:root`-Block (Light-Defaults) – alle vier Blöcke definieren dieselben Namen, nur mit anderen Werten.

Wichtig: Das gilt nur für den MediaPlace-Overlay selbst (`#mp-overlay`). Die klassischen REDAXO-Metainfo-Feldtypen im normalen Medienpool-Bearbeiten-Formular (Copyright, Auswahllisten, Medienlisten-Widget usw.) rendern über REDAXOs eigene Core-Fragmente und erben das Backend-Theme direkt – die sollten nicht von hier aus überschrieben werden, sondern folgen automatisch demselben `rex-theme-dark`.

## Credits

- [FriendsOfREDAXO](https://github.com/FriendsOfREDAXO)
- Inspiriert von gängigen System-Filemanagern. 
- Nutzt die [FriendsOfREDAXO/api](https://github.com/FriendsOfREDAXO/api)-REST-Schnittstelle
- Lizenz: MIT
