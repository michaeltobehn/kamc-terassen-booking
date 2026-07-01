# KAMC · Lounge oben — Dachterrassen-Buchung

Web-Buchungstool fuer die Clubterrasse des KAMC e.V. (Rheinauhafen Koeln).
Mitglieder buchen einen von **zwei festen Slots pro Tag** (Tag / Abend); die
Hafenmeisterei bestaetigt und nimmt nach dem Termin ab.

## Tech-Stack
PHP 8.x + PDO · MySQL/MariaDB · Tailwind CSS · Alpine.js · Strato Shared Hosting

## Projektstruktur
    public/            Webroot (index.php, assets, .htaccess)
    src/               Logik: db.php (PDO+UTC), helpers.php
    migrations/        Versionierte SQL-Migrationen (via phpMyAdmin importieren)
    cron/              expire.php (Auto-Expiry, Strato Cron)
    build/             Tailwind-Input
    docs/              Briefing + E-Mail-Vorlagen
    config.php         NICHT im Repo (Secrets) — aus config.sample.php erstellen

## Setup (lokal)
1. `cp config.sample.php config.php` und Werte eintragen.
2. `migrations/001_initial_schema.sql` in leere DB importieren (phpMyAdmin).
3. `npm install && npm run build:css`
4. `public/` als Webroot; Aufruf zeigt "Setup OK".

## Deploy (Strato)
- **Automatisch:** Push auf `main` -> GitHub Actions baut CSS und deployt per FTP.
  Dafuer in *Settings -> Secrets and variables -> Actions* anlegen:
  `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`. `server-dir` in
  `.github/workflows/deploy.yml` auf den Strato-Webroot setzen.
- **Manuell:** `npm run build:css`, dann die noetigen Ordner per FTP hochladen.
  `config.php` bleibt auf dem Server und wird **nie** ueberschrieben.

## Wichtig
- Alle Zeitstempel in der DB sind **UTC** (`SET time_zone='+00:00'`), Anzeige in Europe/Berlin.
- Buchungslogik: 2 feste Slots (Tag = Oeffnung-18:00, Abend = 18:00-Schliessung),
  je 1x pro Tag. Doppelbuchungs-Schutz per `GET_LOCK` + "(Datum,Slot) frei?"-Check.
- Details siehe `docs/Briefing-Dachterrassenbuchung.md`.
