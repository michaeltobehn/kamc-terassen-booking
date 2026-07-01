-- =====================================================================
-- Migration 003 — inspection_photos (Foto-Dokumentation der Abnahme)
-- Mehrere Fotos je Buchung; verlinkt Datei-Pfade unter /public/uploads/abnahmen.
-- MySQL 8.x / MariaDB, InnoDB, utf8mb4. Re-import-sicher.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS inspection_photos (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id   BIGINT UNSIGNED NOT NULL,
  file_path    VARCHAR(255)    NOT NULL,               -- z.B. /uploads/abnahmen/<hash>.webp
  uploaded_by  BIGINT UNSIGNED NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_booking (booking_id),
  CONSTRAINT fk_ip_booking   FOREIGN KEY (booking_id)  REFERENCES bookings (id) ON DELETE CASCADE   ON UPDATE CASCADE,
  CONSTRAINT fk_ip_uploader  FOREIGN KEY (uploaded_by) REFERENCES members (id)  ON DELETE SET NULL  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
