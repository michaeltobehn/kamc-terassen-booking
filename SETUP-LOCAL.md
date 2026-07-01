# Lokal starten (Laptop)

Schnellstart für eine eigenständige Demo — **kein Netzwerk zum Mac mini nötig**.
Voraussetzung: **MAMP** (bringt PHP 8 + MySQL mit). Kein Node/npm nötig — das
CSS (`public/assets/style.css`) ist bereits gebaut und im Repo.

## Schnellster Weg (MAMP, macOS)

1. Repo klonen:
   ```bash
   git clone https://github.com/michaeltobehn/kamc-terassen-booking.git
   cd kamc-terassen-booking
   ```
2. **`setup-local.command` doppelklicken.**
   Das Skript: startet MAMP-MySQL, legt die DB `kamc_lounge` an, importiert die
   Demo-Daten (`db/demo-dump.sql`), erstellt `config.php` und startet den Server.
3. Browser öffnet automatisch: **http://localhost:8010**

> Falls Doppelklick blockiert wird: Rechtsklick → *Öffnen* (einmalig bestätigen),
> oder im Terminal: `bash setup-local.command`.

## Logins (Passwort überall `kamc`)
- `admin@kamc.dev` (Vorstand) · `hafen@kamc.dev` (Hafenmeister) · `member@kamc.dev` (Mitglied)

## Manuell (falls das Skript nicht passt)
```bash
# 1) DB anlegen + Demo-Daten importieren (MAMP-MySQL, Port 8889, root/root)
/Applications/MAMP/Library/bin/mysql80/bin/mysql -h127.0.0.1 -P8889 -uroot -proot \
  -e "CREATE DATABASE IF NOT EXISTS kamc_lounge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
/Applications/MAMP/Library/bin/mysql80/bin/mysql -h127.0.0.1 -P8889 -uroot -proot kamc_lounge < db/demo-dump.sql

# 2) Konfiguration
cp config.mamp.php config.php     # MAMP-Defaults (127.0.0.1:8889, root/root)

# 3) Server starten
/Applications/MAMP/bin/php/php8.4.1/bin/php -S 127.0.0.1:8010 -t public
# -> http://localhost:8010
```

## Hinweise
- **Kein MAMP?** Es genügt PHP 8.1+ mit `pdo_mysql` und ein MySQL/MariaDB. Werte in
  `config.php` (`db.host/port/user/pass`) an deine Umgebung anpassen, dann `db/demo-dump.sql`
  in eine leere DB `kamc_lounge` importieren und `php -S 127.0.0.1:8010 -t public` starten.
- **E-Mails** werden im Dev-Modus nicht versendet, sondern als Dateien in `logs/mail/`
  abgelegt (Outbox). Zum Vorzeigen eine `.html` daraus im Browser öffnen.
- `config.php` ist bewusst nicht im Repo (Secrets) — deshalb die MAMP-Vorlage `config.mamp.php`.
