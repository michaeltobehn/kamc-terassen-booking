-- =====================================================================
-- Migration 004 — Reklamation / Abnahme-Fall-Lebenszyklus
-- Deckt ab: Nacharbeit-Loop, Mitglieder-Widerspruch, Eskalation an Vorstand,
-- Auflösung (inkl. optionaler Buchungssperre) + Audit-Trail.
-- MySQL 8.x / MariaDB. Einmalig importieren.
-- =====================================================================

SET NAMES utf8mb4;

-- Fall-Status je Buchung (nur relevant, wenn Abnahme = rework):
--   none      = kein Fall
--   rework    = Nacharbeit offen (Mitglied soll beheben, Frist läuft)
--   disputed  = Mitglied hat widersprochen -> zur Klärung
--   escalated = Hafenmeister hat an Vorstand eskaliert
--   resolved  = abgeschlossen (Re-Abnahme ok ODER Vorstandsbeschluss)
ALTER TABLE bookings
  ADD COLUMN case_status ENUM('none','rework','disputed','escalated','resolved') NOT NULL DEFAULT 'none',
  ADD COLUMN rework_due  DATE            NULL,           -- Frist für Nacharbeit
  ADD COLUMN resolution  VARCHAR(30)     NULL,           -- ok | kulanz | kosten | sperre
  ADD COLUMN resolved_by BIGINT UNSIGNED NULL,
  ADD COLUMN resolved_at DATETIME        NULL,
  ADD KEY idx_case (case_status),
  ADD CONSTRAINT fk_bookings_resolver FOREIGN KEY (resolved_by) REFERENCES members (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Buchungssperre für ein Mitglied (Sanktion, entscheidet Vorstand)
ALTER TABLE members
  ADD COLUMN booking_blocked TINYINT(1) NOT NULL DEFAULT 0;

-- Audit-Trail des Falls (Verlauf: wer, was, wann, Notiz)
CREATE TABLE IF NOT EXISTS inspection_events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id  BIGINT UNSIGNED NOT NULL,
  actor_id    BIGINT UNSIGNED NULL,
  event_type  VARCHAR(40)     NOT NULL,                  -- rework | dispute | member_done | escalate | resolve | reinspect_pass
  note        VARCHAR(1000)   NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ie_booking (booking_id, created_at),
  CONSTRAINT fk_ie_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT fk_ie_actor   FOREIGN KEY (actor_id)   REFERENCES members (id)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: bestehende rework-Abnahmen als offenen Nacharbeit-Fall markieren
UPDATE bookings SET case_status = 'rework' WHERE inspection_result = 'rework' AND case_status = 'none';
