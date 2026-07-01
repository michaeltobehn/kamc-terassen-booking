-- =====================================================================
-- Dachterrassen-Buchung „Lounge oben" (KAMC e.V.) — Initiales Schema
-- Migration: 001_initial_schema.sql
-- Ziel-DB:  MySQL 8.x / MariaDB 10.4+   Engine: InnoDB   Charset: utf8mb4
-- Stand:    nach Briefing-Klärung (Vorstandsbeschluss 22.05.2026)
-- =====================================================================
--
-- ZEITZONEN (kritisch):
--   * _utc-Spalten + created_at/updated_at/decided_at/inspected_at speichern UTC.
--   * PHP/PDO-Verbindung MUSS nach Connect die Session-TZ setzen:
--         $pdo->exec("SET time_zone = '+00:00'");
--     Numerischer Offset '+00:00', NICHT 'UTC' (benannte Zonen brauchen die
--     mysql-tz-Tabellen, die auf Shared Hosting oft fehlen).
--   * open_time/close_time (opening_hours), evening_start_local (settings) und
--     blackout_date (blackouts) sind LOKALE Zeit/Daten (Europe/Berlin).
--     Verfügbarkeitslogik: UTC-Buchung -> Europe/Berlin projizieren -> prüfen.
--
-- SLOTS (2 feste pro Tag):
--   Jede Buchung belegt genau EINEN Slot:
--     * 'tag'   = Öffnung .. evening_start_local (18:00)
--     * 'abend' = evening_start_local (18:00) .. Schließung (02:00 Folgetag)
--   start_utc/end_utc werden aus booking_date + slot abgeleitet und gespeichert.
--   Keine freie Zeitwahl, keine Mindest-/Maxdauer, kein Puffer. Max. 2 Buchungen
--   pro Tag (1x tag + 1x abend). Abend-Slot läuft über Mitternacht.
--
-- FREIGABE:
--   create -> immer 'pending' (Soft-Hold, blockt (booking_date, slot) mit).
--   Hafenmeister/Admin entscheidet -> 'confirmed' | 'rejected'.
--   Mitglied -> eigene Buchung -> 'cancelled'.
--   Auto-Expiry: offenes 'pending' ohne Entscheidung nach X h -> 'rejected'.
--
-- IMPORT: einmalig via phpMyAdmin in leere DB. Re-import-sicher.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';


