# PHP 8+ Kompatibilitaetscheck (2026-03-18)

## Kontext
- Laufzeit lokal: `PHP 8.4.18` (CLI)
- Scope: statischer Schnellcheck im Plugin `ps-medienoptimierung`

## Durchgefuehrte Checks
1. Vollstaendiger Syntax-Lint auf alle PHP-Dateien (`php -l`).
2. Pattern-Scan auf typische PHP8-Breaker:
- `create_function()`
- `each()`
- `preg_replace(.../e...)`
- alte String-Offset-Syntax mit geschweiften Klammern
3. Spot-Check auf bekannte Legacy-Risiken in den zuletzt geaenderten Dateien.

## Ergebnis
- Keine Syntax-Fehler unter PHP 8.4.
- Keine Treffer fuer die oben genannten harten Inkompatibilitaeten.
- Aktuell keine akuten Showstopper fuer PHP 8+ aus statischer Sicht.

## Restrisiken (nicht vollautomatisch nachweisbar)
- Runtime-Warnungen/Deprecations (z. B. dynamische Properties) koennen erst bei realer Ausfuehrung aller Admin-Pfade sichtbar werden.
- Drittanbieter-Integrationen (NextGEN, Offload S3, Dashboard-Umfelder) brauchen einen Laufzeittest im echten WP-Kontext.

## Empfohlene Verifikation (naechster Schritt)
1. Plugin in Staging mit `WP_DEBUG=true` und `WP_DEBUG_LOG=true` testen.
2. Folgende Flows manuell durchklicken:
- Bulk Smush
- Directory Smush
- NextGEN Bulk (falls aktiv)
- S3-Hinweise (falls Offload aktiv)
3. `debug.log` gezielt auf `Deprecated`, `Warning`, `Fatal` pruefen.
4. Optional CI: PHPCS + PHPCompatibility als Gate fuer neue Commits.
