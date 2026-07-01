-- =====================================================================
-- Migration 002 — amenities (Ausstattung „Lounge oben")
-- Speist die Mitglieder-Galerie UND die Abnahme-Checkliste des Hafenmeisters.
-- MySQL 8.x / MariaDB, InnoDB, utf8mb4. Re-import-sicher.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS amenities (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sort_order           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  name                 VARCHAR(160)  NOT NULL,
  description          VARCHAR(1000) NULL,
  image_path           VARCHAR(255)  NULL,           -- Pfad unter /assets/img/...
  notes                VARCHAR(1000) NULL,           -- „Bitte beachten"-Hinweise
  inspection_relevant  TINYINT(1)    NOT NULL DEFAULT 0,   -- Teil der Abnahme-Checkliste?
  is_active            TINYINT(1)    NOT NULL DEFAULT 1,
  created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_amenities_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: Items aus dem Briefing (nur einfügen, wenn Tabelle leer ist).
INSERT INTO amenities (sort_order, name, description, notes, inspection_relevant, is_active)
SELECT * FROM (
  SELECT 10 AS sort_order, 'Location / Übersicht' AS name,
         'Das Obergeschoss der Lounge oben: Terrasse mit Rheinblick plus Innenbereich, rund 24 Sitzplätze.' AS description,
         'Rücksicht auf Nachbarschaft — keine Musik, keine Lautsprecher.' AS notes, 0 AS inspection_relevant, 1 AS is_active
  UNION ALL SELECT 20, 'Kühlschrank', 'Kühlschrank zur freien Nutzung während deiner Buchung.',
         'Nach dem Termin bitte komplett leeren — der Inhalt wird nicht gestellt.', 1, 1
  UNION ALL SELECT 30, 'Sitzgruppe', 'Gemütliche Lounge-Sitzgruppe im Innenbereich.',
         'Möbel nach dem Termin an den ursprünglichen Platz zurückstellen.', 1, 1
  UNION ALL SELECT 40, 'Esstisch mit Stühlen', 'Großer Esstisch mit Stühlen für gemeinsame Runden.',
         'Sauber hinterlassen, Stühle zurückstellen.', 1, 1
  UNION ALL SELECT 50, 'Deck Chairs', 'Liegestühle für die Terrasse.',
         'Bei Wind sichern; nach Nutzung zusammenklappen und verstauen.', 0, 1
  UNION ALL SELECT 60, 'Gasgrill „Burnhard"', 'Vom Verein gestellter Gasgrill inkl. Haupt- und Ersatz-Gasflasche.',
         'Grill nach Nutzung reinigen, auf Brandflecken prüfen, Terrasse kehren. Gas zudrehen.', 1, 1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM amenities);