-- ---------------------------------------------------------------------
-- members  (Accounts werden aus den KAMC-Mitgliederdaten provisioniert)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS members (
  id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  email         VARCHAR(255)     NOT NULL,
  password_hash VARCHAR(255)     NOT NULL,                        -- password_hash(): bcrypt=60, argon2 länger
  name          VARCHAR(255)     NOT NULL,
  role          ENUM('member','hafenmeister','admin') NOT NULL DEFAULT 'member',
  status        ENUM('active','pending') NOT NULL DEFAULT 'pending',  -- 'pending' = importiert, Zugang noch nicht aktiviert
  created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_members_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
-- Rollen: member = bucht; hafenmeister = entscheidet Buchungen + Abnahme;
--         admin = Vorstand (volle Sicht + Settings).


-- ---------------------------------------------------------------------
-- bookings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
  id          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  member_id   BIGINT UNSIGNED   NOT NULL,
  booking_date DATE             NOT NULL,                        -- LOKALER Kalendertag (Europe/Berlin)
  slot        ENUM('tag','abend') NOT NULL,                     -- fester Slot; bestimmt die Zeiten
  start_utc   DATETIME          NOT NULL,                        -- UTC, aus booking_date+slot abgeleitet (inklusiv)
  end_utc     DATETIME          NOT NULL,                        -- UTC, aus booking_date+slot abgeleitet (EXKLUSIV)
  status      ENUM('pending','confirmed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  party_size  SMALLINT UNSIGNED NOT NULL,                        -- App erzwingt <= settings.max_party_size (16)
  purpose     VARCHAR(500)      NULL,                            -- Anlass (optional)

  -- Hausordnung (Pflicht im Buchungs-Flow, revisionssicher festgehalten)
  hausordnung_accepted_at DATETIME    NOT NULL,                  -- UTC; ohne Zustimmung keine Buchung
  hausordnung_version     VARCHAR(20) NOT NULL,                  -- z.B. '2026-1'

  -- Entscheidung (Hafenmeister/Admin) — Audit-Trail
  decided_by    BIGINT UNSIGNED NULL,
  decided_at    DATETIME        NULL,
  decision_note VARCHAR(500)    NULL,                            -- z.B. Ablehnungsgrund

  -- Abnahme / Begehung nach dem Termin (durch Hafenmeister)
  inspection_result ENUM('passed','rework') NULL,                -- NULL = noch nicht abgenommen
  inspected_by      BIGINT UNSIGNED NULL,
  inspected_at      DATETIME        NULL,
  inspection_notes  VARCHAR(500)    NULL,                        -- "Grill ok / Brandfleck / gekehrt"

  created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- Basis für Auto-Expiry
  updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_slot      (booking_date, slot, status),               -- Doppelbuchungs-Check „(Datum, Slot) frei?"
  KEY idx_expiry    (status, created_at),                       -- Auto-Expiry-Sweep
  KEY idx_member    (member_id, booking_date),                  -- "Meine Buchungen" + Häufigkeits-Reports
  KEY idx_abnahme   (status, end_utc, inspection_result),       -- offene Abnahmen

  CONSTRAINT fk_bookings_member    FOREIGN KEY (member_id)    REFERENCES members (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_bookings_decider   FOREIGN KEY (decided_by)   REFERENCES members (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bookings_inspector FOREIGN KEY (inspected_by) REFERENCES members (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_booking_time CHECK (end_utc > start_utc),
  CONSTRAINT chk_party_size   CHECK (party_size > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
-- Doppelbuchungs-Schutz: KEIN UNIQUE(booking_date, slot) — cancelled/rejected
-- müssen denselben Slot wieder freigeben. Stattdessen in PHP: GET_LOCK ->
-- „existiert aktive (pending|confirmed) Buchung für (booking_date, slot)?" ->
-- sonst INSERT -> RELEASE_LOCK.


-- ---------------------------------------------------------------------
-- settings  (Singleton: genau eine Zeile, id = 1)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id                     TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  evening_start_local    TIME              NOT NULL DEFAULT '18:00:00',  -- Slot-Grenze Tag/Abend (LOKAL)
  lead_time_hours        SMALLINT UNSIGNED NOT NULL DEFAULT 24,   -- Vorlaufzeit: frühestens 24 h vor Slot-Beginn
  pending_expiry_hours   SMALLINT UNSIGNED NOT NULL DEFAULT 48,   -- offenes pending -> rejected nach X h
  max_party_size         SMALLINT UNSIGNED NOT NULL DEFAULT 16,   -- harte Obergrenze (Beschluss)
  booking_window_end     DATE              NULL     DEFAULT '2026-10-31',  -- Pilot-Ende: kein start danach
  updated_at             DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT chk_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Entfallen ggü. Freizeit-Modell: min/max_duration, buffer_minutes,
-- max_evening_bookings, max_daytime_bookings. Slots sind fix (tag/abend).

INSERT INTO settings (id) VALUES (1)
  ON DUPLICATE KEY UPDATE id = id;          -- Seed: eine Zeile mit Defaults


-- ---------------------------------------------------------------------
-- opening_hours  (LOKAL Europe/Berlin; Fenster darf über Mitternacht laufen)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS opening_hours (
  weekday    TINYINT UNSIGNED NOT NULL,                          -- ISO-8601: 1=Mo .. 7=So (PHP date('N'))
  open_time  TIME    NOT NULL DEFAULT '08:00:00',                -- Europe/Berlin
  close_time TIME    NOT NULL DEFAULT '02:00:00',                -- Europe/Berlin; <= open_time => Folgetag!
  is_closed  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (weekday),
  CONSTRAINT chk_weekday CHECK (weekday BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Regel: ist close_time <= open_time, liegt die Schließzeit am FOLGETAG
--        (z.B. 08:00 -> 02:00 = bis 02:00 Uhr morgens). Die PHP-Logik bildet
--        das Fenster als [Tag open_time, Tag+1 close_time] ab.

INSERT INTO opening_hours (weekday, open_time, close_time, is_closed) VALUES
  (1, '08:00:00', '02:00:00', 0),
  (2, '08:00:00', '02:00:00', 0),
  (3, '08:00:00', '02:00:00', 0),
  (4, '08:00:00', '02:00:00', 0),
  (5, '08:00:00', '02:00:00', 0),
  (6, '08:00:00', '02:00:00', 0),
  (7, '08:00:00', '02:00:00', 0)
  ON DUPLICATE KEY UPDATE weekday = weekday;   -- Seed, nichts überschreiben


-- ---------------------------------------------------------------------
-- blackouts  (Sperrtage, LOKALER Kalendertag Europe/Berlin)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blackouts (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  blackout_date DATE            NOT NULL,
  reason        VARCHAR(255)    NULL,                            -- z.B. "Vereinsveranstaltung 90-Jahr-Sommerfest"
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blackout_date (blackout_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Vorrang Vereinsveranstaltungen lässt sich für den Pilot als Blackout abbilden.


-- ---------------------------------------------------------------------
-- ERSTER ADMIN / HAFENMEISTER — NICHT per SQL anlegen!
-- ---------------------------------------------------------------------
-- password_hash() gehört in PHP. Einmaliges Setup-Skript (danach löschen):
--   $hash = password_hash('START-PASSWORT', PASSWORD_DEFAULT);
--   $pdo->prepare("INSERT INTO members (email,password_hash,name,role,status)
--                  VALUES (:e,:h,:n,'admin','active')")
--       ->execute([':e'=>'vorstand@kamc.de', ':h'=>$hash, ':n'=>'Vorstand']);
-- =====================================================================
