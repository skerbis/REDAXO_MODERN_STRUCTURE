# Changelog

## Version 2.7.1 – 2026-10-29

### Behoben
- **PDF-Vorschau in der Kachel**: Die erste Seite wird ganz gezeigt statt als Querformat-Ausschnitt, auch in Liste und Media-Wall.
- **Kachelhöhe**: Fällt eine Vorschau aufs Datei-Icon zurück, bleibt die Kachel so hoch wie die anderen. Datei-Icons ohne Vorschau haben im Grid jetzt dasselbe Seitenverhältnis wie Bilder.

## Version 2.7.0 – 2026-10-29

### Neu
- **PDF-Vorschau und PDF-Viewer** (mit dem [pdfout](https://github.com/FriendsOfREDAXO/pdfout)-AddOn): Grid und Detail-Panel zeigen die erste Seite eines PDFs statt des Datei-Icons. Den Media-Manager-Typ `mediaplace_pdf_thumb` legt MediaPlace selbst an. Gerendert wird mit poppler-utils, Ghostscript oder Imagick, je nachdem, was pdfout findet; ohne Konverter bleibt es beim Icon. Ein Klick auf die Vorschau öffnet das PDF im pdf.js-Viewer von pdfout direkt im Overlay.
- **Vorschaubilder vorwärmen** erfasst jetzt auch PDFs: im Cronjob (eigenes Kontingent pro Lauf), auf der Seite „Vorschaubilder vorwärmen“ und im Hintergrundlauf.

### Behoben
- **Vorwärmen im Hintergrund erzeugte nichts**: Der Hintergrundlauf rief relative Adressen auf („../media/…“), jeder Abruf scheiterte sofort, und weil auch Fehlschläge als erledigt zählten, war der Fortschrittsbalken nach einem Klick voll. Er ruft jetzt die vollständige Adresse aus der `server`-Einstellung auf. Fehlschläge zählen nicht mehr als Fortschritt: Die Seite meldet, wie viele Vorschaubilder nicht erzeugt werden konnten, und empfiehlt dann den Browser-Modus. Das passiert z. B., wenn der Server seine eigene Adresse nicht erreicht.

## Version 2.6.0 – 2026-10-28

### Neu
- **Dateien umbenennen** (Recht `mediaplace[rename_files]`, Admins immer): Knopf im Detail-Panel, Dialog mit Live-Vorschau des endgültigen Namens. Die Datei heißt sofort anders; alle Verweise in den REDAXO-Tabellen ersetzt ein Job im Hintergrund – nur ganze Dateinamen, auch in JSON und PHP-serialisierten Daten. Bis zum Ende bleibt eine Übergangskopie unter dem alten Namen liegen. Der Job läuft weiter, wenn der Browser geschlossen wird (direkt nach dem Start, auf jeder Backend-Seite, per Cronjob „MediaPlace: Umbenennungen fortsetzen“ oder `mediaplace:rename-work`). Extension Points `MEDIAPLACE_RENAME_TABLES` und `MEDIAPLACE_MEDIA_RENAMED`.

### Behoben
- **Abgeschaltetes Tagging und abgeschaltete Sammlungen tauchten noch auf** ([#17](https://github.com/FriendsOfREDAXO/mediaplace/issues/17)): Das Drei-Punkte-Menü einer Kategorie bot weiter „Alle Dateien taggen“ und „Alle Dateien zu Sammlung hinzufügen“ an, das Menü im Overlay und die Backend-Navigation die „Tag-Verwaltung“. Diese Einträge erscheinen jetzt nur noch, wenn die Funktion eingeschaltet ist. Ein direkter Aufruf der Tag-Verwaltung zeigt bei abgeschaltetem Tagging einen Hinweis mit Link zu den Einstellungen.

## Version 2.5.3 – 2026-10-17

### Behoben
- **Löschen hinter Web Application Firewalls**: Firewalls wie Imperva/Incapsula blocken Adressen mit `/delete`. Dateien ließen sich dahinter nicht löschen, auch nicht als Admin. MediaPlace löscht jetzt über die REST-Adresse `DELETE /api/backend/media/{datei}` (API-AddOn ab 1.3.1, FriendsOfREDAXO/api#84) und weicht bei älteren API-Versionen automatisch auf die bisherige Adresse aus.
- **Ehrliche Fehlermeldung bei 403**: „Keine Berechtigung … nur in zugewiesenen Kategorien“ erscheint nur noch, wenn REDAXO die Anfrage wirklich abgelehnt hat. Lehnt ein Server oder eine Firewall davor ab (Antwort ohne JSON), sagt MediaPlace das jetzt auch so.

## Version 2.5.2 – 2026-10-12

### Behoben (KI-Alt-Text-Generator)
- **Massengenerierung bricht nicht mehr ab**: Eine nicht lesbare oder zu große Datei (z. B. TIFF) ließ bisher eine ganze Anfrage mit zehn Bildern scheitern – die schon erzeugten Vorschläge waren weg, es gab keinen „Übernehmen“-Knopf, und beim Fortsetzen kam wieder dieselbe Datei. Jetzt verarbeitet der Dialog eine feste Warteschlange in kleinen Paketen; scheitert ein Paket, wird jede Datei einzeln versucht und nur die fehlerhafte übersprungen.
- **Fehlerhafte Dateien werden markiert** (`AiAltFailures`, rex_config) und bei den nächsten Läufen übersprungen – auch im Cronjob und auf der Konsole, die sonst an immer denselben Dateien hängen blieben. „Erneut versuchen“ im Dialog; eine ersetzte Datei gilt wieder als neu.
- **Nicht-Webformate** (TIFF, BMP, HEIC, AVIF, PSD …) werden vor dem Senden in ein verkleinertes JPEG umgewandelt (Imagick, sonst GD; erste Seite/Ebene, CMYK → sRGB). Formate, die der Server nicht umwandeln kann, werden gezählt und übersprungen. Große Originale gehen nie mehr unverkleinert an die KI.
- Zeitbudget je Anfrage (40 s): Was nicht fertig wird, kommt mit der nächsten Anfrage – keine Abbrüche am PHP-Zeitlimit.
- „Schließen“ heißt während des Laufs **„Anhalten“** und behält die erzeugten Vorschläge zum Übernehmen.
- Übersprungene Dateien erscheinen als **eine aufklappbare Zeile** („3 Datei(en) übersprungen – Details“) statt einer Box je Datei.

## Version 2.5.1 – 2026-10-09

### Geändert
- Bildbearbeitung speichert standardmäßig ersetzend („Datei ersetzen“): Dateiname und Verwendungen bleiben, das Original wird aufbewahrt und lässt sich wiederherstellen. User ohne das Recht `mediaplace[edit_image_overwrite]` speichern weiterhin als neue Datei.
- Bildbearbeitung: „Zuschneiden und Drehen“ ist beim ersten Öffnen aufgeklappt, „Automatisch korrigieren“ steht als hervorgehobener Knopf oben in diesem Bereich statt in der Kopfleiste. Danach gilt wie bisher der gemerkte Zustand der Bereiche.

## Version 2.5.0 – 2026-10-09

### Neue Features (Bildbearbeitung)
- **Weißabgleich**: Pipette für eine neutrale Stelle, dazu Regler für Farbtemperatur und Tönung.
- **Automatisch korrigieren**: setzt Schwarz- und Weißpunkt aus dem Histogramm und gleicht einen Farbstich gedämpft aus; die Werte stehen danach in den Reglern und lassen sich anpassen.
- **Schwarzpunkt, Weißpunkt, Lichter, Tiefen** als eigene Regler.
- **Effekte** mit einstellbarer Stärke: Schwarzweiß, Sepia, Warm, Kühl, Ausgeblichen; dazu eine **Vignette**.
- **Vorher/Nachher**: „Vorher“ zeigt das unbearbeitete Original zum Vergleich.
- „Automatisch korrigieren“ und „Vorher“ liegen in der Kopfleiste; die Bereiche der Seitenleiste sind aufklappbar und merken sich pro Browser, welche offen waren. Regler für Farbtemperatur, Tönung, Sättigung, Helligkeit, Schwarz-/Weißpunkt, Lichter, Tiefen, Kontrast und Vignette zeigen ihren Wertebereich als Farbverlauf.

### Intern
- Alle kanalweisen Korrekturen werden als eine gemeinsame Tonwertkurve berechnet (Browser: Lookup-Tabelle, Server: `Imagick::clutImage()`), Sättigung und Effekt als eine Farbmatrix – Vorschau und gespeicherte Datei stimmen damit überein.

## Version 2.4.0 – 2026-10-09

### Neue Features
- **Eigene Bildbearbeitung statt cropper-Integration**: Der Regler-Button in der Bildvorschau öffnet die Bearbeitung im Hauptbereich – Zuschneiden (frei oder mit Seitenverhältnis), Drehen, Spiegeln, Ausrichten bis ±45°, Perspektive korrigieren (vier Eckpunkte), Helligkeit, Kontrast, Gamma und Sättigung. Live-Vorschau mit denselben Schritten und Formeln wie die serverseitige Verarbeitung (Imagick, mit GD nur die Geometrie). Beim Korrigieren der Perspektive zeigt eine mitlaufende Vorschau das entzerrte Ergebnis, „Ergebnis groß anzeigen“ wechselt zwischen Eckpunkten und Ergebnis.
- **Als neue Datei oder ersetzend speichern**: neue Dateien übernehmen Titel und alle Metadaten; beim Ersetzen bleiben Dateiname und Verwendungen erhalten, das Original wird aufbewahrt und lässt sich wiederherstellen. Fokuspunkte werden mitgerechnet.
- Neue Rechte `mediaplace[edit_image]` und `mediaplace[edit_image_overwrite]`.

### Entfernt
- Integration des cropper-Addons (Zuschneiden-Canvas, `mediaplace_crop`-Endpunkt). Das cropper-Addon selbst funktioniert außerhalb von MediaPlace unverändert weiter.

## Version 2.3.0 – 2026-10-02

### Neue Features
- **Verwendungs-Filter „Nur benutzte“ / „Nur unbenutzte“** (#9): der bisherige Button „Nur unbenutzte“ ist jetzt ein Auswahlfeld „Verwendung“ mit drei Zuständen (Alle / Nur benutzte / Nur unbenutzte) – kein zusätzlicher Button in der Filterleiste. Im kompakten Layout stehen beide Optionen im Filter-Dropdown. Während die Verwendung geprüft wird, bleibt das Raster stehen und das Feld zeigt einen Spinner, statt kurz „0 Treffer“ anzuzeigen.
- **Tagliste nutzt den freien Platz der Sidebar**: statt fester 220 px wächst die Liste in den Raum unter Kategorien und Sammlungen und scrollt erst, wenn dieser ausgeht. Lange Listen behalten eine Mindesthöhe. Zeilen so kompakt wie die Kategorie-Zeilen, beim Anklicken eines Tags springt die Liste nicht mehr nach oben.

### Bugfixes
- **Tags von Dateien in der Hauptkategorie fehlten in der Sidebar** (#10): für Admins wurde die Hauptkategorie bei den Tag- und Sammlungszählern nicht berücksichtigt – solche Tags tauchten nie im Sidebar-Filter auf, Sammlungen zeigten zu niedrige Zahlen.
- **Neu vergebene Tags erschienen erst nach einem Neuladen in der Sidebar** (#10): nach dem Speichern im Detail-Panel werden jetzt auch die Tag-Zähler aktualisiert.
- **Einheitliche Bezeichnung „Hauptkategorie“** (#10): Auswahlfeld und Hinweistexte sagten bisher „(Hauptverzeichnis)“ bzw. „Kein Ordner“.
- **`&quot;` und andere HTML-Entities in Hinweisen** (#10): Sprachtexte wurden an mehreren Stellen doppelt escaped (u. a. „Sammlung aktiviert“-Hinweis, Toasts, Fehlermeldungen, Tooltips). Neue Hilfsfunktion `MPCore.i18n.tText()` für reine Textausgaben.

## Version 2.2.0 – 2026-09-18

### Neue Features
- **Hintergrund-Modus für "ALT-Texte vervollständigen"**: die Admin-Seite `mediaplace/ai_alt_complete` startet die vollständige KI-Vervollständigung jetzt wahlweise als serverseitigen Hintergrundprozess (`php bin/console mediaplace:ai-alt-complete`, analog zum "Vorschaubilder vorwärmen"-Hintergrundmodus) statt nur im Browser mit offenem Tab – läuft weiter, auch wenn der Tab geschlossen wird, die Seite kann jederzeit neu geladen werden und zeigt den laufenden Fortschritt weiter an. Automatischer Fallback auf den Browser-Modus, falls `shell_exec()` auf dem Server nicht verfügbar ist.

### Bugfixes
- **Bilder mit bereits einer gepflegten Sprache wurden fälschlich als "erledigt" gewertet**: sowohl beim klassischen `med_alt`-Feld als auch beim eigenen JSON-Alt-Feld prüfte die "ALT-Text fehlt"-Ermittlung (`AltTextStatus`) bisher nur, ob IRGENDEINE Sprache bereits einen Text hat, statt ob ALLE konfigurierten Sprachen vollständig sind. Ein Bild mit nur deutschem ALT-Text (Niederländisch noch leer) wurde dadurch von der KI-Vervollständigung (Bulk-Panel, `ai_alt_complete`-Seite, Cronjob) sowie der "Medien ohne ALT-Text"-Ansicht nie wieder angefasst. Prüft jetzt korrekt pro konfigurierter Sprache.
- **KI-ALT-Text-Button im Metadaten-Dialog schrieb ins Leere**: bei mehrsprachigen ALT-Text-Feldern (`metainfo_lang_fields`, Typ `lang_text`/`lang_text_all`) schrieb der Zauberstab-Button den generierten Text in ein unsichtbares Sammel-Feld statt in die sichtbaren Sprachfelder – der Button wirkte dadurch komplett wirkungslos. Schreibt jetzt korrekt in jedes sichtbare Sprachfeld (inkl. automatischem Anlegen einer noch nicht vorhandenen Sprachzeile im Repeater-Modus).

## Version 2.1.0 – 2026-09-18

### Neu
- **Schnellauswahl im Einzelauswahl-Modus**: Wenn das Overlay für ein einzelnes Bild-Feld geöffnet ist (kein Multi-Select), erscheint auf jeder Kachel ein grüner Auswahl-Button – Grid-Ansicht beim Hover oben rechts, Listenansicht als eigene, dauerhaft sichtbare Spalte, Media-Wall-Ansicht in der bestehenden Hover-Toolbar. Ein Klick wählt die Datei sofort aus und schließt das Overlay, ohne erst die Detailansicht öffnen zu müssen. Ein normaler Klick auf die Kachel selbst öffnet weiterhin wie gewohnt die Detailansicht.

## Version 2.0.2 – 2026-09-11

### Fixes
- Gesamt-Fortschrittsbalken beim Upload blieb bis zum Abschluss der jeweiligen Datei unverändert: er wurde nur nach kompletten Dateien aktualisiert, nicht anhand des Chunk-Fortschritts der aktuell laufenden Datei. Bezieht den Byte-Fortschritt der laufenden Datei jetzt live mit ein.

## Version 2.0.1 – 2026-09-11

### Fixes
- Video-Upload schlug mit „Unknown field(s): permitted_only" fehl: der Chunked-Upload-Pfad (`media/upload`-Init) validiert Request-Bodys inzwischen strikt, `permitted_only` war dort nie ein bekanntes Feld und wurde serverseitig ohnehin nicht ausgewertet (REDAXOs `hasCategoryPerm()` prüft Kategorie-Rechte bereits kaskadierend). Entfernt aus Upload/Update/Delete in `mediaplace-api.js`.

## Version 2.0.0 – 2026-09-02

### Breaking Change
- **"mp3"-Namenskürzel durchgängig auf "mp" umgestellt**: MediaPlace hieß ursprünglich "MediaPool 3.0" (daher "mp3"), die interne Namenskonvention wurde bei der Umbenennung zu "MediaPlace" nie mitgezogen. Betrifft die komplette öffentliche Oberfläche:
  - Widget-CSS-Klasse `mp3-widget` → `mp-widget`
  - Widget-Daten-Attribute `data-mp3-multiple`/`data-mp3-upload`/`data-mp3-types`/`data-mp3-max`/`data-mp3-view` → `data-mp-*`
  - JS-API `window.MP3` → `window.MP` (`MP3.open()` → `MP.open()`, `MP3.close()`, `MP3.registerX()` → analog)
  - Widget-JS-API `window.MP3Widget` → `window.MPWidget`
  - Overlay-CSS-Klassen (`.mp3-*` → `.mp-*`), CSS-Variablen (`--mp3-*` → `--mp-*`), Widget-CSS-Klassen (`mp3w-*` → `mpw-*`)
  - Interne Details ohne öffentliche Wirkung (localStorage-Keys, Cache-Buster-Parameter) sind mitgezogen, betreffen aber nur den eigenen Browser-Zustand.

  Bestehende Integrationen (Modul-Vorlagen, YForm-Templates, eigener JS-Code, der `window.MP3`/`data-mp3-*` nutzt) müssen entsprechend angepasst werden. Da das Addon noch nicht produktiv im Einsatz war, wurde bewusst kein Kompatibilitäts-Alias eingebaut – sauberer Neustart der Namenskonvention. `README.md`/`DEV.md`/das Demo-Tab sind vollständig aktualisiert.

### Behoben
- **Einstellungen: Checkboxen wurden nie zuverlässig gespeichert.** Die Einstellungsseite (`pages/settings.php`) hat POST-Daten bisher unter dem festen Schlüssel `mediaplace` gesucht, um deaktivierte Checkboxen korrekt als `false` zu speichern (Checkboxen senden beim Abschicken keinen Wert, wenn sie deaktiviert sind – ein bekanntes `rex_config_form`-Verhalten). Tatsächlich gruppiert REDAXO die POST-Felder aber nach dem jeweiligen Fieldset-Legendentext (`klassischer_medienpool`, `funktionen`, `upload`, `ki_funktionen`, je nach Sprache), nie nach dem Addon-Namen – der Abgleich lief also immer ins Leere, wodurch `rex_config::set()` nie aufgerufen wurde und `rex_config::get()` beim nächsten Laden auf den hartkodierten Default zurückfiel. Betroffen waren alle 9 Checkboxen der Einstellungsseite, u.a. "Klassischen Medienpool-Menüpunkt ersetzen", das sich dadurch nie abschalten ließ. Die Erkennung "Formular wurde abgeschickt" läuft jetzt über den vom Save-Button-Namen abgeleiteten, sprachunabhängigen Schlüssel (`md5($addonName) . '_save'`), das Auslesen der Checkbox-Werte scannt generisch alle POST-Gruppen.

## Version 1.28.2 – 2026-09-02

### Behoben
- **"Datei ersetzen" meldete Erfolg, tauschte die Datei aber tatsächlich nie aus**: der 1.28.1-Fix (Umstellung auf `PATCH`) behob zwar den sichtbaren "Method not allowed"-Fehler, deckte aber ein zweites, unabhängiges Problem auf – ist PHPs `enable_post_data_reading` aktiv (php.ini-Default), liest PHP den kompletten Datei-Upload schon vor jedem eigenen Code aus dem Request und verwirft ihn bei PUT/PATCH-Requests wieder, ohne ihn nutzbar zu machen. Für den Server blieb dann nichts mehr zum Verarbeiten übrig, meldete aber trotzdem "erfolgreich ersetzt" (nur Metadaten wurden aktualisiert, die Datei selbst nie). "Datei ersetzen" läuft jetzt über einen eigenen, schlanken MediaPlace-Endpunkt (echtes POST, direkt auf REDAXOs Medien-Service) statt über die betroffene api-Addon-Route – keine Änderung am api-Addon nötig oder vorgenommen, der zugrundeliegende Bug dort ist als [Issue #81](https://github.com/FriendsOfREDAXO/api/issues/81) gemeldet. Mehrfach per echtem Browser-Test verifiziert.

## Version 1.28.1 – 2026-09-02

### Behoben
- **"Datei ersetzen" schlug immer mit "Method not allowed" fehl**: der lokale Datei-Ersetzen-Dialog schickte einen `POST`-Request an die api-Addon-Route `media/{filename}/update`, die aber ausschließlich `PUT`/`PATCH` akzeptiert (der Server hat dafür sogar einen eigenen Multipart-Parser, weil PHP `$_FILES` nur bei POST automatisch befüllt). Betraf jede Installation, nicht nur einzelne Server – auf `PATCH` umgestellt.

## Version 1.28.0 – 2026-09-01

### Neu
- **Vorschaubilder vorwärmen** (neue Admin-Seite): erzeugt den kompletten Bestand an Grid-Vorschaubildern einmal vollständig vorab, statt sie erst beim ersten Betrachten "on demand" zu generieren – hilfreich nach einer Migration/Erstinstallation mit vielen bereits vorhandenen Dateien, wenn auf einem langsamen Server viele gleichzeitige Vorschau-Anfragen beim Scrollen spürbar bremsen. Zwei Modi: **im Hintergrund** (läuft als abgekoppelter Server-Prozess weiter, auch wenn der Tab geschlossen wird – Seite neu laden zeigt den Fortschritt weiter an) oder **im Browser** (treibt den Vorgang direkt an, Tab muss offen bleiben; automatischer Rückfall, falls der Server keine Hintergrundprozesse erlaubt). Mit Live-Vorschau des zuletzt erzeugten Vorschaubilds. Ergänzt den bestehenden Cronjob, der nur laufend neue Dateien nachzieht.
- **Vollbild-Zustand wird gemerkt**: der Overlay startet jetzt im zuletzt gewählten Vollbild-/Fenster-Modus statt immer im normalen Fenster.

### Behoben
- **ffmpeg-Integration**: "Video optimieren" und der zugehörige Status im Detail-Panel setzten stillschweigend ffmpeg-Addon-Version 4.5.0+ voraus (globale Job-Engine) – auf älteren, weiterhin funktionsfähigen ffmpeg-Installationen (Vorschau lief dort einwandfrei weiter) führte das zu "Call to undefined method"-Fehlern. Jetzt sauber versionsgeprüft: fehlt die passende ffmpeg-Version, bleibt nur das Optimieren-Feature ausgeblendet, alles andere funktioniert unverändert.

## Version 1.27.0 – 2026-08-31

Ein bisschen Politur am Kategorie-Menü, Toasts die man auch wirklich sieht, und ein handfestes Safari-Rendering-Problem in der Media-Wall-Ansicht ausgemerzt – inklusive einer generalüberholten Hover-/Auswahl-Farblogik, die jetzt überall (Grid, Media Wall, Liste) gleich tickt.

### Neu
- **Infobereich im Kategorie-Dreipunkt-Menü**: zeigt am Ende des Menüs Kategorie-ID (mit Kopieren-Button), Namen und – sofern Massenaktionen erlaubt sind – die Dateianzahl der Kategorie an.
- **Neue Hover-/Auswahl-Farblogik**: Hover ist jetzt durchgängig auffällig blau (mit weichem Glow), eine ausgewählte Datei leuchtet hell grün (ebenfalls mit Glow), und wer eine bereits ausgewählte Datei hovert, bekommt ein eigenes, helleres Blau – so ist auf einen Blick klar, welcher Zustand gerade zutrifft. Gilt einheitlich für Grid-, Media-Wall- und Listenansicht, Light- und Dark-Theme.

### Verbessert
- **Toast-Benachrichtigungen** erscheinen jetzt mittig im Overlay statt unten, wo sie leicht untergingen.

### Behoben
- **Safari: Rendering-Glitch in der Media-Wall-Ansicht** – beim Hover einer Kachel konnte WebKit die komplette Mehrspalten-Ansicht neu balancieren, sichtbar als flackernde Nachbarkacheln bzw. eine kurze Nahtlinie am Ende der vorherigen Spalte. Betraf nur den normalen (nicht ausgewählten) Hover-Zustand und ist jetzt Safari-spezifisch entschärft, ohne dass Chrome/Firefox etwas davon merken.

## Version 1.26.0 – 2026-08-31

### Neu
- **Direkter Upload über die klassischen `REX_MEDIA[n]`/`REX_MEDIALIST[n]`-Widgets**: der "+"-Button öffnet MediaPlace jetzt direkt im Upload-Modus (nativer Datei-Dialog sofort, kein Umweg über Browsen-dann-Hochladen) statt der normalen Auswahlansicht. Ist am Widget eine feste Zielkategorie konfiguriert, wird direkt dorthin hochgeladen; sonst wird – wie beim regulären Upload-Button auch – nach der Zielkategorie gefragt. Nach dem Upload wird die Datei automatisch ausgewählt und der Overlay schließt sich. Neuer öffentlicher Einstiegspunkt `MP3.openUpload(callback, opts)` für eigenen Code mit demselben Bedürfnis.

## Version 1.25.0 – 2026-08-31

### Neu
- **Akzentfarbe je Cloud-Verbindung**: eine in `cloudconnect` konfigurierte Verbindung kann jetzt eine eigene Akzentfarbe bekommen (siehe dortige Einstellungsseite) – das Cloud-Icon in der Sidebar färbt sich entsprechend ein, hilft mehrere gleichzeitig aktive Verbindungen desselben Typs (z. B. zwei Nextcloud-Accounts) auf einen Blick zu unterscheiden. Ohne gesetzte Farbe unverändertes Verhalten.

## Version 1.24.0 – 2026-08-31

Mobile Nutzung im Fokus: ein informeller Usability-Test auf sehr schmalen Bildschirmen hat mehrere konkrete Schwachstellen aufgedeckt, hier größtenteils behoben. Dazu ein hartnäckiger Anzeige-Bug bei "Medien ohne ALT-Text", spürbar besseres Upload-Feedback im Eingabe-Widget und die Möglichkeit, eine Datei auch aus einer Cloud-Quelle heraus zu ersetzen.

### Neu
- **Datei ersetzen aus Cloud-Quelle**: neben dem bestehenden "Ersetzen"-Dialog (lokaler Datei-Upload) steht in der Detailansicht jetzt ein zweiter Button bereit, sofern über `cloudconnect` mindestens eine Cloud-Verbindung (WebDAV/Nextcloud/Dropbox) konfiguriert ist – öffnet das Cloud-Browsing, die gewählte Datei ersetzt Inhalt/Dateigröße der bestehenden Datei direkt (Dateiname/Kategorie/`rex_media`-ID bleiben unverändert, es wird keine neue Datei angelegt). Ohne konfigurierte Cloud-Verbindung bleibt der Ersetzen-Dialog unverändert.
- **Toast-Benachrichtigung**: neue, wiederverwendbare kurze Erfolgs-/Fehlermeldung (statt stillem Verschwinden) – nach Datei-Ersetzen (lokal wie aus der Cloud) und nach Datei-Upload im Overlay.

### Verbessert
- **Detailansicht auf schmalen Screens**: die untere Aktionsleiste (Ersetzen/Herunterladen/Merken/Speichern/Löschen) bricht jetzt um statt "Löschen" abzuschneiden; "Speichern" wird dabei auf ein reines Icon verkleinert.
- **Touch-Ziele vergrößert**: mehrere Symbolleisten- und Sidebar-Icons (Kategorien/Ansicht/Verwaltung/Schließen im Header, Sammlung bearbeiten/umbenennen/löschen, Ein-/Ausklappen, Mehrfachauswahl-Umschalter) waren auf Mobile deutlich unter gängigen Touch-Ziel-Empfehlungen (u. a. WCAG 2.5.5) – jetzt mindestens 44×44px, nur auf schmalen Screens, Desktop bleibt unverändert kompakt.
- **Kategorie-/Sammlungs-Sidebar auf Mobile**: eigener, sticky Schließen-Header ergänzt – vorher verdeckte das Offcanvas-Menü auf schmalen Screens den Header (Schließen-/Zahnrad-Button) komplett, ohne erkennbaren Rückweg außer dem Antippen eines Eintrags.
- **Upload-Feedback im Eingabe-Widget** (`data-mp3-upload`): echter Fortschrittsbalken statt nur einer Textzeile, plus kurz sichtbar bleibende Erfolgs-/Fehlermeldung (vorher verschwand die Anzeige beim Abschluss sofort, Fehler kamen nur als blockierender Browser-`alert()`).
- **Upload-Feedback im Overlay**: zusätzlich zur bestehenden Fortschrittsanzeige/Zusammenfassung im Panel jetzt eine kurze Toast-Meldung nach Abschluss (siehe oben) – vorher war nach dem automatischen Grid-Reload keine Abschlussmeldung mehr sichtbar.

### Bugfix
- Datei-Vorschau beim Browsen einer Cloud-Quelle (WebDAV/Nextcloud/Dropbox, siehe `cloudconnect`-Addon) hatte keinen eigenen Schließen-Button – auf Mobile ließ sich die Vorschau dadurch nicht mehr verlassen.
- "Medien ohne ALT-Text" blieb nach einmaligem Aufrufen dauerhaft als aktive Ansicht gespeichert und erschien deshalb bei jedem erneuten Öffnen von MediaPlace wieder – auch wenn längst alle Dateien einen ALT-Text hatten. Der Zustand wird jetzt nicht mehr über Sitzungen hinweg gespeichert.
- Eigene API-Aufrufe sowie Thumbnail-Bilder werden bei einem seltenen, transienten 500er jetzt einmal automatisch wiederholt (Hintergrund: eine Race Condition in REDAXOs eigenem Sprach-Cache direkt nach einem Cache-Clear, siehe [redaxo/core#6648](https://github.com/redaxo/core/issues/6648) – kein mediaplace-spezifischer Fehler, MediaPlace trifft die seltene Race durch mehrere parallele Requests beim Öffnen aber überproportional oft).

## Version 1.23.1 – 2026-08-31

### Bugfix
- "Alle Dateien zu Sammlung hinzufügen" (Kategorie-Kontextmenü) crashte beim Speichern mit "Can't find variable: refreshCollectionsSection" – Funktion war nicht importiert, ist jetzt behoben.
- Sammlungs-Mitgliederzahl in der Sidebar blieb nach dieser Massenaktion auf dem alten Stand stehen, da die Aktion (anders als das Einzeldatei-Umschalten) dem Client nicht mitteilt, wie viele Dateien tatsächlich geändert wurden – wird jetzt frisch vom Server nachgeladen.
- Der "Bestehend oder neu"-Sammlungsname beim Massen-Hinzufügen ließ sich nur exakt abtippen, obwohl vorhandene Sammlungen als Hinweistext angezeigt wurden – jetzt per Autocomplete auswählbar.

## Version 1.23.0 – 2026-08-31

„Aufgeräumt und nachgezählt." – kein großes Feuerwerk, dafür ein ordentlicher Batzen Kleinkram, den wir beim Testen der letzten Releases gefunden haben, plus zwei neue, nützliche Kleinigkeiten. Am wichtigsten: ein Bug, der bei der KI-Massengenerierung mit klassischem `med_alt` Dateien in die Wurzelkategorie verschoben hat, statt den ALT-Text zu schreiben – wer das nutzt, sollte unten unbedingt lesen.

### Neu
- **Speicherverbrauch im Zahnrad-Menü**: zeigt den kompletten Speicherverbrauch des Medienpools (Summe aller Dateigrößen über alle Kategorien) samt Dateianzahl, wird bei jedem Öffnen des Menüs frisch nachgeladen.
- **Dekoratives klassisches ALT-Text-Feld**: neue Box in den Einstellungen legt bei Bedarf `med_alt` und ein zusätzliches `med_alt_decorative`-Checkbox-Feld an (über REDAXOs eigene Metainfo-API, ein Klick genügt). Für Installationen ohne eigenes JSON-Alt-Feld gab es bisher keine Möglichkeit, ein Bild bewusst als "kein ALT-Text nötig" zu markieren – ein leeres Feld zählte immer als fehlend. Wirkt sich überall aus, wo "ALT-Text fehlt" geprüft wird (Sidebar-Filter, Detail-Panel-Hinweis).
- Sortierung "Zuletzt bearbeitet" ergänzt.
- Sammlungen: Mehrfachauswahl bekommt einen eigenen "Auswahl aus Sammlung entfernen"-Button, der nur die Sammlungs-Zuordnung aufhebt.

### Geändert
- "AI Bulk Management" heißt jetzt "KI-Alt-Text-Generator" (bzw. "AI Alt-Text Generator" auf Englisch) – beschreibt treffender, was der Zahnrad-Menü-Eintrag tatsächlich tut.
- Einstellungsseite zweispaltig: Haupt-Formular links, die neue ALT-Feld-Box rechts als Sidebar direkt sichtbar (vorher unter dem langen Formular leicht zu übersehen), mit Warnfarbe solange etwas fehlt.
- Filter-Reset-Button sitzt jetzt stabil neben dem Zahnrad im Header (Desktop und Mobile identisch), statt mal isoliert in der Filterleiste umzubrechen oder auf Mobile ganz zu verschwinden – dazu farbig und leicht pulsierend, damit man ihn bei aktiven Filtern nicht übersieht.
- Veraltete "Lesezeichen"-Hinweistexte rund um Sammlungen entfernt – die Zuordnung läuft längst über den Button im Detail-Panel, nicht mehr per Lesezeichen-Icon.

### Bugfix
- **Wichtig, betrifft KI-Massengenerierung mit klassischem `med_alt`**: schrieb den generierten ALT-Text gar nicht in die Datenbank UND verschob die bearbeitete Datei als Nebeneffekt in die Wurzelkategorie (Kategorie-ID 0). Ursache war eine falsche Verwendung von `rex_media_service::updateMedia()`, die weder `med_alt` kennt noch mit einer fehlenden `category_id` sauber umgeht. Betrifft nur Installationen ohne eigenes JSON-Alt-Feld – wer die KI-Funktionen mit dem klassischen Feld genutzt hat, sollte die betroffenen Dateien auf ihre Kategorie prüfen.
- Sidebar-Eintrag "Medien ohne ALT-Text" erschien nie: die Funktion, die ihn ein-/ausblendet, war zwar geschrieben, aber nirgends aufgerufen.
- Einstellungsseite zeigte an mehreren Stellen `&quot;` statt eines echten Anführungszeichens (doppeltes HTML-Escaping bei Checkbox-/Auswahl-Labels).
- KI-Icon im klassischen Metainfo-Formular landete unter statt im Feld-Label (REDAXOs Standard-Formular verschachtelt das Label anders als angenommen).
- Detail-Panel-Speichern aktualisierte die Ansicht nicht vollständig: Tag-Punkte auf der Kachel blieben nach Tag-Änderungen veraltet stehen, "Medien ohne ALT-Text" reagierte nicht sofort auf gerade vervollständigte Dateien.
- Sammlungen: "Auswahl löschen" hat dort tatsächlich Dateien aus dem gesamten Medienpool gelöscht, obwohl "nur aus der Sammlung entfernen" gemeint war – jetzt behoben (siehe neuer Button oben).
- Die "MediaPlace"-Hauptseite crashte für Redakteure ohne weitergehende Rechte mit einer kryptischen REDAXO-Fehlermeldung, weil keine ihrer Unterseiten für sie zugänglich war. Hauptseite ist jetzt konsequent admin-only, wie es ihre Unterseiten ohnehin schon sind.

Kleines Extra nebenbei: das separate `nextcloud`-Addon hatte einen ähnlichen Rechte-Bug (feingranulare Rechte tauchten in der Rollenverwaltung nie auf) – bereits mit [nextcloud 1.7.1](https://github.com/FriendsOfREDAXO/nextcloud/releases/tag/1.7.1) separat behoben.

## Version 1.22.0 – 2026-08-30

„Alles bestens beim Apfel." – volle Benutzbarkeit auf iOS sowie Massenimport aus Cloud-Speichern (Nextcloud & Co.). Löst die Zwischen-Beta 1.21.1-beta1 ab (Inhalt hier vollständig enthalten).

### Neu
- **Massenimport aus Cloud-Speichern**: im Cloud-Provider-Bereich (z. B. Nextcloud) lässt sich jetzt per Auswahl-Modus-Umschalter eine Mehrfachauswahl treffen – einzelne Dateien per Checkbox oder „Alle auswählen" für den kompletten aktuellen Ordner (nicht rekursiv). Eine eigene Fußleiste zeigt die Auswahlzahl und importiert nach einmaliger Zielkategorie-Wahl alle markierten Dateien in Chunks von je 25, mit Fortschrittsbalken und einer Fehlerliste für einzelne fehlgeschlagene Dateien (bricht die übrigen nicht ab). Neuer Endpunkt `func=import_batch` am bestehenden `mediaplace_provider`-Dispatcher, ruft serverseitig lediglich `importToMediaPool()` je Datei auf – kein bestehender Storage-Provider (z. B. `nextcloud`) musste dafür angepasst werden.
- Werkzeugleiste: neuer Button hebt alle aktiven Filter (Typ, Tags, „Nur unbenutzte") auf einen Klick auf.

### Geändert
- Mobiles Detail-Panel: Schließen-Button jetzt gleich groß wie der des Haupt-Overlays (war zuvor deutlich kleiner und dadurch schwer als Schließen-Aktion erkennbar); das Panel darf bei Bedarf den vollen Bildschirm ausfüllen.
- Tags erscheinen im Detail-Panel jetzt konsequent zuletzt – nach den eigenen Metadaten-Feldern, direkt vor den technischen Details (vorher zwischen "Metadaten bearbeiten"-Button und den eigenen Feldern).
- KI-Icons neben ALT-Text-/Tags-Feld-Label sowie die Feld-Labels selbst vergrößert (bessere Lesbarkeit/Klickfläche).

### Bugfix
- iOS Safari: das Overlay wurde auf dem iPhone teils vom Browser-Chrome (Adress-/Werkzeugleiste) abgeschnitten. Der `100vh`→`100%`→`100dvh`-Fallback aus 1.21.0 saß am falschen Selektor (`.mp3-fullscreen-mode .mp3-modal`, ein separates, nur manuell togglebares Feature) statt am tatsächlich auf schmalen Viewports wirksamen `@media (max-width: 768px)`-Regelwerk, das weiterhin blankes `100vh` nutzte – jetzt am richtigen Ort ergänzt.
- iOS Safari: die Adressleiste konnte zusätzlich dauerhaft in dem Zustand steckenbleiben, den sie genau beim Öffnen des Overlays hatte – durch den (bereits vorhandenen) Scroll-Lock der Hintergrundseite sieht Safari die Seite nie wieder scrollen und hat keinen Anlass, die Leiste zu verkleinern. Ein minimaler, unsichtbarer 1px-Scroll auf `<html>` kurz nach dem Öffnen gibt Safari dieses Signal, ohne die Wiederherstellung der Scrollposition beim Schließen zu beeinflussen.
- Tag-Filter (Sidebar) zeigte auch Tags an, die gar keiner Datei (mehr) zugewiesen sind (z. B. nach Entfernen des letzten Vorkommens) – der Filter listete den kompletten Tag-Katalog statt nur tatsächlich vergebene Tags. Zeigt jetzt nur noch Tags mit mindestens einer (für den User zugänglichen) zugewiesenen Datei, per echtem Server-Count (`SystemTagManager::getTagCounts()`, gleiches Muster wie bei Sammlungen).
- Tags-Combobox im Detail-Panel wurde von nachfolgenden Feldern verdeckt, sobald sie geöffnet blieb (zu niedriger `z-index`).
- Tags-Combobox öffnete sich unerwünscht erneut, wenn ein KI-Tag-Vorschlag per Klick übernommen wurde (Verhalten war eigentlich nur für die manuelle Mehrfachauswahl gedacht).
- Auswahl-Modus-Umschalter in der Werkzeugleiste war im Cloud-Provider-Bereich per CSS komplett ausgeblendet (Rest einer älteren Einschränkung, bevor Mehrfachauswahl dort unterstützt wurde) – verhinderte die Nutzung des neuen Massenimports.

## Version 1.21.0 – 2026-08-30

Zusammengefasste Änderungen seit Version 1.5.3 (Zwischenschritte entfernt, siehe Git-Historie für die volle Detailtiefe).

### Neu
- **Optionale KI-Alt-Text-Generierung** (Einstellungen → "KI-Funktionen"): kleines Zauberstab-Icon direkt im Feld-Label neben dem ALT-Text-Feld (eigenes JSON-Feld UND klassisches `med_alt` im nativen Metainfo-Canvas – schreibt nur ins sichtbare Feld, speichert nicht automatisch) sowie "AI Bulk Management" im Zahnrad-Menü (kategorieübergreifende Massengenerierung für alle Dateien ohne ALT-Text). Nutzt das separate `ai_platform`-Addon für die eigentliche KI-Anbindung (Bildverständnis) – rein soft-optional, ohne installiertes/konfiguriertes `ai_platform` bleibt das Feature inaktiv. Prompt-Profil (Barrierefreiheit/Neutral/SEO) und eigener Prompt konfigurierbar. Zugriff durchgängig rechtegeprüft (nicht nur serverseitig im Endpunkt, sondern auch bei der Button-Injektion selbst: `hasMediaAccess()` fürs Einzeldatei-Feature, zusätzlich das granularere `mediaplace[bulk_operations]`-Recht für die Massengenerierung – Defense-in-Depth, gleiches Muster wie die anderen KI-Bild-Endpunkte). SVG-Dateien (Vektorformat, kein serverseitiger Rasterizer verfügbar) werden beim Einzeldatei-Button clientseitig auf einen Canvas gerendert und als PNG an die KI übergeben (der Browser kann SVG selbst rendern) – in der Massengenerierung dagegen weiterhin übersprungen (kein Client-Rendering im serverseitigen Batch-Lauf verfügbar) und separat gezählt statt einfach zu scheitern. Raster-Bilder werden vor dem KI-Aufruf bei Bedarf auf eine konfigurierbare maximale Kantenlänge verkleinert (Standard 1024px, Einstellungen → "KI-Funktionen") – spart Traffic/Zeit/Kosten besonders bei der Massengenerierung, Original bleibt unverändert. Die Massengenerierung folgt demselben Review-vor-Speichern-Prinzip wie der Einzeldatei-Button: "Alle generieren" erzeugt zunächst nur Vorschläge in einer Prüfliste (Thumbnail + editierbares Textfeld je Datei, größere Vorschau per Klick aufklappbar, einzelne Einträge verwerfbar), geschrieben wird erst nach einem expliziten Klick auf "Übernehmen". Ein Generierungs-Lauf ist auf 25 Dateien begrenzt (jede ist ein echter, synchroner KI-Aufruf – unbegrenzt hätte bei vielen fehlenden ALT-Texten ein einzelner Klick potenziell minutenlang unbeaufsichtigt laufen können, mit Risiko für Session-Timeout/Tab-Schließen vor dem Speichern und einer unübersichtlich langen Prüfliste); "Weitere generieren" holt bei Bedarf den nächsten Abschnitt nach, ohne bereits Generiertes zu verwerfen.
- **Optionale KI-Tag-Vorschläge** (Einstellungen → "KI-Funktionen", zusätzlich zur KI-Alt-Text-Generierung): gleiches kleines Icon im Tags-Feld-Label, Vorschläge erscheinen als anklickbare Chips – Klick fügt den Tag über denselben Weg wie eine manuelle Auswahl hinzu (kein automatisches Speichern). **Geschlossenes Vokabular**: die KI erstellt nie neue Tags, sondern wählt ausschließlich aus Tags, die in der Tag-Verwaltung gezielt über eine neue Spalte "KI-Vorschläge" pro Tag freigegeben wurden (Default: kein Tag freigegeben) – die Modellantwort wird zusätzlich serverseitig gegen genau diese Freigabeliste gefiltert, nie blind übernommen. Obergrenze konfigurierbar (Standard 3 Tags pro Datei). Teilt sich das Bildverständnis-Profil und die Bild-Downscale-Einstellung mit der ALT-Text-Generierung (gemeinsame `AiImagePreparer`-Bildaufbereitung inkl. SVG-Rasterisierung). Bulk-Tagging über mehrere Dateien ist (noch) nicht Teil dieser Version.
- Tag-Verwaltung: neuer Tags lassen sich jetzt direkt dort anlegen (Name, Farbe, optional sofort "Für KI-Vorschläge freigeben"), statt sie erst durch Zuweisen an eine Datei entstehen zu lassen – zusammen mit der neuen KI-Freigabe-Spalte die Grundlage für ein gezielt kuratiertes KI-Tag-Vokabular.
- Vollständiger Ersatz des klassischen Medienpools durch MediaPlace: nicht nur der Haupt-Menüpunkt, auch "Medium hinzufügen"/"Kategorieverwaltung" sowie Direktaufrufe/Deep-Links (`?page=mediapool/media&file_id=...`) landen jetzt auf der MediaPlace-Vollbildansicht. TinyMCE/CKEditor5 und die klassischen `REX_MEDIA[n]`/`REX_MEDIALIST[n]`-Popups funktionieren unverändert weiter (eigener Popup-Vertrag wird nachgebildet). Kollisionsschutz mit `filepond_uploader`s eigenem `replace_mediapool`-Schalter.
- Cloud-Provider-Anbindung: andere Addons können sich als zusätzlicher Baum in die Sidebar einklinken (Browsen, Suchen, Import einzelner Dateien in den lokalen Medienpool) über einen neuen Erweiterungspunkt `MEDIAPLACE_STORAGE_PROVIDERS`. Erster Provider: das `nextcloud`-Addon.
- Upload-Anbieter-Anbindung: andere Addons können den eingebauten Upload-Button/Drag&Drop/Paste im Overlay durch ihren eigenen Dialog ersetzen – Registrierung serverseitig über den neuen Erweiterungspunkt `MEDIAPLACE_UPLOAD_PROVIDERS` (Label + eigenes Recht), clientseitig über `MP3.registerUploadProvider(id, handler)`. Neue Einstellung "Upload-Anbieter" wählt den aktiven Provider aus (Standard: eingebaut). Erster Provider: das `filepond_uploader`-Addon (übergibt die Dateien an dessen eigenen Metadaten-Dialog vor dem eigentlichen Upload).
- Zahnrad-Menü-Erweiterungspunkt: andere Addons können über `MP3.registerAdminMenuItem(id, {label, icon, onClick})` einen eigenen Eintrag ins Verwaltungsmenü einhängen, der JS-Code innerhalb des laufenden Overlays ausführt (anders als die bisherige Liste klassischer Unterseiten, die immer im Popup-Fenster öffnet).
- Mehrfachauswahl grundlegend überarbeitet: sichtbare Checkbox-Overlays statt nur versteckter Ctrl/Cmd+Klick-Geste, Sammelaktionen "Auswahl verschieben"/"Auswahl löschen" in einer Auswahl-Fußleiste – deckt jetzt alle klassischen Medienpool-Mehrfachaktionen ab.
- Sammlungen: fester Zuordnungs-Button im Detail-Panel (ersetzt das fehlerhafte Lesezeichen-System), echte globale Mitgliederzahl pro Sammlung statt nur der aktuell geladenen Kategorie.
- Tags: eigene Combobox mit Mehrfachauswahl + "neu anlegen"-Option ersetzt das frühere native `<datalist>`-Autocomplete. Neu abgeschlossen: zentrale **Tag-Verwaltung** (eigene Backend-Seite) zum Umbenennen (kaskadiert automatisch auf alle Dateien), Farbe zentral ändern und Löschen von System-Tags – erreichbar über eine neue, eigene Berechtigung `mediaplace[manage_tags]` sowie einen Eintrag im Zahnrad-Menü des Overlays. Die Farbe eines *bestehenden* Tags ist damit nur noch hier änderbar; das per-Datei-Farb-Swatch im Tag-Widget erscheint nur noch direkt bei der Neuanlage eines Tags.
- Sidebar: die drei großen Abschnitte (Kategorien, Sammlungen, Tags) lassen sich einzeln ein-/ausklappen (Zustand in `localStorage`); der Tag-Filter zog aus einem Dropdown in einen eigenen, immer sichtbaren Sidebar-Abschnitt um.
- Video/Bild-Optimierung: "Bild optimieren"-Button für Bestandsdateien über den Upload-Resize-Grenzen (GD-Resize in-place), wählbarer Video-Vorschau-Modus (Aus/Einzelbild/Animiert), "Bereits optimiert"-Badge mit Kompressionsrate, aufklappbarer "Technische Details"-Bereich für Videos (ffprobe-Daten, lazy geladen), Cronjob "Vorschaubilder vorwärmen" für Bild- und Video-Thumbnails.
- "Pro Seite"-Auswahl bietet zusätzlich "Alle" an; Dark/Light-Umschalter zog ins Zahnrad-Menü.
- JS-Architektur: der komplette Overlay-Kern (ursprünglich 7313 Zeilen als Einzeldatei) wurde in einem esbuild-basierten Build-Prozess schrittweise in eigenständige ES-Module aufgeteilt (Cloud-Provider, Modals, Lightbox, Fokuspunkt, Zuschneiden, Optimieren, Sammlungen, Kategorien, Filter, Grid-Rendering, Detail-Panel, Upload, Multi-Select) – der verbleibende Hub ist dadurch auf ca. 3640 Zeilen geschrumpft (-50%), bei unveränderter Funktionalität.
- Neuer Sidebar-Eintrag "Medien ohne ALT-Text" direkt unter dem Medien-Abschnitt (nur sichtbar, wenn in den Einstellungen aktiviert – Default an – UND überhaupt ein ALT-Text-Feld – eigenes oder klassisches `med_alt` – existiert UND tatsächlich mindestens eine Datei betroffen ist, per echtem Server-Count): globaler, rechte-gefilterter Filter über alle zugänglichen Kategorien, analog zu Sammlungen als eigener Ansichts-Modus. Folgt derselben Prioritäts-Logik wie der "ALT-Text fehlt"-Hinweis im Detail-Panel (`AltTextStatus`) – ist das eigene Metadaten-Feld aktiv, zählt ausschließlich dieses, nicht zusätzlich das klassische `med_alt`.
- iOS-Safari-Layoutfehler beim Öffnen im Vollbild-Modus behoben: `100vh` bezieht sich dort auf die größtmögliche (Adressleiste eingeklappt) statt der tatsächlich sichtbaren Höhe, wodurch der Header verrutscht/der untere Rand abgeschnitten wirkte, bis Safari die Leiste einklappte. Fallback-Kette `100vh` → `100%` → `100dvh` behebt das für moderne Mobile-Browser, ohne ältere zu verschlechtern.
- Doppelte Scrollbalken behoben: die Hintergrundseite bleibt jetzt hinter dem Overlay eingefroren (per `position: fixed` + Scroll-Offset, nicht `overflow: hidden` – Letzteres würde mit REDAXOs fixer `html`/`body`-Höhe im Backend die Scroll-Position dauerhaft auf 0 zurücksetzen, ein bereits einmal aufgetretener Bug).
- "In Benutzung"-Fehlermeldungen im Massenaktionen-Fortschrittsmodal überarbeitet: kein doppelter, fetter Dateiname-Vorspann mehr (REDAXOs eigene Meldung nennt ihn schon), lange Dateinamen brechen jetzt korrekt um statt aus dem Modal auszubrechen, eigene kompaktere Liste/Link-Optik statt Browser-Default. Der enthaltene "wo benutzt"-Link schloss MediaPlace bisher nicht und führte ins Leere (REDAXOs `openPage()` ist für ein Popup-Fenster gebaut, nicht für ein Vollbild-Overlay im selben Fenster) – Klick schließt MediaPlace jetzt und navigiert zur referenzierenden Seite. Gilt für alle "in Benutzung"-Meldungen (Detail-Panel-Löschen wie Massenaktionen).
- Massenaktionen für ganze Kategorien im Kategorie-Kontextmenü: "Alle Dateien verschieben", "Alle Dateien taggen", "Alle Dateien zu Sammlung hinzufügen", "Alle Dateien löschen" – Ergänzung zur Checkbox-Mehrfachauswahl für Kategorien mit sehr vielen Dateien (die weder als Checkbox-UI noch als Ladevorgang skaliert). Verschieben/Löschen laufen serverseitig in Batches mit Fortschrittsanzeige und echtem Abbrechen-Button (Chunking gegen PHP-Zeitlimits bei sehr großen Kategorien); Löschen zeigt vorab die tatsächliche Dateianzahl mit deutlicher Warnung, dass der Vorgang nicht rückgängig gemacht werden kann. "In Benutzung"-Meldungen beim Löschen zeigen jetzt dieselben Links zu den referenzierenden Objekten (Artikel etc.) wie der klassische Medienpool – gilt jetzt auch für die Löschen-Meldung einzelner Dateien im Detail-Panel. Neue, eigene Berechtigung `mediaplace[bulk_operations]` schaltet diese Massenaktionen frei (Kategorie-Zugriff allein reicht nicht, deutlich größeres Blast-Radius-Risiko als einzelne Datei-Operationen).

### Geändert
- Sammlungen sehen nirgends mehr wie Tags aus (Farb-Dots auf Datei-Kacheln, im Sammlungen-verwalten-Dialog und im Tag-Widget waren zuvor optisch nicht unterscheidbar).
- Lange, leerzeichenfreie Dateinamen sprengen Bestätigungs-/Auswahl-Dialoge nicht mehr um (zentral in den gemeinsamen Modal-Bausteinen behoben).
- Diverse Performance-/Speicher-Fixes für die Video-Vorschau im Grid (IntersectionObserver statt dauerhaft geladener animierter Bilder, kleinere Thumbnail-Zielgröße, konsequentes `loading="lazy"`).
- Mobile Auswahl-Fußleisten zeigen auf schmalen Bildschirmen nur noch Icons statt umbrechender Textlabel.
- "Metadaten bearbeiten" ist bei Neuinstallationen jetzt standardmäßig aktiviert.
- Der "Bitte ALT-Text hinterlegen"-Hinweis unter dem "Metadaten bearbeiten"-Button erscheint nur noch bei Bild-Dateien – bei PDF/Video/Audio ergibt der Hinweis keinen Sinn (`AltTextStatus::isMissing()` prüft jetzt zuerst `$media->isImage()`).
- Sidebar-Sektion "Kategorien" heißt jetzt "Medien" mit dem Icon des klassischen Medienpools (`rex-icon-media`) statt eines Ordnerbaum-Symbols.
- Der Panel-Header im Detail-Panel zeigt jetzt nur noch die statische Überschrift "Details" – ein kurzer Versuch, den Titel direkt dort editierbar zu machen (um die Dopplung mit dem "Titel"-Feld darunter zu vermeiden) machte das Eingabefeld dort zu schmal; das "Titel"-Feld bleibt daher wie gewohnt unterhalb der Vorschau.
- Ansicht-Umschalter (Kacheln/Liste/Media-Wall) ist jetzt ein einzelner Button statt 3 nebeneinander – öffnet ein kleines Dropdown, der Button selbst zeigt immer das Icon der aktiven Ansicht. Gilt auch auf dem Desktop, nicht nur mobil.
- Sortierung wandert im mobilen (schmalen) Layout aus der Werkzeugleiste ins Zahnrad-Menü – ein Icon-Ziel weniger in der ohnehin vollen mobilen Kopfzeile.
- Sortierung ist jetzt ein eigener Trigger-Button mit Popover statt eines nativen `<select>` – konsistent durchgängig stylebar (ein natives `<select>`-Options-Panel lässt sich browserseitig nicht vollständig gestalten), gleiches Muster wie der Ansicht-Umschalter.
- Alle Header-Buttons (Zahnrad, Vollbild, Schließen, Sortierung, Ansicht, Upload, Suchfeld, mobiles Kategorien-Icon) haben jetzt durchgängig dieselbe explizite Höhe statt teils padding-, teils fest-basierter Werte – 36px auf dem Desktop, 38px im mobilen Layout (etwas großzügiger für Touch-Bedienung).
- Auswahl-Modus-Checkbox über der Dateiliste vergrößert (20px → 26px) und mit eigenen, für den hellen/dunklen Statusleisten-Hintergrund passenden Farben statt der vom (immer dunklen) Header geerbten – war dort als weißes Icon auf hellem Grund kaum sichtbar.
- Header-Buttons im mobilen Layout vergrößert (bessere Touch-Ziele) und Zahnrad/Schließen wieder korrekt rechtsbündig (waren durch den umbrechenden Werkzeugleisten-Bereich links neben dem Titel hängengeblieben).

### Intern
- Alle `mediapool3*`-Dateinamen (Erbe aus der Zeit vor dem eigenständigen `mediaplace`-Addon) auf `mediaplace*` umbenannt: `assets/mediaplace.js` (Overlay-Bundle, vorher `mediapool3.js`), `mediaplace.css`, `mediaplace-i18n.js`/`-helpers.js`/`-api.js`, `mediaplace_widget.js`/`.css`, `mediaplace_classic.js`, Quell-Ordner `src/mediaplace/` (vorher `src/mediapool3/`). `legacy.js` (der Hub, der alle `modules/*.js` importiert und verdrahtet) heißt jetzt `core.js` – der alte Name war seit Abschluss aller 12 Modularisierungs-Phasen irreführend. `DEV.md` entsprechend aktualisiert.
- Alle 13 eigenen `rex_api_function`-Endpunkte laufen jetzt unter dem Namespace `FriendsOfRedaxo\Mediaplace\Api` (siehe https://redaxo.org/doku/5.x/api#namespace-registrierung) statt der `rex_api_<name>`-Klassennamenskonvention – Klassen umbenannt (z. B. `rex_api_mediaplace_json_metainfo` → `Api\JsonMetainfo`), Dateien nach `lib/Api/` verschoben, explizite `rex_api_function::register()`-Aufrufe in `boot.php` ergänzt. Die `rex-api-call`-Bezeichner selbst (z. B. `mediaplace_json_metainfo`) bleiben unverändert – bestehende Client-Aufrufe sind nicht betroffen.

### Bugfix
- Z-Index von `#mp3-overlay` war zwischenzeitlich von 10500 auf 999999 erhöht worden (Kommentar dazu blieb stehen), die davon abhängigen "über dem Overlay liegen"-Ausnahmen für eingebettete TinyMCE-/CKEditor5-Dialoge und das Accessibility-Panel des tinymce-Addons wurden dabei nicht mitgezogen und lagen dadurch faktisch alle unter dem Overlay (unsichtbar/unbedienbar, sobald sie innerhalb von MediaPlace geöffnet wurden). Werte entsprechend angehoben; betraf auch den neuen `filepond_uploader`-Upload-Anbieter, dessen Metadaten-Dialog beim Öffnen aus MediaPlace heraus komplett hinter dem Overlay verschwand (erst nach dessen Schließen sichtbar).
- Mehrere Zustands-Sync-Probleme rund um den nativen Metainfo-Canvas behoben: ALT-Text-Warnungen und Speichern-Button blieben nach dem Zurückkehren aus dem klassischen Formular auf altem Stand.
- 500er-Fehler bei Videos behoben, wenn das `ffmpeg`-Addon nicht (mehr) installiert ist.
- Einstellungen-Checkboxen (inkl. "Klassischen Medienpool-Menüpunkt ersetzen") ließen sich nicht mehr deaktivieren (REDAXO-Kernverhalten von `rex_config_form` bei nicht gesendeten Checkboxen).
- Escape schloss im Metainfo-/Fokuspunkt-/Zuschneiden-Canvas fälschlich das komplette Overlay statt nur den Canvas.
- Fokuspunkt-Button reagierte teils gar nicht auf Klicks (veralteter clientseitiger Gate-Check).
- i18n-Platzhalter-Syntax-Fehler (`%s` statt `{0}`) beim Optimieren-Badge korrigiert.
- Dark/Light-Umschalter im Zahnrad-Menü blendet sich jetzt aus, wenn er ohnehin wirkungslos wäre: er kann nur eine helle Umgebung dunkel machen, nicht eine bereits (von REDAXO explizit oder REDAXO "automatisch" + dunklem System) dunkle Umgebung wieder hell erzwingen – vorher blieb er in diesem Fall sichtbar, hatte beim Klick aber keine erkennbare Wirkung.
- "System-Tags"-Feld im Detail-Panel heißt jetzt nur noch "Tags", Label größer (13px statt 11px) und Tag-Chips deutlich größer (15px statt 11px, plus etwas mehr Innenabstand).
- Tag-Filter (Sidebar) zeigte in "Alle Medien" bzw. großen Kategorien fälschlich "0 Treffer", wenn die getaggten Dateien nicht zufällig auf der bereits geladenen ersten Seite lagen – lief bisher rein client-seitig über die schon geladenen Seiten statt serverseitig zu filtern (gleicher Fehler, den Sammlungen und "Medien ohne ALT-Text" schon vorher hatten und der dort bereits behoben war). Läuft jetzt über denselben serverseitigen Weg (`filter[tags]`, eigener Fallback-Endpunkt) wie diese beiden – korrekt über den gesamten Bestand, nicht nur die geladene(n) Seite(n). Typ-Zähler (Bilder/Videos/…) berücksichtigen aktive Tag-Filter jetzt ebenfalls server-exakt statt nur die geladenen Seiten zu zählen.
- "Ansehen" (Auge-Icon) auf einem klassischen `REX_MEDIA[n]`/`REX_MEDIALIST[n]`-Widget landete innerhalb des nativen Metainfo-Canvas fälschlich im Auswahl-Modus statt die Details der bereits ausgewählten Datei im eigenen Detail-Panel zu zeigen (nur auf "irgendeine Aktion" statt auf die konkrete Aktion geprüft).
- Sidebar-Eintrag "Medien ohne ALT-Text" war immer sichtbar, sobald das Feature aktiviert ist – auch wenn längst alle Dateien einen ALT-Text haben. Blendet sich jetzt per echtem Server-Count ein/aus, bleibt aber sichtbar, während die Ansicht selbst gerade aktiv ist.
- KI-Buttons ("AI generieren" beim ALT-Text-Feld, "KI-Tags vorschlagen" im Tags-Feld) wirkten als ausgeschriebene, eigene Blöcke unruhig im Formular. Beide jetzt als kleines Icon direkt im Feld-Label statt als eigener Button-Block.
- iOS Safari: Formularfelder mit unter 16px Schriftgröße (u. a. das Suchfeld, 12px) lösten beim Antippen den automatischen Seiten-Zoom von iOS aus, wodurch auch der (fixierte) Header aus dem sichtbaren Bereich rutschen konnte. Alle Formularfelder im Overlay haben auf Mobile-Breiten jetzt mindestens 16px.
- Video-Badge auf Grid-Kacheln (Standbild-Vorschau-Modus) verdeckte bei aktiver Mehrfachauswahl die Checkbox (beide oben links positioniert) – Badge sitzt im normalen Grid jetzt oben rechts, im Media-Wall unverändert oben links (dort ist die Checkbox rechts in der Toolbar).
- Feld-Labels (TITEL/SYSTEM-TAGS/BESCHREIBUNG/...) und der "Technische Details"-Umschalter im Detail-Panel waren zu klein (10px bzw. 12px) – jetzt 11px bzw. 13px.
- Der Tag-Feld-Hinweis "Autofill aus bestehenden Tags. Farben gelten systemweit." entfernt (unnötiger Text im Detail-Panel).
- Der "N weitere Sprache(n)"-Umschalter bei mehrsprachigen Feldern zeigte beim ersten Rendern wörtlich "%d weitere Sprache" statt der echten Anzahl (falsche Platzhalter-Syntax `%d` statt `{0}`, REDAXOs `rex_i18n::msg()` kennt nur Letzteres) – betraf ebenso den Tag-Umbenennen-Dialog-Titel (`%s` statt `{0}`). Der JS-seitige Auf-/Zuklappen-Text nutzte zusätzlich hartkodiertes Deutsch statt der Sprachdatei – zeigte an nicht-deutschen Installationen immer Deutsch.
- "Bild optimieren"-Button umbrach bei langem Label (inkl. Bildmaße, z. B. "Bild optimieren (3000×2000 → max. 2000×2000)") zweizeilig – Label bleibt jetzt einzeilig mit Ellipsis, voller Text weiterhin per Tooltip abrufbar.
- Kachelgrößen-Regler im Footer ausgeblendet, wenn das Layout mobil/schmal ist: die dort greifenden kleineren Kachel-Obergrenzen ließen ihn über weite Strecken seines Wertebereichs wirkungslos wirken (nur ein sehr kleiner Teil des Reglers hatte überhaupt eine sichtbare Auswirkung).
- "Bitte ALT-Text hinterlegen" unter "Metadaten bearbeiten" erschien zusätzlich zum eigenen, direkt am ALT-Feld sitzenden Hinweis – doppelt und irreführend, da ein Klick auf "Metadaten bearbeiten" zum klassischen `med_alt`-Feld im nativen Formular führt, nicht zum eigentlich fehlenden eigenen Feld. Der Hinweis unter "Metadaten bearbeiten" erscheint jetzt nur noch, wenn tatsächlich das klassische `med_alt`-Feld die maßgebliche Quelle ist (kein eigenes Alt-Feld aktiv).
- Eine Sammlung zu aktivieren zeigte oft "0 Treffer", obwohl die Sidebar die echte (globale) Mitgliederzahl korrekt anzeigte: der Sammlungs-Modus lud serverseitig einfach Seite 1 der unsortierten Gesamtliste (kein Kategorie-Filter im Sammlungs-Modus, da Sammlungen kategorieübergreifend sind) und filterte erst danach clientseitig auf die Sammlung – lagen die Mitglieder nicht zufällig auf dieser einen geladenen Seite, blieb die Liste leer. `Api\MediaList` unterstützt jetzt `filter[collection]` (SQL-Filterung direkt auf `rex_mediaplace_media_tags`, kategorierechte-bewusst wie die übrige Abfrage) und wird für den Sammlungs-Modus jetzt immer direkt aufgerufen – unabhängig vom `data-api-media-list-secure`-Schalter, der nur die generische Kategorie-Rechtefilterung der normalen Medienliste betrifft, da Sammlungen ein reines MediaPlace-Konzept sind, das die `api`-Addon-Route ohnehin nie kennen konnte.
- "Alle Dateien taggen" im Kategorie-Kontextmenü ließ bisher auch neue Tag-Namen per Freitext zu (analog zur Sammlungs-Massenaktion) – das konnte versehentlich neue, kaum wiederauffindbare Tags erzeugen. Bietet jetzt nur noch eine Auswahl aus bereits vorhandenen Tags an, kein Freitext/Neuanlegen mehr.
- Natives REDAXO-Metainfo-Formular ("Metadaten bearbeiten"-Fallback sowie der eigene JSON-Canvas, beide im selben `#mp3-metainfo-canvas`-Container) war im MediaPlace-Dark-Mode kaum lesbar: REDAXOs eigene, für helles Theme gedachte Farben (dunkler Text auf dunklem Grund bei Labels, grelle weiße Eingabefelder/Buttons) wurden nicht überschrieben. Betraf auch das über `bootstrap-select` gerenderte Auswahlfeld (kein natives `<select>`, eigener Button + eigenes Dropdown-Menü) sowie die mehrsprachige "Langtext"-Feldbox, deren einzelne Sprachzeilen bewusst bei den REDAXO-Originalfarben bleiben (eigener heller Zeilenhintergrund).

## Version 1.5.2 – 2026-08-28

### Bugfix
- Der "Video optimieren"-Button zeigte nie an, dass eine Datei bereits optimiert wurde – anders als ffmpeg's eigene Video-Tools-Seite (siehe dortiges Changelog 4.6.0), die dieselbe Registry bereits auswertet. Button-Beschriftung wechselt jetzt zu "Erneut optimieren" und ein kleines Badge zeigt die erreichte Kompressionsrate, sobald eine Datei per Overwrite-Modus optimiert wurde.

## Version 1.5.1 – 2026-08-28

### Neu
- Aufklappbarer "Technische Details"-Bereich im Detail-Panel bei Videos (Auflösung, Dauer, Seitenverhältnis, Framerate, Format, Bitrate, Video-/Audio-Codec) – nutzt ffmpeg's vorhandene `VideoInfo`-Klasse, lazy nachgeladen erst beim Aufklappen (ffprobe-Aufruf kostet spürbar Zeit, soll das normale Öffnen des Detail-Panels nicht verzögern).

### Bugfix
- Läuft gerade eine Videooptimierung (auch von einer anderen Session oder über ffmpeg's eigene Video-Tools-Seite gestartet), zeigte das Detail-Panel beim (Wieder-)Öffnen keinen Status – erst ein erneuter Klick auf "optimieren" hätte das bemerkt (und dabei nur "läuft bereits" gemeldet, ohne den echten Fortschritt zu zeigen). Das Detail-Panel prüft jetzt beim Rendern, ob für die geöffnete Datei bereits ein Job aktiv ist, und nimmt das Live-Polling automatisch wieder auf.
- Die Video-Vorschau im Grid und der "Video optimieren"-Button prüften bisher nur, ob das `ffmpeg`-Addon installiert ist – nicht, ob das `ffmpeg`-Programm auf dem Server tatsächlich vorhanden/lauffähig ist (viele Webspaces haben es nicht). Alle drei Fähigkeiten (Vorschau/Optimieren/Technische Details) prüfen jetzt einheitlich die echte Verfügbarkeit (gecacht, 1x pro Stunde), Fallback bleibt konsequent das bisherige Datei-Icon bzw. ausgeblendete Buttons.

## Version 1.5.0 – 2026-08-28

### Neu (ffmpeg-Integration)
- Ist das separate `ffmpeg`-Addon installiert, zeigt das Grid für Videos jetzt eine echte, animierte Vorschau (Media-Manager-Typ `mediaplace_video_thumb`, nutzt ffmpeg's `rex_effect_video_to_webp`) statt des Datei-Icons – nativ lazy geladen (`loading="lazy"`), wird also erst beim Sichtbarwerden der Kachel angefordert.
- Neuer "Video optimieren"-Button im Detail-Panel: stößt eine Konvertierung über ffmpeg's Job-Engine an (siehe dortiges Changelog 4.5.0, neuer `overwrite`-Modus) – die Original-Mediendatei wird direkt ersetzt (gleicher Dateiname), Titel/Kategorie/Metainfo-Felder bleiben automatisch erhalten. Läuft im Hintergrund weiter, das Detail-Panel bleibt währenddessen bedienbar; eine kleine Statuszeile unter dem Button zeigt Fortschritt/Ergebnis.
- Neues Rollenrecht `mediaplace[optimize_video]` – ohne dieses Recht ist der "Video optimieren"-Button nicht sichtbar, der Server-Endpunkt lehnt den Aufruf zusätzlich unabhängig vom UI ab. Bestehende Rollen haben das Recht nicht automatisch.

## Version 1.4.4 – 2026-08-28

### Bugfix
- Zurück-Button und Titel im Zuschneiden-Canvas-Header blieben ungestylt (Browser-Default statt des einheitlichen Buttons/Titels wie bei Metadaten und Fokuspunkt) – die entsprechenden CSS-Regeln fehlten für die Zuschneiden-Klassen. Jetzt auf dieselben Regeln gemappt, optisch identisch zu den anderen beiden Canvas-Headern.

## Version 1.4.3 – 2026-08-28

### Bugfix
- Die Zuschneiden-UI zeigte cropper's Info-Sidebar (Vorschau/Zuschnittdaten) immer eingeblendet und nahm dadurch unnötig Platz weg – der eigene Ein-/Ausblenden-Button dafür (`#cropper_sidebar_toggle`) fehlte im eingebetteten Canvas, ohne den bleibt die Sidebar laut cropper's eigener Logik immer sichtbar. Button jetzt im Canvas-Header ergänzt (cropper's `rex_cropper.js` steuert Ein-/Ausblenden und merkt sich die Wahl selbst) – startet standardmäßig eingeklappt.

## Version 1.4.2 – 2026-08-28

### Bugfix
- Die native Metainfo-Bearbeitung (siehe 1.4.0) zeigte auch den vom `cropper`-Addon selbst eingehängten "Zuschneiden"-Link auf die klassische Seite `mediapool/cropper` – ein Klick hätte das Overlay verlassen. Wird jetzt aus dem geladenen Formular-HTML herausgefiltert; der eigene, im Overlay eingebettete Zuschneiden-Button (Detail-Vorschau-Icon) bleibt die einzige Zuschneiden-Möglichkeit.

## Version 1.4.1 – 2026-08-28

### Bugfix
- Das Backend war für jeden User mit dem Recht `cropper[]` komplett unerreichbar (`rex_exception`: "cropper.css ... is already added"). Ursache: das separate `cropper`-Addon lädt seine eigene `cropper.css`/`cropper_ui_fix.css` bereits unconditional für jeden Backend-User mit diesem Recht – MediaPlace hat dieselben Dateien in der eigenen Cropper-Integration (siehe 1.4.0) ein zweites Mal eingebunden, was REDAXOs Asset-Verwaltung ablehnt. MediaPlace bindet die cropper-CSS jetzt nicht mehr selbst ein (JS weiterhin, da cropper das nur auf eigenen Seiten lädt).

## Version 1.4.0 – 2026-08-28

### Neu
- Ist das separate `cropper`-Addon (FriendsOfRedaxo/cropper) installiert und hat der User das Recht `cropper[]`, zeigt das Detail-Panel bei Bildern jetzt einen "Zuschneiden"-Button. Öffnet cropper's eigene UI (Ratio-Presets, Zoom/Rotate/Flip, Live-Vorschau) direkt im MediaPlace-Overlay statt einer eigenen Nachimplementierung – Speichern läuft über cropper's eigenen `CropperExecutor`, ohne Seitenwechsel. Ohne das Recht `cropper[overwrite]` kann nur eine neue Kopie erzeugt werden, nie das Original überschrieben (spiegelt cropper's eigene Rechte-Logik).

## Version 1.3.17 – 2026-08-28

### Neu
- Neues Rollenrecht "Ordner (Kategorien) umbenennen, verschieben oder löschen" (`mediaplace[manage_categories]`, in der Rollenverwaltung als eigene Checkbox). Ohne dieses Recht können User zwar weiterhin innerhalb ihrer freigegebenen Kategorien neue Unterkategorien anlegen, aber keine bestehende Kategorie mehr umbenennen, verschieben oder löschen – unabhängig davon, welche Kategorien ihnen zugewiesen sind. Admins sind wie üblich ausgenommen. Bestehende Rollen haben das Recht nicht automatisch (muss für Redakteure, die Ordner verwalten dürfen sollen, einmalig in der Rollenverwaltung gesetzt werden).

## Version 1.3.16 – 2026-08-28

### Bugfix
- Das Verwaltungsmenü am Zahnrad-Icon konnte auf schmalen Bildschirmen über den rechten Bildschirmrand hinausragen (rein CSS-relativ zum Button positioniert, ohne Rücksicht auf den verfügbaren Platz). Positioniert sich jetzt wie das Tag-Filter- und Kategorie-Aktionsmenü am Viewport geklemmt.

## Version 1.3.15 – 2026-08-28

### Bugfix
- Metadaten- und Fokuspunkt-Bearbeitung öffneten auf schmalen Bildschirmen (Smartphone, oder ein manuell auf Compact-Breite verkleinertes Modal) hinter dem weiterhin sichtbaren Detail-Panel – beide sind dort als Bottom-Sheet über demselben Bereich implementiert. Das Detail-Panel blendet sich jetzt beim Öffnen des jeweiligen Canvas automatisch aus und beim Zurückgehen wieder ein – nur auf schmalen Bildschirmen, auf Desktop-Breite bleiben Detail-Panel und Canvas wie bisher nebeneinander sichtbar.

## Version 1.3.14 – 2026-08-28

### Bugfix
- Auf manchen Backend-Themes/Setups schien auf schmalen Viewports (iPhone) ein fixes Icon (z.B. ein mobiles Nav-Toggle der umgebenden Backend-Seite) durch den Overlay hindurch und verdeckte Bedienelemente – u.a. den "Metadaten bearbeiten"-Button im Detail-Panel. `#mp3-overlay` lag mit `z-index: 10500` nicht immer über solchen Elementen. Auf `999999` angehoben (die separat an `document.body` angehängten Kategorie-Dialoge entsprechend auf `1000000`).

## Version 1.3.13 – 2026-08-28

### Übergangslösung für Installationen ohne den kaskadierenden `api`-Fix
Die eigentliche Behebung von 1.3.9–1.3.12 liegt teils im `api`-Addon (PR [#78](https://github.com/FriendsOfREDAXO/api/pull/78), noch offen). Bis das dort released ist, betreffen die folgenden zwei Punkte alle Installationen, die noch eine `api`-Version ohne diesen Fix einsetzen:

- **Durchsuchen selbst behoben, nicht mehr auf `api` angewiesen**: Die Medienliste nutzt jetzt in jedem Fall (bis `api` die Kaskadierung tatsächlich released hat) MediaPlace's eigenen, rechte-geprüften Fallback-Endpunkt (`rex_api_mediaplace_media_list.php`) statt der direkten `api`-Route – der Fallback kaskadiert bereits seit 1.3.9 korrekt über `MediaPermission`, war aber bisher nur bei `api <1.3.1` aktiv. Damit sind Unterkategorien einer freigegebenen Kategorie beim Durchsuchen jetzt unabhängig von der installierten `api`-Version korrekt sichtbar.
- **Hochladen/Löschen/Verschieben-Ziel bleibt vorerst von `api` abhängig**: Für diese Operationen bietet MediaPlace bewusst keinen eigenen Ersatz-Endpunkt (kein Duplizieren der Schreiblogik). Schlägt eine dieser Aktionen serverseitig mit HTTP 403 fehl (typischerweise beim Arbeiten in einer Unterkategorie einer freigegebenen Kategorie auf einer `api`-Version ohne den Fix), zeigt MediaPlace jetzt statt einer rohen Fehlermeldung einen verständlichen Hinweis auf die Rechte-Grenze – beim Hochladen als Tooltip auf dem fehlgeschlagenen Datei-Icon, sonst als Fehlermeldung im jeweiligen Dialog. Sobald die installierte `api`-Version den Fix enthält, funktionieren diese Aktionen ohne weiteres Update dieses Addons.

## Version 1.3.12 – 2026-08-28

### Bugfix
- Löschen, Titel/Kategorie ändern und Datei ersetzen schlugen für Dateien in einer Unterkategorie einer freigegebenen Kategorie mit HTTP 403 fehl – gleiche Ursache wie beim Durchsuchen (1.3.10) und Hochladen (1.3.11). Nutzt jetzt die kaskadierende `permitted_only`-Option des `api`-Addons auch bei diesen Operationen.
- ~~Bekannte verbleibende Lücke (im `api`-Addon, siehe [api#79](https://github.com/FriendsOfREDAXO/api/issues/79)): Beim Verschieben einer Datei in eine andere Kategorie über das Detail-Panel prüft der Server bislang nur die *aktuelle* Kategorie der Datei, nicht die neue Zielkategorie.~~ Inzwischen im `api`-Addon behoben (Ziel-Kategorie wird jetzt ebenfalls geprüft, unabhängig von `permitted_only`) – kein Update dieses Addons nötig, sobald die `api`-Addon-Version mit dem Fix installiert ist.

## Version 1.3.11 – 2026-08-28

### Bugfix
- Hochladen in eine Unterkategorie einer freigegebenen Kategorie schlug mit HTTP 403 fehl (Datei-Icon in der Upload-Warteschlange blieb rot) – gleiche Ursache wie beim Durchsuchen in 1.3.10: das `api`-Addon prüfte die Ziel-Kategorie beim Hochladen nur auf exakten Treffer, nicht auf Vorfahren. Nutzt jetzt dessen neue kaskadierende `permitted_only`-Option auch beim Hochladen (Direkt-Upload und Chunk-Upload für große Dateien).

## Version 1.3.10 – 2026-08-28

### Bugfix
- Eine leere Unterkategorie innerhalb einer freigegebenen Kategorie wurde als befüllt angezeigt und zeigte Dateien aus „Alle Medien" – Ursache war eine Rechte-Lücke im `api`-Addon (`filter[permitted_only]` prüfte beim Durchsuchen einer Kategorie nur exakten Treffer, nicht Vorfahren, siehe [api#79](https://github.com/FriendsOfREDAXO/api/issues/79)/dortiger Fix). Beim Fehlschlagen fiel MediaPlace automatisch auf „Alle Medien" zurück, aktualisierte dabei aber Breadcrumb und Sidebar-Markierung nicht – die Kategorie blieb optisch ausgewählt, obwohl bereits eine andere Ansicht geladen war. Breadcrumb/Sidebar werden jetzt beim automatischen Ausweichen korrekt mit aktualisiert, als zusätzliche Absicherung unabhängig vom eigentlichen Fix im `api`-Addon.

## Version 1.3.9 – 2026-08-28

### Bugfix
- 1.3.8 machte Umbenennen/Löschen/Verschieben einer freigegebenen Kategorie selbst zu Recht unmöglich – aber `MediaPermission::hasCategoryAccess()` prüfte weiterhin nur exakte Treffer, nicht Vorfahren. Dadurch waren Unterkategorien einer freigegebenen Kategorie (egal ob selbst neu angelegt oder bereits vorhanden) weder im Kategoriebaum sichtbar noch beim Durchsuchen erreichbar, obwohl der User sie laut 1.3.8 frei verwalten können soll. `hasCategoryAccess()` kaskadiert jetzt: Zugriff auf eine Kategorie gilt automatisch für ihren gesamten Unterbaum (bewusste, dokumentierte Abweichung vom klassischen Medienpool, dessen Rechte-Widget jede Kategorie unabhängig behandelt).

### Verbesserungen
- Das Kategorie-Aktionsmenü ("...") zeigt „Umbenennen"/„Verschieben"/„Löschen" jetzt nur noch an, wenn der User dafür tatsächlich Zugriff auf die Elternkategorie hat – „Unterkategorie" bleibt immer verfügbar. Vorher wurden alle vier Aktionen immer angeboten und liefen bei einer geschützten Kategorie serverseitig mit 403 ins Leere.
- „Kategorie verschieben" bietet „(Hauptverzeichnis)" als Ziel nicht mehr an, wenn der User dorthin ohnehin nicht verschieben darf.
- Kategorie anlegen/umbenennen/verschieben/löschen zeigt Fehler nicht mehr per rohem `alert()` mit unverständlichem "Permission denied", sondern inline im jeweiligen Dialog mit einer verständlichen Meldung, wenn die Rechte-Grenze der Grund ist.

## Version 1.3.8 – 2026-08-27

### Sicherheit
- Ein Backend-User mit auf einzelne Kategorien eingeschränkten Medienrechten konnte eine ihm zugewiesene Kategorie selbst umbenennen, löschen oder verschieben (das `api`-Addon prüfte für diese drei Operationen nur Zugriff auf die Kategorie selbst, nicht auf deren Elternkategorie) – die vom Admin festgelegte Ordner-Hauptstruktur war damit vom zugewiesenen User selbst auflösbar. Kategorie anlegen/umbenennen/löschen/verschieben laufen jetzt über einen eigenen, rechte-geprüften Endpunkt (`rex_api_mediaplace_categories`): Umbenennen/Löschen/Verschieben-als-Quelle brauchen jetzt Zugriff auf die **Elternkategorie**, nicht auf die Kategorie selbst – ein User mit Zugriff auf Kategorie X kann weiterhin frei innerhalb von X arbeiten (Unterkategorien anlegen/umbenennen/löschen/verschieben), X selbst bleibt aber vor ihm geschützt. Anlegen unter X bleibt unverändert erlaubt (das war bereits korrekt).

## Version 1.3.7 – 2026-08-27

### Bugfix
- Die Zähler an den Typ-Filter-Tabs ("Bilder", "Dokumente", ...) zeigten bisher nur, wie viele der bereits *geladenen* Dateien passen – bei großen Kategorien stand dort z.B. "Dokumente 0", obwohl reichlich PDFs existierten, nur eben noch nicht auf der ersten geladenen Seite dabei waren. Ein Klick auf einen Typ-Tab lud außerdem nicht gezielt nach, sondern filterte nur innerhalb der schon geladenen Treffer. Beide Zähler und das Laden nutzen jetzt serverseitige `filter[types]`-Abfragen (Kategorie + Suche exakt berücksichtigt) und zeigen/laden die echte Gesamtzahl. Tag-Filter bleiben bewusst client-seitig (keine serverseitige Tag-Filterung in der Medienliste vorhanden) – bei aktivem Tag-Filter fallen die Zähler auf die bisherige Zählung innerhalb der geladenen Seite zurück.

## Version 1.3.6 – 2026-08-27

### Neu
- `MP3.open(callback, { allowedExtensions: [...] })`: neue, harte Auswahl-Einschränkung für Aufrufer, die bereits klassisch eine Dateiendungs-Beschränkung durchgesetzt haben (z. B. `mform`s Custom-Link-/Medialisten-Widgets mit `types="jpg,png"`). Anders als `filter` (nur Start-Tab, jederzeit umschaltbar) blendet dies nicht passende Dateien komplett aus dem Grid aus und blockiert die Auswahl auch über die Mehrfachauswahl-Bestätigung.

## Version 1.3.5 – 2026-08-27

### Entfernt
- Die Feldtypen „TinyMCE" und „CKEditor5" für eigene JSON-Metainfo-Felder (MediaPlace → Metainfo Felder) sind entfallen, inklusive des dafür eingeführten Vollbild-Editor-Canvas im Overlay. Bereits gespeicherte Felder dieses Typs werden jetzt als einfaches Textfeld angezeigt statt zu crashen. Betrifft ausschließlich MediaPlace' eigenes JSON-Feldsystem (`med_json_data`) – die Metainfo-Canvas-Bearbeitung echter REDAXO-Kernfelder (`med_*`) sowie die TinyMCE/CKEditor5-Integration als Bild-/Medien-Picker (siehe 1.3.4 und früher) sind davon nicht betroffen.

## Version 1.3.4 – 2026-08-27

### Bugfix
- Die Lightbox-Großansicht im Detail-Panel zeigte bislang dieselbe verkleinerte `rex_media_medium`-Vorschau wie das kleine Bild daneben, statt die Original-Datei in Upload-Qualität – genau der Zweck des Vergrößerns. Nutzt jetzt die Original-Datei.
- SVG-Vorschaubilder (Grid, Detail-Panel, Widget) referenzierten die Original-Datei bislang über einen geratenen Pfad (`../media/`, relativ zur aktuellen Backend-Seite) statt über REDAXOs eigene `rex_url::media()` – funktioniert zuverlässig nur, solange die Seite in einer ganz bestimmten Verzeichnistiefe liegt, und wäre bei Unterordner-Installationen mit abweichender Struktur oder einem späteren Frontend-Einsatz zerbrochen. Die Basis-URL wird jetzt serverseitig berechnet und über `#mp3-root` an den Client durchgereicht.

## Version 1.3.3 – 2026-08-27

### Verbesserungen
- Startet ein User das Overlay zum ersten Mal (noch keine gespeicherte Kategorie-Präferenz), landet er jetzt bei „Alle Medien" statt bei Kategorie „Kein Ordner" – letztere braucht ein eigenes Recht (`hasCategoryPerm(0)`), das viele auf einzelne Kategorien eingeschränkte User gar nicht haben.
- Der „Medienpool"-Link (Sidebar-Wurzel und Breadcrumb-Icon) ist jetzt nicht mehr anklickbar, wenn der User keinen Zugriff auf Kategorie „Kein Ordner" hat – vorher führte ein Klick dort zuverlässig in eine Sackgasse. Mit Tooltip, der erklärt, wo die eigenen Medien stattdessen zu finden sind.

### Bugfix
- Der automatische Ausweich-Mechanismus auf „Alle Medien" bei fehlendem Kategorie-Zugriff (siehe 1.3.2) blieb in einem Fall hängen: löste er selbst aus, während bereits eine Anfrage lief, blockierte die eigene `mediaLoading`-Sperre den Ausweich-Versuch stillschweigend – das Grid zeigte dauerhaft den Lade-Spinner, obwohl die Daten längst geladen waren.

## Version 1.3.2 – 2026-08-27

### Sicherheit
- Der klassische Medienpool gibt traditionell jedem Backend-User mit Basis-Medienrecht Leserecht auf **alle** Kategorien – nur Schreibaktionen (verschieben/löschen) prüfen die Kategorie-Rechte. Der zugehörige Fix im `api`-Addon ([PR #78](https://github.com/FriendsOfREDAXO/api/pull/78)) spiegelt dieses Verhalten deshalb per Default weiterhin exakt, um bestehende Aufrufer nicht zu brechen – die strengere Kategorie-Filterung, die MediaPlace will, ist dort jetzt ein expliziter Opt-in (`filter[permitted_only]=1`). MediaPlace schickt dieses Flag ab sofort bei jeder Medienlisten-Anfrage automatisch mit, damit die gewünschte strikte Filterung erhalten bleibt, sobald die installierte `api`-Version den Fix mitbringt und der eigene Fallback-Endpunkt nicht mehr greift.

## Version 1.3.1 – 2026-08-27

### Bugfix
- Sortierung ("Neueste zuerst" etc.) wirkte bisher nur auf die bereits geladene Seite: `buildMediaEndpoint()` schickte nie einen `sort`-Parameter mit, Server (sowohl das `api`-Addon als auch der eigene Fallback-Endpunkt) sortierten deshalb immer nach Dateiname aufsteigend. Bei mehr Dateien als `mediaPerPage` (Standard 30) konnte dadurch z. B. eine gerade erst hochgeladene Datei alphabetisch weit hinten liegen und in "Alle Medien" gar nicht erst mitgeladen werden, obwohl sie bei "Neueste zuerst" ganz oben stehen müsste. `buildMediaEndpoint()` schickt jetzt den tatsächlich gewählten Sortmodus als `sort=feld:richtung` mit (`ListHelper::parseSort()`-Syntax), beide Endpunkte respektieren ihn serverseitig.

## Version 1.3.0 – 2026-08-27

### Widget: Direkt-Upload, Mehrfachmarkierung, Ansichten
- Neu: `data-mp3-upload="true"` erlaubt Direkt-Upload per Drag&Drop/Klick direkt im Widget (ganzer Container ist Drop-Zone), inklusive Kategorie-Auswahl-Dialog vor dem Hochladen und optionaler Dateityp-Beschränkung (`data-mp3-types`, wie das native `accept`-Attribut).
- Neu: `data-mp3-max` begrenzt die Anzahl Dateien bei Mehrfachauswahl.
- Neu: Klick markiert genau ein Medium (hebt andere Markierungen auf), Cmd/Ctrl-Klick fügt es zu einer Mehrfachmarkierung hinzu/entfernt es daraus. Zwei getrennte Aktionen dafür: Papierkorb entfernt nur die markierten Dateien (deaktiviert ohne Markierung), separater roter „Leeren"-Button (REDAXOs `.btn-delete`-Konvention) entfernt nach Bestätigung alle Dateien.
- Neu: Kacheln/Liste-Umschalter, jetzt auch bei Einzelauswahl-Widgets verfügbar; `data-mp3-view="grid"|"list"` legt die Start-Ansicht eines einzelnen Widgets fest (ohne Angabe gilt die geteilte Nutzer-Präferenz), ein späterer Umschalter-Klick wirkt weiterhin global für alle Widgets der Seite.
- Neu: Auge-Button in der unteren Leiste jedes Grid-Elements (immer sichtbar, öffnet die Detailansicht in MediaPlace) zusätzlich zur bestehenden, nur bei Hover sichtbaren Lupe.

### YForm-Integration
- Neuer nativer YForm-Werttyp `mediaplace` (`lib/yform/value/yform_value_mediaplace.php`), modelliert nach `yform/lib/Field/value/be_media.php`: bindet das Widget direkt inkl. Direkt-Upload, Typ-/Mengen-Beschränkung und Start-Ansicht als Feld-Optionen. Registriert sich automatisch per Klassennamens-Konvention bei YForm und meldet referenzierte Dateien korrekt beim „Datei in Verwendung"-Check von REDAXO (`MEDIA_IS_IN_USE`), damit sie nicht versehentlich gelöscht werden können.

### Demo-Seite
- Überarbeitet: Einleitung, nummerierte Abschnitte (Overlay → Einzelmedium → Mehrfachauswahl → Direkt-Upload → API-Referenz), neue Attribut-Tabelle (inkl. `data-mp3-max`/`data-mp3-view`), YForm-Abschnitt zeigt jetzt zuerst den empfohlenen nativen Feldtyp (inkl. echtem, verifiziertem JSON-Export-Beispiel) und danach die ältere HTML-Feldtyp-Alternative.

## Version 1.2.2 – 2026-08-27

### Sicherheit
- Kategoriebaum und flache Kategorienliste (`rex_api_mediaplace_categories`) zeigten bisher **alle** Medienkategorien, unabhängig von den tatsächlichen Medienordner-Rechten des Users – ein auf einzelne Kategorien eingeschränkter Backend-User sah trotzdem den kompletten Kategoriebaum. Kategorienliste und Sidebar-Baum filtern jetzt per `MediaPermission::hasCategoryAccess()`; eine nicht erlaubte Kategorie wird übersprungen, ihre erlaubten Unterkategorien aber weiterhin angezeigt (an der nächsthöheren sichtbaren Stelle "hochgezogen"), analog zu `mediapool/lib/media_category_select.php::addCatOption()`.
- Die eigentliche Medienliste läuft über das `api`-Addon (`media`/`backend/media`), dessen `handleMediaList()` bislang ebenfalls keinerlei Kategorie-Rechte prüfte – ein eingeschränkter User bekam dort trotzdem sämtliche Dateien aller Kategorien zurück (separater Fix im `api`-Repository, [PR #78](https://github.com/FriendsOfREDAXO/api/pull/78), ab Version 1.3.1). Solange die installierte `api`-Version das noch nicht mitbringt, weicht MediaPlace automatisch auf einen eigenen, rechte-geprüften Fallback-Endpunkt aus (`rex_api_mediaplace_media_list`, Versionserkennung in `boot.php`) – kein manuelles Eingreifen nötig, die Weiche greift automatisch und entfällt von selbst, sobald `api` >=1.3.1 installiert ist.

## Version 1.2.1 – 2026-08-27

### Bugfix
- Upload-Verkleinerung (1.2.0): `canvas.toBlob()` fällt bei nicht unterstützten Ausgabeformaten (z. B. AVIF praktisch überall, WebP in älterem Safari) laut Spezifikation still auf PNG zurück. Der Code vertraute bisher blind darauf, dass die Ausgabe dem angeforderten Format entspricht, wodurch eine Datei mit falschem `.type`-Label (Originalformat), aber tatsächlich PNG-kodiertem Inhalt hochgeladen worden wäre. Jetzt wird der tatsächliche `blob.type` geprüft; bei einer Abweichung wird die Verkleinerung verworfen und die Originaldatei unverändert hochgeladen.

## Version 1.2.0 – 2026-08-27

### Bilder beim Upload verkleinern
- Neue Einstellung (Upload, standardmäßig aus): Bilder werden vor dem Hochladen im Browser per Canvas auf eine konfigurierbare maximale Breite/Höhe herunterskaliert – Seitenverhältnis bleibt erhalten, kleinere Bilder werden nie vergrößert, Dateiformat bleibt unverändert.
- GIFs (könnten animiert sein) und SVGs (kein Rasterbild) werden dabei nie angefasst.
- Funktioniert für alle Upload-Wege (Button, Drag & Drop, Ordner-Upload, Paste) sowie weiterhin mit dem Chunk-Upload für große Dateien; schlägt die Skalierung fehl, wird die Originaldatei hochgeladen.

## Version 1.1.1 – 2026-08-27

### Metadaten bearbeiten
- Umbenannt von „Nativ bearbeiten (Prototyp)" zu **„Metadaten bearbeiten"** – nicht mehr als experimentell/Prototyp gekennzeichnet, eigener „Experimentell"-Bereich in den Einstellungen entfernt und in „Funktionen" zusammengeführt.
- Klick auf ein klassisches `REX_MEDIA[n]`/`REX_MEDIALIST[n]`-Widget innerhalb des Canvas öffnet jetzt das eigene Grid zum Auswählen statt REDAXOs natives Popup – Canvas blendet sich kurz aus, nach der Auswahl (bzw. „Übernehmen" bei Mehrfachauswahl) geht es zurück zum Formular, ohne dass unsauber gespeicherte Eingaben verloren gehen.
- Speichern zeigt jetzt eine kurze Erfolgs-/Fehler-Rückmeldung im Button, statt kommentarlos ins Grid zu wechseln.
- Kompatibilität mit dem `metainfo_lang_fields`-Addon: dessen Sprachfelder werden jetzt korrekt im Canvas gerendert (siehe auch `metainfo_lang_fields` 1.0.8).

### Eigene Metadaten-Felder
- Neuer Schalter „Eigene Metadaten-Felder aktivieren" – **Opt-in, standardmäßig aus** (vorher standardmäßig an). Titel, System-Tags und Sammlungen sind davon unabhängig und bleiben immer verfügbar.
- Neuer Hinweis „Bitte ALT-Text hinterlegen" unter dem „Metadaten bearbeiten"-Button, wenn kein ALT-Text vorhanden ist. Eigenes ALT-Feld hat Vorrang, wenn eigene Metadaten aktiv sind, sonst zählt das klassische `med_alt`-Feld.
- Den vorherigen Übergangsmodus „Klassische Metainfo-Felder verlinken" (Link zur klassischen Medienpool-Bearbeitung) entfernt – durch „Metadaten bearbeiten" abgelöst.

### Bugfixes
- Checkbox-Felder lösten den Speichern-Button fälschlich sofort nach dem Laden aus (Dirty-Check verglich ein nie gesetztes Feld gegen `false`).

## Version 1.1.0 – 2026-08-27

### Echte Metainfo-Felder (Prototyp)
- Neuer, experimenteller Modus zum nativen Bearbeiten echter, über das REDAXO-Metainfo-Addon angelegter `med_*`-Felder direkt im MediaPlace-Detail-Panel: ein eigener Canvas rendert das Formular über REDAXOs eigenen `MEDIA_FORM_EDIT`-Erweiterungspunkt und speichert über `rex_media_service::updateMedia()` – inklusive Widgets wie dem klassischen Medienlisten-Feld, ohne eigene Nachbau-Logik.
- Eigener Einstellungen-Schalter (Standard: aus), unabhängig vom bestehenden „Klassische Metainfo-Felder verlinken“-Schalter; der API-Endpunkt verweigert Auslieferung/Speichern serverseitig, wenn der Schalter aus ist.
- „Nativ bearbeiten“-Button sitzt jetzt direkt unter dem Titelfeld, im gleichen Button-Stil wie der TinyMCE/CKEditor5-„Bearbeiten“-Button, mit dem Icon des klassischen Metainfo-Addons.
- Die schreibgeschützte „MediaPlace Metadaten“-Anzeige im klassischen Medienpool-Formular entfernt jetzt HTML-Tags aus TinyMCE/CKEditor5-Feldwerten, statt sie roh als Text anzuzeigen.

### Medien nach Tags/Sammlungen auslesen
- Neue Methoden in `SystemTagManager`: `getFilenamesForTag()`, `getFilenamesForCollection()`, `getTags()`/`getCollections()` (Katalog nach Sammlungen gefiltert) und `isCollectionTagName()` – für Entwickler, die Medien programmatisch nach Tag oder Sammlung auslesen wollen, ohne eigenes SQL zu schreiben.
- README dokumentiert diesen Anwendungsfall neu.

### Technik & Bugfixes
- Default-Config-Werte (`replace_classic_mediapool`, `disable_tagging`, …) kommen jetzt aus `package.yml` (`default_config:`), nicht mehr aus manuellem Code in `install.php`.
- Tastaturkürzel „F“ für Vollbild löste versehentlich aus, während man innerhalb von TinyMCE tippte (Iframe-Fokus wurde von der bisherigen Eingabefeld-Prüfung nicht erkannt).
- Klick auf klassische `REX_MEDIA[n]`/`REX_MEDIALIST[n]`-Widgets innerhalb des neuen Metainfo-Canvas öffnete versehentlich rekursiv den MediaPlace-Overlay statt des erwarteten klassischen Popups.
- Vereinheitlichtes Erscheinungsbild: Alle Canvas-Header (Editor, Fokuspunkt, Metainfo) teilen sich jetzt dieselben Styles, alle „Speichern“-Buttons sind einheitlich grün.
- Kompaktere Abstände in Kopfzeile und Filterleiste, damit Toolbar-Buttons und Tag-Filter auch bei der Standardgröße des Overlays ohne Umbruch passen.

## Version 1.0.0 – 2026-08-26

Erste Version von **MediaPlace**: ein vollständiger, moderner Medienpool für das REDAXO-Backend als Ersatz für den klassischen Medienpool.

### Sicherheit
- **Alle eigenen API-Endpunkte (`rex_api_mediaplace_*`) hatten `published = true` gesetzt** — das schaltet REDAXOs eigene, automatische Absicherung in `rex_api_function::handleCall()` (`rex::isBackend()` + `rex::getUser()`, laufen sonst VOR `execute()`) ab, nicht an. Da `rex-api-call` sowohl von `backend.php` als auch von `frontend.php` verarbeitet wird, waren die Endpunkte dadurch auch über die normale Frontend-URL erreichbar, nicht nur über den Backend-Controller. Die vorhandenen manuellen Prüfungen (`rex::getUser()`/`rex_backend_login::hasSession()`) blockierten zwar nicht angemeldete Anfragen zuverlässig, prüften aber nie `rex::isBackend()` — ein am Backend angemeldeter Nutzer hätte die Endpunkte also auch über die Frontend-Domain erreichen können. `published = true` in allen sechs Endpunkten entfernt (Basisklassen-Default ist bereits `false`); live verifiziert: authentifizierte Backend-Anfrage weiterhin 200, nicht authentifizierte Anfrage weiterhin 401, authentifizierte Anfrage über die Frontend-URL jetzt korrekt 403.

### Medienpool-Overlay
- Vollbild-Overlay (`MP3.open()`) mit Kategorie-Baum (inkl. Kategoriesuche, Verschieben, Anlegen/Umbenennen), Grid-, Listen- und Media-Wall-Ansicht, Kachelgröße per Slider.
- Serverseitige Suche über Titel, Dateiname, Originalname und JSON-Metadaten; Typ-Filter (Bilder/Videos/Audio/Dokumente/Sonstige), Tag-Filter, „Nur unbenutzte Medien“-Filter (eigenes granulares Recht `mediaplace[view_unused_media]`), 8 Sortieroptionen, Pagination mit „Mehr laden“.
- Upload per Drag & Drop, Upload-Button oder Cmd/Ctrl+V-Paste; Kategorie erstellen/umbenennen direkt in der Sidebar; Breadcrumb-Navigation.
- Responsive Compact-Mode (Offcanvas-Sidebar, Bottom-Sheet Detail-Panel, mobiles Filter-Dropdown) für schmale Modal-Breiten; Dark-Mode-Toggle unabhängig vom REDAXO-Theme.

### Detail-Panel & Metadaten
- Strukturierte JSON-Metadaten (`med_json_data`) mit eigenem Feld-Editor (Text/Textarea/Checkbox/Select/TinyMCE/CKEditor5/Alt-Text/Medienverknüpfung), Mehrsprachigkeit, dekorativem ALT-Text-Modus. TinyMCE und CKEditor5 teilen sich denselben Vollbild-Editor-Canvas (Bedienung identisch, nur die Engine dahinter unterscheidet sich) und binden das jeweils installierte Addon direkt über dessen öffentliche JS-API ein.
- Neue Feldtypen **Checkbox** (einfacher Ja/Nein-Schalter) und **Select** (Dropdown oder Mehrfachauswahl): Auswahlmöglichkeiten entweder als Zeilenliste (`Wert|Beschriftung`) oder als SQL-`SELECT`-Abfrage (Konvention wie bei REDAXOs klassischem Metainfo-Addon) – wird bei jedem Laden neu aufgelöst, damit SQL-basierte Auswahllisten stets aktuell bleiben.
- Erweiterungspunkt `MEDIAPLACE_WIDGET_TYPES` für eigene Feldtypen aus Drittaddons (Registry-Pattern, kein Zwang zur Vererbung).
- System-Tags mit Autocomplete, Datei-Kategorie direkt im Panel wechselbar, Medien tauschen (gleicher Dateiname/kompatible Endung), Download, PDF-Öffnen-Button.
- Echte, über das REDAXO-Metainfo-Addon angelegte `med_*`-Felder (z.B. Copyright): Übergangslösung, standardmäßig deaktiviert (Einstellungen → „Klassische Metainfo-Felder verlinken“). Zeigt bei Aktivierung den bestehenden „Alte Metadaten“-Bereich (read-only) plus einen Link, der die Datei direkt zum Bearbeiten in der klassischen Medienpool-Detailseite öffnet — natives Editieren dieser Felder in MediaPlace selbst folgt in einer späteren Version.
- Fokuspunkt-Editor: nur sichtbar bei installiertem [focuspoint](https://github.com/FriendsOfREDAXO/focuspoint)-Addon und mindestens einem Media-Manager-Typ mit Fokuspunkt-Effekt. Vollflächiger Editor mit Klick-zum-Setzen und Live-Zuschnitt-Vorschau; Speicherung bleibt im klassischen Metainfo-Feld (`med_focuspoint`), damit andere Konsumenten (Templates, Effekte) unverändert funktionieren.

### Sammlungen & Mehrfachauswahl
- Sammlungen (Collections) als eigener Modus: anlegen/umbenennen/löschen, Zuordnung per Lesezeichen-Button oder Drag & Drop (inkl. Batch-Drag mit Cmd/Ctrl+Klick-Markierung).
- Multi-Select im Picker-Modus (Auswahl übernehmen) sowie unabhängig davon eine Cmd/Ctrl+Klick-Mehrfachauswahl im Normalmodus mit Batch-Löschen (überspringt automatisch noch verwendete Dateien) und „Alle auswählen“.
- Beide Bereiche (Tagging, Sammlungen) einzeln über die Einstellungsseite deaktivierbar.

### Input-Widget & klassische Integration
- `<input class="mp3-widget">` wird automatisch zu einem visuellen Medien-Picker mit Vorschau, Hinzufügen/Entfernen, Drag & Drop-Sortierung.
- Jedes ausgewählte Medium bekommt einen „Details ansehen“-Button (Lupe), der den Overlay direkt im Detail-Panel dieses Mediums öffnet (Browse-only, ändert die Auswahl nicht) — nutzt `MP3.openFile()`.
- Der klassische „Medienpool“-Menüpunkt sowie die `REX_MEDIA[n]`/`REX_MEDIALIST[n]`-Widgets öffnen wahlweise direkt den neuen Overlay statt der alten Seiten/Popups (abschaltbar in den Einstellungen).

### i18n-Infrastruktur
- `lang/de_de.lang` ist die **einzige** Quelle der Wahrheit für übersetzbare Texte — auch für JS-seitige Strings, keine zweite, separat gepflegte Übersetzungstabelle. `boot.php` löst beim Seitenaufbau jeden dort vorhandenen `mediaplace_*`-Schlüssel über `rex_i18n::msg()` für die aktive Locale auf (inkl. deren eingebauter Fallback-Kette) und embedded das Ergebnis als JSON (`<script type="application/json" id="mp3-i18n-data">`). Neue Datei `assets/mediapool3-i18n.js` liest dieses JSON und stellt `MP3Core.i18n.t(key, vars)` bereit — reines PHP-gerendertes JSON, keine Abhängigkeit von REDAXO-Backend-JS-Globals, funktioniert deshalb unverändert auch im Frontend, sobald MediaPlace dort eingesetzt wird.
- Das komplette Addon ist migriert: Overlay-Kern (`mediapool3.js`), Input-Widget (`mediapool3_widget.js`), alle 17 PHP-Fragmente des Detail-Panels (`fragments/mediaplace/*.php`), sowie die Seiten `settings.php`, `metainfo_fields.php` und `demo.php` nutzen durchgängig `t()`/`rex_i18n::msg()` statt hartkodiertem Text (262 Schlüssel in `lang/de_de.lang`).
- `lang/en_gb.lang` (Englisch) ist vollständig gepflegt (gleiche 262 Schlüssel). Weitere Sprachen fallen dank REDAXOs Locale-Fallback (`lang_fallback`) auf Englisch bzw. Deutsch zurück statt einen kaputten Platzhalter zu zeigen.

### Technik
- Eigene `rex_api_function`-Endpunkte (`mediaplace_categories`, `mediaplace_json_metainfo`, `mediaplace_tags`, `mediaplace_unused`, `mediaplace_focuspoint`, `mediaplace_schema`) mit zentraler Rechteprüfung (`MediaPermission`), die REDAXOs Medien-Berechtigungen spiegelt.
- Serverseitig gerenderte Fragmente für Kategoriebaum und Detail-Panel; JS-Kern in mehrere Dateien aufgeteilt (API-Schicht, generische Helfer, Hauptmodul).
- Vanilla JS/CSS ohne Build-Step oder Framework-Abhängigkeit; vollständiges Dark-Mode-Theming über CSS Custom Properties.
