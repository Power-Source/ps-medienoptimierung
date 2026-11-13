=== PS Medienoptimierung ===
Plugin Name: Medienoptimierung
Version: 1.0.2
Author: PSOURCE
Author URI: https://psource.eimen.net/
Contributors: DerNerd
Tags: bilder, videos, optimierung, komprimierung, performance, medienbibliothek, wordpress
Requires at least: 6.0
Requires CP: 2.7
Tested up to: 7.1.4
ClassicPress: 3.7.4
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optimiere deine Bilder und passe Video-Codecs lokal für Mobilgeräte an, ohne externe API-Abhängigkeiten.

== Beschreibung ==

<strong>Medienoptimierung hilft dir dabei, Bilder zu optimieren und MP4-Videos für Mobilgeräte kompatibel zu konvertieren.</strong>

Du kannst Bilder in der Medienbibliothek einzeln oder gesammelt verarbeiten, automatische Optimierung beim Upload aktivieren und auf Wunsch auch Bilder außerhalb des Upload-Ordners optimieren.

Die Optimierung läuft lokal in deiner Umgebung. So behältst du volle Kontrolle über Daten, Verarbeitung und Performance.

= Funktionen =

* Lokale Bildoptimierung für JPG, PNG und GIF
* Automatische Optimierung beim Upload
* Bulk-Optimierung für bestehende Medien
* Optionales erneutes Optimieren (Re-Check)
* Optionales Skalieren großer Originalbilder
* Optionales Beibehalten von EXIF-Daten
* Ordner-Optimierung außerhalb der Medienbibliothek
* Statistiken zu Einsparungen und optimierten Bildern
* Unterstützung für Multisite-Setups
* Integrationen für NextGEN und S3-Workflows
* Eigene Videooptionen innerhalb der gemeinsamen Medienoptimierung
* Lokale MP4-Konvertierung zu H.264 mit 8 Bit, 4:2:0 und AAC-Ton für Mobilgeräte
* Video-Bulk-Optimierung mit Fortschritt und erneuter Prüfung bestehender Videos
* Übersicht über kompatible, offene, optimierte und verkleinerte Videos
* Videokomprimierung und optionale Begrenzung der Auflösung
* Größen vorher/nachher und gesamte Einsparung in Speicherplatz und Prozent
* Optionale automatische Videooptimierung neuer Uploads über WP-Cron
* Optionale Originalsicherung für Videos, standardmäßig deaktiviert
* Erkennung von FFmpeg oder GStreamer ohne externe Verarbeitung

= Videoeinstellungen und Sicherheit =

Unter Medien -> Medienoptimierung findest du Bild- und Videooptimierung auf einer Seite. Beide haben eigene Optionen, werden aber mit demselben Speichern-Button verwaltet. Die automatische Videooptimierung und die Originalsicherung sind standardmäßig ausgeschaltet.

Auf Multisite findest du die Videooptionen ebenfalls in der bestehenden Medienoptimierung der Netzwerkverwaltung. Der vorhandene Schalter für zentrale Netzwerkeinstellungen gilt für Bilder und Videos gemeinsam. Ist er aktiv, übernehmen alle Unterwebsites die zentralen Videoeinstellungen und können sie lokal nicht überschreiben. Ist er aus, verwaltet jede Website ihre eigenen Bild- und Videoeinstellungen. Die Video-Sammelverarbeitung bleibt wie die Bild-Sammelverarbeitung auf derselben Medienoptimierungsseite der jeweiligen Website; es gibt keinen zusätzlichen Menüpunkt.

Über „Videos erneut prüfen“ erkennst du bereits handykompatible Videos und den aktuellen Optimierungsbedarf, ohne Dateien zu verändern. Die Video-Bulk-Optimierung verarbeitet die offenen Videos nacheinander. Fortschritt, Fehler, Dateigrößen vorher/nachher und die gesamte Einsparung werden wie bei der Bildoptimierung angezeigt. Bereits erledigte Videos werden mit denselben Einstellungen nicht immer wieder komprimiert.

Unter „Ergebnisse und Fehler“ kannst du mit „Protokoll leeren“ die angezeigte Liste dauerhaft leeren. Optimierungsstände, Einsparungen und offene Fehler in der Statistik bleiben erhalten. Neue Prüfergebnisse und Fehlermeldungen erscheinen anschließend wieder in der Liste.

Bei einer Konvertierung entstehen MP4-Videos mit H.264, 8-Bit-4:2:0, 30 Bildern pro Sekunde und bei vorhandenem Ton AAC-Stereo. Stumme Videos bleiben stumm. Die Videokomprimierung ist standardmäßig aktiv. Der Qualitätswert reicht von 18 bis 30, Standard ist 23: Kleinere Werte bedeuten höhere Qualität und meist größere Dateien.

Mit „Videogröße begrenzen“ kannst du zusätzlich eine maximale Breite und Höhe festlegen, beispielsweise 1280 × 720 Pixel. Diese Option ist standardmäßig aus. Große Videos werden proportional verkleinert; kleine Videos werden nicht hochskaliert. Die Statistik zeigt auch die Anzahl verkleinerter Videos und deren Größenänderung.

