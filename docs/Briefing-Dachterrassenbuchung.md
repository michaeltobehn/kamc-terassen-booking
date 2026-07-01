# Buchungstool „Lounge oben" — Projektbriefing für Claude

*Arbeitsgrundlage für die Entwicklung. Kein SQL — das Datenmodell ist hier konzeptionell beschrieben.*
*Basis: Vorstandsbeschluss KAMC e.V. 22.05.2026, Pilotbetrieb bis 31.10.2026.*

---

## 0. Kontext in einem Absatz

Der KAMC e.V. (Kölner Autbord- und Motoryachtclub, Rheinauhafen Köln) gibt das Obergeschoss
des Clubs — Terrasse + Innenbereich, intern „Lounge oben" — zur privaten Nutzung durch
Vereinsmitglieder frei (Geburtstage, kleinere Feiern). Auslöser war u. a. ein
Ungleichbehandlungs-Vorwurf gegenüber der bezahlten „Schätzchen"-Nutzung; deshalb braucht es
eine **transparente, dokumentierte, für alle gleiche Regelung**. Michael baut dafür ein
schlankes, eigenständiges Web-Buchungstool. Der Hafenmeister (RSK) übergibt Schlüssel mit
Begehung und nimmt nach dem Termin ab. Es gibt **keine Bezahlung**.

---

## 1. Rolle & Tech-Stack

**Rolle:** Senior Full-Stack Developer mit Schwerpunkt Buchungs-/Reservierungssysteme auf
klassischem LAMP-Stack. Erst Datenmodell, Verfügbarkeitslogik und Konfliktvermeidung
durchdenken, dann UI. Grenzen von Shared Hosting respektieren (kein Node-Runtime, kein
pg_cron, Deployment per FTP). Bei Unsicherheit Trade-offs nennen.

| Schicht | Technologie |
|---|---|
| Datenbank | MySQL / MariaDB (Verwaltung via phpMyAdmin) |
| Backend | PHP 8.x mit PDO (Prepared Statements) |
| Frontend | PHP-Templates + Tailwind CSS (lokal per CLI gebaut, fertige `style.css` per FTP) + leichtes JS (Alpine.js/vanilla) |
| Hosting | Strato Shared Hosting (Apache), Deployment manuell per FTP |
| Entwicklung | VS Code + Claude Code; Projekt-Doku in Notion |

**Projektstruktur (FTP-freundlich):**
```
/public      Webroot: index.php, login.php, booking.php, admin/, hafenmeister/, assets/
/src         db.php, auth.php, bookings.php, availability.php (Logik/Services)
/config.php  DB-Credentials, AUSSERHALB /public (per .htaccess geschützt)
/cron/expire.php
/migrations  versionierte .sql (nur lokal/phpMyAdmin, nie in den Webroot)
/build       Tailwind-Input -> Output: public/assets/style.css
```

---

## 2. Domänenregeln (beschlossen)

