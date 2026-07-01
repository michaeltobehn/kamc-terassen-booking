#!/bin/bash
# =====================================================================
# KAMC Lounge oben — lokales Setup auf einem Mac mit MAMP (Doppelklick)
# Legt die DB an, importiert die Demo-Daten, erstellt config.php und
# startet den Server auf http://localhost:8010
# =====================================================================
cd "$(dirname "$0")" || exit 1

PHP=$(ls -1 /Applications/MAMP/bin/php/php*/bin/php 2>/dev/null | sort -V | tail -1)
[ -z "$PHP" ] && PHP=$(command -v php)
MYSQL=$(ls -1 /Applications/MAMP/Library/bin/mysql*/bin/mysql 2>/dev/null | sort -V | tail -1)
SOCK=/Applications/MAMP/tmp/mysql/mysql.sock

if [ -z "$PHP" ]; then echo "PHP nicht gefunden. Bitte MAMP installieren."; read -r _; exit 1; fi
echo "PHP:   $PHP"
echo "MySQL: ${MYSQL:-<nicht gefunden>}"

# MAMP-MySQL starten (falls vorhanden und nicht aktiv)
if [ -f /Applications/MAMP/bin/startMysql.sh ]; then
  /bin/sh /Applications/MAMP/bin/startMysql.sh >/dev/null 2>&1
  sleep 3
fi

if [ -z "$MYSQL" ]; then
  echo "MySQL-Client nicht gefunden — bitte DB manuell anlegen (siehe SETUP-LOCAL.md)."
else
  # Verbindung: erst Socket, sonst TCP 8889
  CONN="--socket=$SOCK -uroot -proot"
  "$MYSQL" $CONN -e "SELECT 1" >/dev/null 2>&1 || CONN="-h127.0.0.1 -P8889 -uroot -proot"
  echo "Lege Datenbank an & importiere Demo-Daten…"
  "$MYSQL" $CONN -e "CREATE DATABASE IF NOT EXISTS kamc_lounge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  "$MYSQL" $CONN kamc_lounge < db/demo-dump.sql && echo "Import OK."
fi

# config.php aus MAMP-Vorlage
if [ ! -f config.php ]; then cp config.mamp.php config.php && echo "config.php erstellt (MAMP-Defaults)."; fi

# alten Server auf 8010 beenden, neu starten
lsof -ti tcp:8010 2>/dev/null | xargs kill -9 2>/dev/null

echo ""
echo "======================================================"
echo " KAMC Lounge oben läuft:  http://localhost:8010"
echo " Logins (Passwort: kamc):"
echo "   admin@kamc.dev   ·  hafen@kamc.dev  ·  member@kamc.dev"
echo " Fenster offen lassen. Beenden mit Strg+C."
echo "======================================================"
open "http://localhost:8010" 2>/dev/null
exec "$PHP" -S 127.0.0.1:8010 -t public
