# PS Medienoptimierung

**Deutsch** | [English](README.en.md)

[![Version](https://img.shields.io/badge/Version-1.0.2-2271b1?style=flat-square)](readme.txt)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-bis%207.1.4-21759b?style=flat-square&logo=wordpress&logoColor=white)
![ClassicPress](https://img.shields.io/badge/ClassicPress-2.7.4-03768e?style=flat-square)
[![Lizenz](https://img.shields.io/badge/Lizenz-GPL--3.0--or--later-2ea44f?style=flat-square)](https://www.gnu.org/licenses/gpl-3.0.html)

### Weniger Ballast. Mehr Tempo. Bilder und Videos an einem Ort.

Optimiere deine Bilder, verkleinere große Videos und mache MP4-Dateien fit für Mobilgeräte – direkt in deiner WordPress- oder ClassicPress-Medienverwaltung.

**Lokal auf deinem Server. Ohne externen Optimierungsdienst. Ohne zusätzliche Einstellungsseiten.**

---

## Das steckt drin

| Für deine Bilder | Für deine Videos |
| --- | --- |
| JPG, PNG und GIF optimieren | MP4-Videos für Mobilgeräte aufbereiten |
| Neue Uploads automatisch optimieren | Neue Uploads auf Wunsch automatisch konvertieren |
| Vorhandene Bilder gesammelt verarbeiten | Vorhandene Videos gesammelt optimieren |
| Große Originalbilder optional skalieren | Auflösung optional proportional begrenzen |
| EXIF-Daten auf Wunsch behalten | Dateigröße durch Komprimierung reduzieren |
| Zusätzliche Bildordner optimieren | Passende Codecs erkennen und erneut prüfen |

Die Übersicht zeigt dir, was bereits erledigt ist, wo noch Arbeit wartet und wie viel Speicherplatz die Optimierung gebracht hat. Bei Videos siehst du auch Fehler und mögliche Vergrößerungen.

## In wenigen Schritten loslegen

1. Installiere und aktiviere **PS Medienoptimierung** über deine Pluginverwaltung.
2. Öffne **Medien → Medienoptimierung**.
3. Passe die Bild- und Videooptionen im gemeinsamen Einstellungsformular an und speichere sie.
4. Starte die gewünschte **Bulk-Optimierung**, um vorhandene Medien gesammelt zu verarbeiten.

Für neue Uploads kannst du die Automatik für Bilder und Videos unabhängig voneinander einstellen.

> **Vor der ersten größeren Optimierung:** Eine aktuelle Sicherung deiner Medien ist empfehlenswert. Die Videokonvertierung ist verlustbehaftet und lässt sich ohne Originalsicherung nicht rückgängig machen.

## Bilder: kleiner speichern, schneller laden

Optimiere einzelne Bilder oder räume deine bestehende Medienbibliothek gesammelt auf. Auf Wunsch verarbeitet das Plugin neue Uploads automatisch und begrenzt die Abmessungen großer Originalbilder.

Du entscheidest außerdem, ob EXIF-Daten erhalten bleiben und ob Originalbilder zusätzlich optimiert oder gesichert werden sollen. Über die **Ordner-Optimierung** lassen sich auch Bilder außerhalb der normalen Medienbibliothek einbeziehen.

## Videos: passende Codecs statt böser Überraschungen

Eine MP4-Endung allein sagt noch nicht, ob ein Video auf einem Handy gut abgespielt werden kann. Entscheidend ist auch, wie Bild und Ton darin gespeichert sind.

Bei einer Konvertierung verwendet PS Medienoptimierung:

- **MP4 mit H.264** für das Bild.
- **8 Bit und 4:2:0** für breite Gerätekompatibilität.
- **AAC-Stereo** bei vorhandener Audiospur.
- **30 Bilder pro Sekunde** für die Ausgabe.

Stumme Videos bleiben stumm. Bestehende Video-URLs und Dateinamen bleiben erhalten.

### Erst prüfen, dann optimieren

Mit **„Videos erneut prüfen“** erkennst du bereits kompatible Videos und den aktuellen Optimierungsbedarf, ohne die Dateien zu verändern.

Die **Video-Bulk-Optimierung** verarbeitet anschließend die offenen Videos nacheinander. Du siehst Fortschritt, Ergebnisse, Fehler und die Dateigrößen vorher und nachher. Bereits mit denselben Einstellungen erledigte Videos werden nicht bei jedem Durchlauf erneut komprimiert.

Lass die Seite während der Sammelverarbeitung geöffnet. Mit **„Nach aktuellem Video anhalten“** stoppst du den Durchlauf, sobald das laufende Video fertig verarbeitet ist.

### Du bestimmst Qualität und Größe

| Videooption | Voreinstellung | Was sie bewirkt |
| --- | --- | --- |
| Automatische Videooptimierung | Aus | Verarbeitet neue Video-Uploads im Hintergrund. |
| Videokomprimierung | An | Versucht auch bei bereits kompatiblen Videos, die Dateigröße zu reduzieren. |
| Videoqualität | 23 | Kleinere Werte bedeuten höhere Qualität und meist größere Dateien. Einstellbar von 18 bis 30. |
| Videogröße begrenzen | Aus | Verkleinert große Videos proportional auf deine maximale Breite und Höhe. |
| Originalsicherung | Aus | Bewahrt auf Wunsch eine zusätzliche Kopie des ursprünglichen Videos auf. |

Eine Größenbegrenzung auf beispielsweise **1280 × 720 Pixel** kann bei großen Videos zusätzlich Speicher und Datenvolumen sparen. Kleine Videos werden nicht hochskaliert.

**Wichtig:** Eine kleinere Datei ist nicht immer möglich. Ist das Video bereits kompatibel und muss nicht skaliert werden, bleibt das Original erhalten, wenn die Komprimierung keinen Größenvorteil bringt. Eine notwendige Codec-Anpassung oder Skalierung kann dagegen auch eine größere Datei ergeben – diesen Mehrbedarf zeigt das Plugin an.

### Erst geprüft, dann ersetzt

Die neue Videodatei wird zunächst separat erstellt und auf Format, Laufzeit, vorhandenen Ton und vollständige Lesbarkeit geprüft. Erst danach wird sie anstelle des Originals eingesetzt.

Die optionale Originalsicherung ist **standardmäßig ausgeschaltet**, um Speicherplatz zu sparen. Wenn du sie aktivierst, bleibt die erste Originaldatei zusätzlich erhalten. Ohne Sicherung lässt sich eine erfolgreich ersetzte Datei nicht wiederherstellen.

## Alles im Blick

Im Videobereich findest du:

- Die Anzahl der **handykompatiblen Videos**.
- Noch offene und ungeprüfte Videos.
- Bereits optimierte und verkleinerte Videos.
- Fehler und nicht unterstützte Dateien.
- **Einsparungen in Speicherplatz und Prozent** sowie die Größen vorher und nachher.

Unter **„Ergebnisse und Fehler“ → „Protokoll leeren“** kannst du die angezeigte Ergebnisliste dauerhaft leeren. Deine Optimierungsstände, Einsparungen und offenen Fehler in der Statistik bleiben erhalten. Neue Meldungen erscheinen anschließend wieder.

## Ein gemeinsamer Platz – auch im Netzwerk

Bild- und Videooptionen findest du auf derselben Medienoptimierungsseite und speicherst sie mit demselben Button.

In einer Multisite-Installation kannst du die Einstellungen gemeinsam über die Netzwerkverwaltung vorgeben. Bei aktivierter zentraler Steuerung übernehmen die Unterwebsites diese Werte. Andernfalls verwaltet jede Website ihre eigenen Einstellungen.

Die Sammelverarbeitung bleibt auf der jeweiligen Website und verarbeitet deren Medien.

## Deutsch oder Englisch

Die Oberfläche folgt der Sprache deiner Website beziehungsweise deines Benutzerprofils. Neben Deutsch wird Englisch (USA und Großbritannien) unterstützt – auch für Einstellungen, Meldungen und die Videoverarbeitung.

Die Website-Sprache wählst du unter **Einstellungen → Allgemein**. Wenn du nur deine eigene Verwaltungsoberfläche umstellen möchtest, ändere die Sprache unter **Benutzer → Profil**. Einen zusätzlichen Sprachschalter im Plugin brauchst du nicht.

## Was dein Hosting können muss

Für Bilder verwendet das Plugin die Bildverarbeitung deines Servers. Für Videos muss **FFmpeg mit ffprobe oder GStreamer** mit passender H.264- und AAC-Unterstützung verfügbar sein.

Du musst das nicht selbst herausfinden: Der Videobereich zeigt den erkannten Encoder oder einen verständlichen Hinweis an. Fehlt die Unterstützung, bitte deinen Hoster um Einrichtung. **Die Bildoptimierung funktioniert unabhängig davon weiter.**

FFmpeg wird nicht mitgeliefert. Auch für die lokale Verarbeitung braucht dein Server ausreichend freien Speicher und Zeit für größere Videos.

## Gut zu wissen

**Welche Videos werden verarbeitet?**  
Aktuell lokale Dateien mit **.mp4-Endung** im Upload-Verzeichnis deiner Website. MOV, WebM, AVI und ausgelagerte Videos, etwa auf S3, bleiben mit einem Hinweis unverändert.

**Muss die Seite bei automatischen Uploads geöffnet bleiben?**  
Nein. Die Uploadautomatik nutzt die Hintergrundaufgaben deiner Website. Bei sehr wenigen Seitenaufrufen kann sich der Start verzögern; dein Hoster kann eine regelmäßige Ausführung einrichten.

**Warum sehe ich nach einer Konvertierung noch die alte Version?**  
Weil die Video-URL erhalten bleibt, kann noch eine ältere Kopie im Browser- oder Website-Cache liegen. Leere gegebenenfalls auch deinen CDN-Cache.

**Wird eine Originalsicherung beim Abschalten der Option gelöscht?**  
Nein. Vorhandene Sicherungen bleiben erhalten. Beim Löschen des zugehörigen Mediums wird auch seine registrierte Videosicherung entfernt.

---

**PS Medienoptimierung** · Ein Plugin von [PSOURCE](https://psource.eimen.net/psource/ps-medienoptimierung/)