| Punkt | Festlegung |
|---|---|
| Ressource | 1 Einheit „Lounge oben" (Obergeschoss: Terrasse + Innenbereich, ca. 24 Sitzplätze) |
| Nutzerkreis | nur Vereinsmitglieder; externe Gäste nur als Begleitung (Mitglied haftet) |
| Max. Personen | **16** (harte Grenze) |
| Vorlaufzeit | **mind. 24 h** vor Slot-Beginn |
| Buchungsfenster | bis **31.10.2026** (Pilot), konfigurierbar |
| **Buchungsslots** | **Zwei feste Slots pro Tag**, je genau einmal buchbar: **Tag** (Öffnung bis 18:00) und **Abend** (18:00 bis Schließung). Keine freie Zeitwahl. |
| Slot-Grenze | **18:00 Uhr** (lokal), konfigurierbar |
| Späteste Endzeit | **02:00 Uhr** (Folgetag → Abend-Slot läuft über Mitternacht) |
| Max. pro Tag | **2 Buchungen** (1× Tag + 1× Abend) |
| Musik | **keine** Musik / keine Lautsprecher (rote Linie, Hausordnung) |
| Lärm/Ruhe | regelt die Hausordnung |
| Verpflegung | Selbstversorgung (Speisen + Getränke mitbringen) |
| Grill | vom Verein gestellt (Gasgrill „Burnhard"), inkl. Haupt- + Ersatz-Gasflasche |
| Kühlschrank | nutzbar, danach leeren (Inhalt nicht gestellt) |
| Entgelt | **kostenlos** (kein Gebühren-/Pfandmodell im Pilot, keine Online-Zahlung) |
| Häufigkeit | im Pilot **nur tracken**, nicht erzwingen |
| Schlüssel | Übergabe + Rückgabe ausschließlich über Hafenmeisterei (RSK), jeweils mit Begehung |
| Abnahme | Hafenmeister prüft nach Termin: Grill sauber? Brandflecken? gekehrt? → Go oder Nacharbeit |
| Toiletten | Reinigung über regulären Hafenmeister-Service, nicht durch das Mitglied |

---

## 3. Rollen & Berechtigungen

Autorisierung komplett in PHP (kein RLS): `require_login()` / `require_role()`.

- **member** — bucht; sieht/storniert nur eigene Buchungen; sieht die freie/belegte Übersicht.
- **hafenmeister** — eigener Login. Entscheidet Buchungen (pending → confirmed/rejected),
  sieht die Belegung, führt die Abnahme durch.
- **admin (Vorstand)** — alles vom Hafenmeister + Einstellungen, Mitgliederverwaltung,
  Ausstattungspflege, Reports.

Statuswechsel zu `confirmed`/`rejected` und `inspection` nur durch hafenmeister/admin.

---

## 4. Datenmodell (konzeptionell — kein SQL)

**Querschnitt-Invarianten**
- Alle Zeitstempel in der DB sind **UTC**. Umrechnung Europe/Berlin ↔ UTC passiert nur an der
  I/O-Grenze in PHP. Die PDO-Verbindung setzt nach Connect `SET time_zone = '+00:00'`
  (numerischer Offset, nicht `'UTC'` — benannte Zonen fehlen auf Shared Hosting oft).
- **Zwei feste Slots pro Tag** (Tag / Abend). Jede Buchung belegt genau einen Slot; die
  Start/Ende-Zeiten ergeben sich aus dem Slot (Tag = Öffnung→18:00, Abend = 18:00→Schließung),
  nicht aus freier Eingabe. Kein Puffer, keine Mindest-/Maxdauer nötig.
- `pending` ist ein **Soft-Hold** und blockt seinen Slot mit.
- Doppelbuchungs-Schutz: pro (Datum, Slot) höchstens **eine aktive** Buchung
  (`pending`/`confirmed`). Weiterhin über `GET_LOCK` serialisiert, der Check ist aber nur noch
  „existiert aktive Buchung für dieses Datum + diesen Slot?".

**members** — Accounts werden aus den KAMC-Mitgliederdaten provisioniert (kein öffentliches
Self-Registration; Quelle/SSO-Kandidat: Kurabu-Portal, siehe offene Punkte).
- E-Mail (eindeutig), Passwort-Hash (`password_hash()`), Name
- Rolle (member | hafenmeister | admin), Status (active | pending)
- erstellt/geändert (UTC)

**bookings**
- Mitglied (Referenz)
- Buchungsdatum (lokaler Kalendertag) + **Slot** (tag | abend)
- Start/Ende (UTC, aus Datum + Slot abgeleitet und gespeichert)
- Status (pending | confirmed | rejected | cancelled)
- Personenzahl (≤ 16), Anlass (optional)
- Hausordnung-Zustimmung: Zeitpunkt + akzeptierte Version (revisionssicher, Pflicht)
- Entscheidung: wer / wann / Notiz (z. B. Ablehnungsgrund) — Audit-Trail
- Abnahme: Ergebnis (passed | rework) / wer / wann / Notiz (Grill, Brandflecken, gekehrt)
- erstellt/geändert (UTC)
- *Indizes konzeptionell:* Belegung (Datum+Slot+Status) für den Doppelbuchungs-Check,
  Expiry (Status+erstellt), „Meine Buchungen" (Mitglied+Datum), offene Abnahmen
  (Status+Ende+Abnahme).

**settings** (genau eine Zeile)
- Slot-Grenze Tag/Abend (lokal, Default 18:00), Vorlaufzeit (24 h), Pending-Expiry (z. B. 48 h)
- max. Personenzahl (16), Buchungsfenster-Ende (31.10.2026)

**opening_hours** — pro Wochentag (1=Mo … 7=So), Öffnen/Schließen als **lokale** Uhrzeit
(Europe/Berlin); ist Schließzeit ≤ Öffnungszeit, liegt sie am **Folgetag** (z. B. 08:00 → 02:00);
„geschlossen"-Flag.

**blackouts** — Sperrtage (lokaler Kalendertag) + Grund. Auch für **Vorrang
Vereinsveranstaltungen** nutzbar (z. B. Sommerfest sperrt den Tag).

**amenities (Ausstattung)** — datengetriebene Liste der Ausstattung: Reihenfolge, Name,
Beschreibung, Bild, „Bitte beachten"-Hinweise, Flag *abnahme-relevant*, aktiv.
Speist **beides**: die bebilderte Mitglieder-Galerie *und* die Abnahme-Checkliste des
Hafenmeisters. Items: Location/Übersicht, Kühlschrank, Sitzgruppe, Esstisch mit Stühlen,
Deck Chairs, Grill inkl. Zubehör.

---

## 5. Statusmaschine

```
Buchung anlegen ─────────────► pending        (Soft-Hold, blockt Slot inkl. Puffer)
hafenmeister/admin: pending ─► confirmed | rejected   (+ wer/wann/Notiz)
member: eigene Buchung ──────► cancelled
Auto-Expiry: pending (alt) ──► rejected        (Cron + Lazy-Expiry beim Lesen)

Nach confirmed + Termin vorbei:
  Abnahme ─► passed            (sauber, abgeschlossen)
          └─► rework           (Nacharbeit → erneute Begehung → passed)
```

**Auto-Expiry:** Offene `pending` ohne Entscheidung nach X Stunden → `rejected`. Umsetzung:
Strato-Cronjob ruft `cron/expire.php`; zusätzlich Lazy-Expiry beim Lesen (abgelaufene
`pending` nicht als belegt werten).

---

## 6. Seitentypen

| Bereich | Seite | Zweck | Zugriff |
|---|---|---|---|
| Auth | Login | Anmeldung | öffentlich |
| Auth | Passwort setzen / vergessen | Erstzugang & Reset (kein Self-Signup) | per Link |
| Mitglied | Belegungskalender | freie/belegte Zeiten sehen | member |
| Mitglied | Ausstattung „Lounge oben" | bebilderte Galerie + Hinweise (was buche ich, worauf achten) | member |
| Mitglied | Neue Buchung | Formular: Datum, Uhrzeit, Personenzahl, Anlass, Hausordnung-Checkbox | member |
| Mitglied | Buchung bestätigt | Eingangsbestätigung; Mail mit Hausordnung-PDF | member |
| Mitglied | Meine Buchungen | Liste mit Status; eigene stornieren | member |
| Hafenmeister | Offene Buchungen | pending genehmigen/ablehnen (+ Grund) | hafenmeister |
| Hafenmeister | Belegungsübersicht | alle Buchungen / Kalender | hafenmeister |
| Hafenmeister | Offene Abnahmen | Termine vorbei → passed / Nacharbeit (Checkliste aus Ausstattung) | hafenmeister |
| Admin | Einstellungen | Slots, Öffnungszeiten, Blackouts, Schwellen | admin |
| Admin | Mitgliederverwaltung | Provisionierung/Rollen/sperren | admin |
| Admin | Ausstattung pflegen | amenities anlegen/bearbeiten | admin |
| Admin | Reports | Nutzung/Häufigkeit (nur Tracking) | admin |
| Info | Hausordnung | Volltext + PDF-Quelle | member |

---

## 7. Flows

**A — Mitglied bucht**
Login → Belegungskalender → Tag wählen → **freien Slot wählen (Tag oder Abend)** → Formular
ausfüllen (Personenzahl ≤ 16, Anlass, Hausordnung bestätigen) → **Server validiert** (Vorlauf
24 h, Slot am Tag noch frei, kein Blackout, Buchungsfenster ≤ 31.10.2026). Verfügbarkeit wird
**immer serverseitig** berechnet, das Frontend ist nie Source of Truth.
→ Anlage in Transaktion, serialisiert über Named Lock (`GET_LOCK('booking_terrasse', 10)` →
Check „(Datum, Slot) noch frei?" → INSERT als `pending` → `RELEASE_LOCK`).
→ Eingangs-Mail an Mitglied (mit Hausordnung-PDF) + Benachrichtigung an die Hafenmeisterei.

**B — Hafenmeister entscheidet**
Sieht offene `pending` → **genehmigt** (`confirmed`, Mail „bestätigt" ans Mitglied,
Schlüsselübergabe vorbereiten) **oder lehnt ab** (`rejected` + Grund, Mail). Entscheidung wird
mit wer/wann/Notiz protokolliert.

**C — Schlüssel & Begehung**
Übergabe am Termin **mit Begehung**, Rückgabe **mit Begehung** → leitet in die Abnahme über.

**D — Abnahme**
Nach dem Termin prüft der Hafenmeister anhand der Ausstattungs-Checkliste (Grill sauber?
Brandflecken? gekehrt?) → `passed` oder `rework`. Bei Nacharbeit kommt das Mitglied zurück,
reinigt nach → erneute Begehung → `passed`.

**E — Storno**
Mitglied storniert eigene Buchung (bis Cutoff) → `cancelled` → Slot wird frei.

**F — Auto-Expiry**
`pending` ohne Entscheidung nach X h → `rejected` (Cron `expire.php` + Lazy-Expiry beim Lesen).

---

## 8. E-Mails

Alle Mails: Du-Ansprache, KAMC-Look (Logo, Navy + warmer Akzent), **HTML mit Plain-Text-
Alternative** (multipart). Versand serverseitig (PHPMailer über Strato-SMTP empfohlen, nicht
`mail()`). Zeiten immer in Europe/Berlin anzeigen. Absender/Reply-To = Hafenmeisterei bzw.
Vorstand. Platzhalter-Konvention: `{{name}}`, `{{datum}}`, `{{slot}}` (z. B. „Tag (bis 18:00)" / „Abend (ab 18:00)"),
`{{personenzahl}}`, `{{anlass}}`, `{{buchungsnummer}}`, `{{meine_buchungen_url}}`,
`{{hafenmeister_kontakt}}`, `{{ablehnungsgrund}}`, `{{beanstandung}}`, `{{cutoff}}`.

**Mitglieder-Mails entlang der Statusmaschine:**

| Auslöser | Mail | Kerninhalt |
|---|---|---|
| create → pending | Anfrage eingegangen | kurze Eingangsbestätigung, Eckdaten, „Hafenmeisterei prüft", Status-Link |
| pending → confirmed | **Buchungsbestätigung** | Eckdaten, Ort, Schlüssel & Begehung, Regel-Kurzfassung; Anhänge: Hausordnung-PDF + optional `.ics`; Storno-Hinweis |
| pending → rejected | Buchung abgelehnt | Grund, Einladung einen anderen Tag zu wählen |
| Abnahme passed | **Abnahme erledigt** | Dank, nichts zu tun |
| Abnahme rework | **Nacharbeit nötig** | konkrete Beanstandung, To-do, Kontakt für erneute Begehung (neutral) |

Optional/später: Erinnerung X h vor Termin (Schlüssel/Begehung), Storno-Bestätigung.

**Interne Mail (Hafenmeisterei):**
- *Neue Buchungsanfrage* (bei create): Mitglied, Datum/Uhrzeit, Personenzahl, Link zur
  Genehmigung. (Optional: Erinnerung an fällige Abnahmen.)

Vorlagen liegen als `email-vorlagen.html` (HTML, email-sicher: Tabellen-Layout + Inline-Styles)
und `email-vorlagen.txt` (Plain-Text) vor. In PHP als eine Layout-Hülle + Body-Partials je
Mail-Typ umsetzen.

---

## 9. Admin-Oberfläche im Detail

Eine Oberfläche, zwei Rollen. Hafenmeister sieht/bearbeitet den **operativen Betrieb**, Admin
zusätzlich **Konfiguration, Mitglieder und Auswertung**.

**Gemeinsam (Hafenmeister + Admin):**
- **Dashboard:** heute/diese Woche — anstehende bestätigte Termine, offene Anfragen (pending),
  offene Abnahmen (Termin vorbei, noch kein „ok").
- **Belegungskalender:** alle Buchungen, farbcodiert nach Status.
- **Buchungsliste** mit Filter (Status, Zeitraum, Mitglied) + Detailansicht je Buchung:
  Mitglied & Kontakt, Zeitdaten, Personenzahl, Anlass, Hausordnungs-Zustimmung (Version +
  Zeitpunkt), kompletter Verlauf (erstellt → entschieden von/wann/Notiz → Abnahme).
- **Aktionen:** genehmigen / ablehnen (+ Grund) bei pending; Abnahme erfassen (ok / Nacharbeit
  + Notiz) nach dem Termin.
- **Abnahme-Checkliste** je Termin, gespeist aus der Ausstattung (Grill, Brandflecken, gekehrt,
  Kühlschrank leer, Möbel zurück) — Haken + Notiz.

**Nur Admin (Vorstand):**
- **Einstellungen:** Slot-Grenze (18:00), Vorlauf, Pending-Expiry, Max-Personen,
  Buchungsfenster), Öffnungszeiten pro Wochentag, Blackouts/Sperrtage (auch Vereinsveranstaltungen).
- **Mitgliederverwaltung:** Rollen (member/hafenmeister/admin), Status (aktiv/pending/sperren),
  Provisionierung/Import (Kurabu), Einladung & Passwort-Reset.
- **Ausstattung pflegen:** Bild, Beschreibung, „Bitte beachten"-Hinweise, abnahme-relevant ja/nein.
- **Hausordnung verwalten:** Text/PDF + Versionsnummer.
- **Reports/Tracking:** Buchungen pro Mitglied/Zeitraum (Häufigkeit), Auslastung, Ablehnungs-/
  Nacharbeitsquote, CSV-Export.
- **Audit-Log:** wer hat wann was entschieden/abgenommen.

---

## 10. Designvorgaben (an kamc.koeln angelehnt)

Ziel: Das Tool ist eine eigenständige PHP-App, soll sich aber visuell **nahtlos in den
KAMC-Auftritt einfügen**, damit Mitglieder es sofort als KAMC erkennen.

**Bestandsaufnahme kamc.koeln:** WordPress + Elementor, Google Fonts, farbiges KAMC-Logo
(`Logo-bunt_500x500.png`) plus separater Schriftzug als SVG (`KAMC-Schriftzug.svg`). Maritime
Vereinsidentität (Rheinauhafen, „Ahoi und Willkommen"), traditionell + zeitgenössisch,
freundlich, **Du-Ansprache**. Mitglieder-Portal extern über Kurabu, Hafenmeister extern über RSK.

**Identität & Assets**
- KAMC-Logo + Schriftzug-SVG einbinden (Header/Login). Assets ggf. von der Live-Site übernehmen.
- Footer mit Rücklink zu `kamc.koeln`, zum Mitglieder-Portal (`kamc.kurabu.com`) sowie
  Impressum/Datenschutz analog zur Hauptseite.

**Farbe** — maritime Richtung: dunkles Navy/Blau als Primär, Weiß/Sand als Fläche, ein warmer
Akzent (Rot oder Messing) sehr sparsam für Aktionen/Status.
> ⚠️ Exakte Hexwerte **von der Live-Site übernehmen**: in WP-Admin → Elementor → Site Settings
> → Global Colors, oder im Browser die globale Elementor-CSS inspizieren. Nicht raten — 1:1 spiegeln.

**Typografie** — die auf kamc.koeln genutzten Google-Fonts übernehmen (Display für Headlines,
humanistische Sans für Fließtext). Familien aus dem `<head>`/CSS der Live-Site auslesen und in
Tailwind als `fontFamily` setzen.

**Layout & Komponenten**
- Ruhig, klar, viel Weißraum, großzügige Fotos (Hafen/Club/Terrasse). **Mobile-first** —
  Mitglieder buchen vom Handy.
- Kernkomponenten: Belegungskalender, Buchungsformular, **Statusbadges**
  (pending/confirmed/rejected/cancelled; Abnahme passed/rework), Ausstattungs-Galerie mit
  „Bitte beachten"-Tags als wiederkehrendes Signaturelement, Hafenmeister-Checkliste.
- Quality-Floor: sichtbarer Tastatur-Fokus, ausreichender Kontrast, `prefers-reduced-motion`.

**Ton/Copy**
- Du-Ansprache, freundlich-maritim („Ahoi"), aktive Verben. Buttons benennen die Aktion
  („Buchung anfragen", nicht „Absenden"); gleiche Vokabel durch den ganzen Flow.
- Fehler/Leerzustände sind Wegweiser: was ist los, was tun (z. B. „Dieser Abend ist schon
  zweimal vergeben — wähl einen anderen Tag.").

---

## 11. Sicherheit & Querschnitt (nicht verhandelbar)

- **Double-Booking-Prevention** ohne EXCLUDE-Constraint (MySQL kann das nicht): Anlage in
  Transaktion, serialisiert über `GET_LOCK` → Check „(Datum, Slot) frei?" (keine aktive
  pending/confirmed-Buchung für diesen Slot) → INSERT → Release. `pending` blockt den Slot mit.
- **Zeitzonen:** DB in UTC; Europe/Berlin nur an der I/O-Grenze; Session-TZ `'+00:00'`.
- **Verfügbarkeit** immer serverseitig berechnen — Frontend ist nie Source of Truth.
- **Sicherheit:** durchgängig PDO Prepared Statements (nie SQL zusammenstringen),
  `password_hash()`/`password_verify()`, CSRF-Token in allen Formularen, Sessions
  httponly+secure, `config.php` außerhalb des Webroots / per `.htaccess` geschützt.
- **Datenschutz:** Tool speichert Name, Datum, Personenzahl, Anlass → Hinweis im Buchungs-Flow.
- **Arbeitsweise:** Logik in `/src` kapseln (typisierte Funktionen/Klassen), nicht in Templates.
  Pro Feature erst Datenmodell/Logik, dann UI. Tailwind lokal bauen
  (`npx tailwindcss -i build/input.css -o public/assets/style.css --minify`), nur Output per FTP.
  Deploy-Checkliste pflegen (`config.php` nie überschreiben).

---

## 12. Offene Punkte (vor Implementierung klären)

1. **Mitglieder-Quelle / Auth:** Kurabu-SSO vs. Import vs. eigene Accounts; wie kommt das
   Mitglied erstmalig an Zugangsdaten (Einladung/Passwort setzen)?
2. **Tag-Slot-Startzeit:** Beginn des Tag-Slots = Öffnungszeit (aktuell 08:00). Passt das, oder
   soll der Tag-Slot z. B. erst ab 10:00/12:00 buchbar sein?
3. **Design-Tokens:** exakte Hexwerte + Font-Familien von der Live-Site bestätigen.
4. **Ausstattungs-Fotos:** echte Fotos (empfohlen, Mitglieder laden hoch / Vorstand pflegt) vs.
   Illustrationen/Lageplan?
5. **Begehungs-Uhrzeit** am Folgetag (fest, z. B. 10:00, oder flexibel?).
6. **Sanktionen** bei Verstößen (Sperrung der Buchung?) — wer entscheidet?
7. **Haftung/Versicherung** und **Mitglieder-Kommunikation** (Newsletter/Aushang) — eher
   Konzept/Hausordnung als Tool, hier nur als Verweis.

*Gelöst durch Beschluss „2 feste Slots": Tag/Abend-Grenze (18:00), Puffer, max. Abend-Buchungen,
Mindest-/Maxdauer — alle entfallen.*
