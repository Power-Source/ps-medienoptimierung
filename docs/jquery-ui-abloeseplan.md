# Plan: jQuery UI / jQueryFileTree abloesen

## Ziel
Abhaengigkeiten auf alte jQuery-UI-nahe Komponenten entfernen und die Verzeichnisauswahl auf moderne, wartbare JS-Loesungen (bevorzugt vanilla) umstellen.

## Ist-Stand (heute)
- Aktive Datei-Baum-Abhaengigkeit:
- `assets/js/jQueryFileTree.js`
- `assets/css/jQueryFileTree.min.css`
- Eingebunden ueber:
- `lib/class-wp-smush-admin.php` (`jqft-js`, `jqft-css`)
- Verwendet in:
- `lib/class-wp-smush-dir.php` (Markup `ul.jqueryFileTree`)
- `assets/js/ps-medienoptimierung-admin.js` (Selektor `.jqueryFileTree .selected a`)
- Styling-Erweiterungen in:
- `assets/css/ps-medienoptimierung-admin.css` (Block um Zeile ~2305+)
- `assets/css/jquery-ui.css` und `assets/js/ui.js` wirken derzeit nicht aktiv eingebunden und koennen separat verifiziert/entfernt werden.

## Migrationsstrategie (phasenweise)

### Phase 1: Inventar + Sicherheitsnetz
1. Feature-Flag einfuehren, z. B. `use_modern_tree` (default `false`).
2. Snapshot-Tests fuer Directory-Screen (HTML-Struktur + Auswahlverhalten).
3. Event-Map dokumentieren (expand/collapse/select + rel-path-uebergabe).

### Phase 2: Neuer Tree (ohne jQuery Plugin)
1. Neue Datei `assets/js/ps-medienoptimierung-tree.js` erstellen (vanilla).
2. API-kompatible Schnittstelle anbieten:
- Input: Startpfad + Ajax-Endpunkt
- Output: selected path (wie bisher aus `rel`)
3. Accessibility einbauen:
- Keyboard-Navigation
- `aria-expanded`, `aria-selected`
- Focus-Management

### Phase 3: PHP/Enqueue umstellen
1. In `lib/class-wp-smush-admin.php` neue Assets registrieren/enqueueen.
2. `jqft-js`/`jqft-css` nur noch hinter Legacy-Flag laden.
3. In `lib/class-wp-smush-dir.php` Markup auf neues Datenattribut-Modell anpassen, z. B. `data-path` statt plugin-spezifischer Klassenlogik.

### Phase 4: JS-Integration abschliessen
1. In `assets/js/ps-medienoptimierung-admin.js` Selektorlogik von `.jqueryFileTree` auf neue Tree-API migrieren.
2. Rueckwaertskompatibilitaet waehrend Uebergang:
- Wenn neuer Tree nicht initialisiert werden kann -> sauberer Fallback.

### Phase 5: CSS-Aufraeumen
1. Neues CSS fuer Tree-Komponente in `assets/css/ps-medienoptimierung-admin.css` kapseln.
2. Alte `jqueryFileTree`-Spezifischen Selektoren entfernen.
3. `assets/css/jQueryFileTree.min.css` entfernen, sobald Legacy-Flag entfaellt.

### Phase 6: Legacy entfernen
1. `assets/js/jQueryFileTree.js` entfernen.
2. `jqft-js`/`jqft-css` Handles entfernen.
3. Optional: nicht verwendete `assets/js/ui.js` und `assets/css/jquery-ui.css` endgueltig loeschen (nach finaler Verifikation).

## Akzeptanzkriterien
- Directory-Auswahl funktioniert funktional identisch (expand/select/path).
- Keine JS-Fehler in WP-Admin-Konsole.
- Kein Enqueue mehr fuer `jQueryFileTree` im Standardpfad.
- Bulk/Directory Smush unveraendert lauffaehig.

## Risiko / Aufwand
- Risiko: mittel (UI-Interaktion + Ajax-Baum).
- Aufwand: mittel (ca. 2-4 Umsetzungstage inkl. QA je nach Testtiefe).