Ist ein Video bereits kompatibel und wird nicht verkleinert, bleibt das Original erhalten, falls die Komprimierung keine kleinere Datei ergibt. Muss dagegen der Codec angepasst oder die Auflösung begrenzt werden, kann die neue Datei auch größer werden. Solcher Mehrbedarf wird ausdrücklich angezeigt und nicht als Einsparung gezählt.

Verarbeitet werden lokale Dateien mit .mp4-Endung im WordPress-Upload-Verzeichnis. URLs und Dateinamen bleiben erhalten. Andere Endungen wie MOV, WebM oder AVI sowie ausgelagerte Videos werden mit Hinweis unverändert gelassen, damit bestehende Links und die Auslieferung nicht beschädigt werden.

Das Original wird erst ersetzt, nachdem Format, Laufzeit, Ton und vollständige Lesbarkeit der Ausgabe geprüft wurden. Bei Fehlern bleibt es unverändert. Eine aktivierte Originalsicherung bewahrt zusätzlich eine Kopie mit dem Dateizusatz .ps-original auf. Ohne Sicherung ist eine erfolgreich ersetzte Datei nicht wiederherstellbar. Vorhandene Sicherungen bleiben beim Abschalten der Option oder bei der Plugin-Deinstallation erhalten; beim Löschen des Mediums werden sie mit entfernt.

Lass die Seite während der Bulk-Optimierung geöffnet. Anhalten beendet sie nach dem laufenden Video. Die Uploadautomatik läuft unabhängig davon im Hintergrund über WordPress-Aufgaben. Bei langen Videos oder verzögerter Verarbeitung bitte den Hoster nach geeigneten Zeitlimits und regelmäßig ausgeführten WordPress-Aufgaben fragen. Temporär wird zusätzlicher freier Speicher benötigt. Nach erfolgreicher Konvertierung gegebenenfalls vorhandene Browser- oder CDN-Caches leeren.

= Servervoraussetzungen für Videos =

Der Server benötigt FFmpeg mit ffprobe oder GStreamer, jeweils mit H.264- und AAC-Unterstützung. Die Programme müssen für PHP verfügbar sein. Der Encoderstatus wird in den Einstellungen angezeigt. Ist kein geeigneter Encoder vorhanden, erscheint eine verständliche Meldung – bitte dann den Hoster um Einrichtung bitten. Die Bildoptimierung funktioniert unabhängig davon weiter.

= Für wen ist das Plugin? =

Medienoptimierung ist ideal, wenn du:

* deine Website schneller machen willst,
* Speicherplatz sparen möchtest,
* Bilder zentral in WordPress verwalten willst,
* und dabei keine externen Smush-Dienste nutzen möchtest.

== Häufige Fragen ==

= Werden meine Originalbilder überschrieben? =

Standardmäßig werden die von WordPress erzeugten Bildgrößen optimiert. Je nach Einstellung kannst du zusätzlich auch Originalbilder verarbeiten oder Sicherungen nutzen.

= Warum sehe ich nicht immer einen sichtbaren Qualitätsunterschied? =

Das ist normal. Die Optimierung entfernt vor allem unnötige Daten und reduziert Dateigrößen, ohne die sichtbare Bildqualität stark zu verändern.

= Kann ich auch Bilder außerhalb der Medienbibliothek optimieren? =

Ja. Über die Ordner-Optimierung kannst du zusätzliche Verzeichnisse auswählen und dort enthaltene Bilder ebenfalls verarbeiten.

= Unterstützt das Plugin Multisite? =

Ja. Es unterstützt Multisite-Installationen inklusive zentraler Steuerung über Netzwerkeinstellungen.

== Screenshots ==

1. Bulk-Optimierung in der Medienbibliothek
2. Einstellungen für automatische Optimierung und Bildskalierung
3. Ordner-Optimierung außerhalb des Upload-Ordners
4. Statistikansicht mit Einsparungen und Gesamtwerten

== Installation ==

1. Lade das Plugin nach `/wp-content/plugins/ps-medienoptimierung/` hoch.
2. Aktiviere es im WordPress-Backend unter `Plugins`.
3. Öffne `Medien -> Medienoptimierung`.
4. Passe die Einstellungen an und starte bei Bedarf die Bulk-Optimierung.
5. Prüfe auf derselben Seite im Videobereich den Encoderstatus und aktiviere auf Wunsch die Uploadautomatik und Originalsicherung. Im Netzwerk stehen die Videooptionen ebenfalls in der vorhandenen Medienoptimierung.

== Changelog ==

= 1.0.2 =
* Lokale Videooptimierung in der vorhandenen Oberfläche, einschließlich Netzwerkeinstellungen.
* Video-Bulk-Optimierung, erneute Prüfung, Komprimierung, optionale Größenbegrenzung und Einsparungsstatistiken.
* MP4/H.264 in 8-Bit-4:2:0 und AAC für Mobilgeräte mit Prüfung vor dem Ersetzen und optionaler Originalsicherung.

= 1.0.1 =
* Verbesserte Deutsche Ausgabe

= 1.0.0 =

* Initiale Veröffentlichung als Medienoptimierung
