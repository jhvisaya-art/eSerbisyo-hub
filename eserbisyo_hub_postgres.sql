-- =====================================================================
-- eSerbisyo Hub — PostgreSQL schema and seed data
-- Converted from MySQL/MariaDB dump (Jan 6, 2026)
-- =====================================================================
-- To import:
--   psql "$DATABASE_URL" -f eserbisyo_hub_postgres.sql
-- Or locally:
--   psql -U postgres -d eserbisyo_hub -f eserbisyo_hub_postgres.sql
-- =====================================================================

BEGIN;

SET client_encoding = 'UTF8';

-- ---------------------------------------------------------------------
-- Drop in dependency order (safe to re-run)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS status_history CASCADE;
DROP TABLE IF EXISTS sms_logs       CASCADE;
DROP TABLE IF EXISTS payment_slips  CASCADE;
DROP TABLE IF EXISTS payments       CASCADE;
DROP TABLE IF EXISTS requests       CASCADE;
DROP TABLE IF EXISTS services       CASCADE;
DROP TABLE IF EXISTS login_attempts CASCADE;
DROP TABLE IF EXISTS staff_users    CASCADE;

-- ---------------------------------------------------------------------
-- Trigger function: emulate MySQL's "ON UPDATE CURRENT_TIMESTAMP"
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- =====================================================================
-- Table: requests
-- =====================================================================
CREATE TABLE requests (
  id              BIGSERIAL PRIMARY KEY,
  reference_no    VARCHAR(30)  NOT NULL UNIQUE,
  service_code    VARCHAR(50)  NOT NULL,
  last_name       VARCHAR(80)  NOT NULL,
  first_name      VARCHAR(80)  NOT NULL,
  middle_name     VARCHAR(80),
  address_line    VARCHAR(255) NOT NULL,
  mobile_no       VARCHAR(20)  NOT NULL,
  status          VARCHAR(40)  NOT NULL DEFAULT 'Queued',
  payment_status  VARCHAR(40)  NOT NULL DEFAULT 'Unpaid',
  consent_privacy SMALLINT     NOT NULL DEFAULT 0,
  is_archived     SMALLINT     NOT NULL DEFAULT 0,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP
);

CREATE INDEX idx_requests_mobile  ON requests (mobile_no);
CREATE INDEX idx_requests_status  ON requests (status);
CREATE INDEX idx_requests_service ON requests (service_code);

CREATE TRIGGER trg_requests_updated_at
BEFORE UPDATE ON requests
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- =====================================================================
-- Table: services
-- =====================================================================
CREATE TABLE services (
  id          SERIAL PRIMARY KEY,
  code        VARCHAR(50)  NOT NULL UNIQUE,
  name        VARCHAR(120) NOT NULL,
  description VARCHAR(255),
  is_active   SMALLINT     NOT NULL DEFAULT 1
);

-- =====================================================================
-- Table: staff_users
-- =====================================================================
CREATE TABLE staff_users (
  id            SERIAL PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(40)  NOT NULL DEFAULT 'STAFF',
  is_active     SMALLINT     NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- Table: login_attempts
-- =====================================================================
CREATE TABLE login_attempts (
  id           BIGSERIAL PRIMARY KEY,
  username     VARCHAR(120) NOT NULL,
  attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_login_attempts_username_attempted
  ON login_attempts (username, attempted_at);

-- =====================================================================
-- Table: payments
-- =====================================================================
CREATE TABLE payments (
  id          BIGSERIAL PRIMARY KEY,
  request_id  BIGINT        NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
  or_no       VARCHAR(60),
  paid_amount NUMERIC(10,2) NOT NULL DEFAULT 0.00,
  verified_by VARCHAR(120),
  verified_at TIMESTAMP
);

CREATE INDEX idx_payments_request ON payments (request_id);

-- =====================================================================
-- Table: payment_slips
-- =====================================================================
CREATE TABLE payment_slips (
  id         BIGSERIAL PRIMARY KEY,
  request_id BIGINT        NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
  amount     NUMERIC(10,2) NOT NULL DEFAULT 0.00,
  remarks    VARCHAR(255),
  printed_at TIMESTAMP
);

CREATE INDEX idx_payment_slips_request ON payment_slips (request_id);

-- =====================================================================
-- Table: sms_logs
-- =====================================================================
CREATE TABLE sms_logs (
  id               BIGSERIAL PRIMARY KEY,
  request_id       BIGINT       NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
  message_type     VARCHAR(40)  NOT NULL,
  recipient_mobile VARCHAR(20)  NOT NULL,
  message          VARCHAR(255) NOT NULL,
  sent_by          VARCHAR(120),
  sent_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status           VARCHAR(30)  NOT NULL DEFAULT 'QUEUED'
);

CREATE INDEX idx_sms_request ON sms_logs (request_id);

-- =====================================================================
-- Table: status_history
-- =====================================================================
CREATE TABLE status_history (
  id         BIGSERIAL PRIMARY KEY,
  request_id BIGINT       NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
  old_status VARCHAR(40),
  new_status VARCHAR(40)  NOT NULL,
  changed_by VARCHAR(120),
  note       VARCHAR(255),
  changed_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_history_request ON status_history (request_id);

-- =====================================================================
-- Seed data
-- =====================================================================

-- services
INSERT INTO services (id, code, name, description, is_active) VALUES
  (1, 'CEDULA',           'Community Tax Certificate (Cedula)',         NULL, 1),
  (2, 'MAYOR_CLEARANCE',  'Mayor''s Clearance',                         NULL, 1),
  (3, 'CTC_BIRTH',        'Certified True Copy - Birth Certificate',    NULL, 1),
  (4, 'CTC_MARRIAGE',     'Certified True Copy - Marriage Certificate', NULL, 1),
  (5, 'CTC_DEATH',        'Certified True Copy - Death Certificate',    NULL, 1),
  (6, 'DELAYED_REG',      'Delayed Registration',                       NULL, 1),
  (7, 'BUSINESS_RENEWAL', 'Business Permit Renewal',                    NULL, 1),
  (8, 'OTHER_RENEWAL',    'Other Renewal Requests',                     NULL, 1);

-- staff_users
INSERT INTO staff_users (id, username, password_hash, role, is_active, created_at) VALUES
  (1, 'admin',  '$2y$10$yvXZPeXZRHOSt55HjMm.7.vgr59vby2UHTBzAbC94fOPhD./F//2O', 'ADMIN', 1, '2026-01-06 15:20:12'),
  (2, 'staff1', '$2y$10$K8IYQEC.XKHJhph4xkNIbel6Yurn8P2zvWyNLutM3HUsQrXtMLaRG', 'STAFF', 1, '2026-01-06 16:05:47');

-- requests
INSERT INTO requests (id, reference_no, service_code, last_name, first_name, middle_name, address_line, mobile_no, status, payment_status, consent_privacy, created_at, updated_at) VALUES
  (7,  'ES-20260106-DA4E23', 'CEDULA',          'Visaya', 'Jherson', 'Catubig', 'Zone 5, Lupi, San Fernando, Camarines Sur', '09922010267', 'Queued',   'Unpaid', 1, '2026-01-06 23:27:37', NULL),
  (8,  'ES-20260106-EB68E2', 'CEDULA',          'Visaya', 'Jherson', 'Catubig', 'Zone 5, Lupi, San Fernando, Camarines Sur', '09992201023', 'Queued',   'Unpaid', 1, '2026-01-06 23:28:47', NULL),
  (9,  'ES-20260106-40DA0D', 'MAYOR_CLEARANCE', 'Visaya', 'Jherson', 'Catubig', 'Zone 5, Lupi, San Fernando, Camarines Sur', '09992201023', 'Queued',   'Unpaid', 1, '2026-01-06 23:35:24', NULL),
  (10, 'ES-20260106-360FB8', 'MAYOR_CLEARANCE', 'Visaya', 'Jherson', 'Catubig', 'Zone 5, Lupi, San Fernando, Camarines Sur', '09992201023', 'Released', 'Paid',   1, '2026-01-06 23:35:30', '2026-01-06 23:40:49');

-- payment_slips
INSERT INTO payment_slips (id, request_id, amount, remarks, printed_at) VALUES
  (7,  7,  0.00,   NULL, NULL),
  (8,  8,  0.00,   NULL, NULL),
  (9,  9,  0.00,   NULL, NULL),
  (10, 10, 0.00,   NULL, NULL);

-- payments
INSERT INTO payments (id, request_id, or_no, paid_amount, verified_by, verified_at) VALUES
  (5, 10, 'OR-2026-973156', 100.00, 'admin', '2026-01-06 23:39:58');

-- status_history
INSERT INTO status_history (id, request_id, old_status, new_status, changed_by, note, changed_at) VALUES
  (34, 7,  NULL,                'Queued',             'KIOSK',  'Initial submission',           '2026-01-06 23:27:37'),
  (35, 8,  NULL,                'Queued',             'KIOSK',  'Initial submission',           '2026-01-06 23:28:47'),
  (36, 9,  NULL,                'Queued',             'KIOSK',  'Initial submission',           '2026-01-06 23:35:24'),
  (37, 10, NULL,                'Queued',             'KIOSK',  'Initial submission',           '2026-01-06 23:35:30'),
  (38, 10, 'Queued',            'In Process',         'staff1', 'Status set to In Process (UI)','2026-01-06 23:37:37'),
  (39, 10, NULL,                'For Payment Verification', 'admin', 'Payment verified',        '2026-01-06 23:39:58'),
  (40, 10, 'In Process',        'Ready for Release',  'admin',  'Status set to Ready (UI)',     '2026-01-06 23:40:21'),
  (41, 10, 'Ready for Release', 'Released',           'admin',  'Released via modal',           '2026-01-06 23:40:49');

-- =====================================================================
-- Re-sync sequences so new inserts pick up after the seed IDs.
-- (Otherwise the next INSERT would try id=1 and collide.)
-- =====================================================================
SELECT setval(pg_get_serial_sequence('requests',       'id'), COALESCE((SELECT MAX(id) FROM requests),       1));
SELECT setval(pg_get_serial_sequence('services',       'id'), COALESCE((SELECT MAX(id) FROM services),       1));
SELECT setval(pg_get_serial_sequence('staff_users',    'id'), COALESCE((SELECT MAX(id) FROM staff_users),    1));
SELECT setval(pg_get_serial_sequence('login_attempts', 'id'), COALESCE((SELECT MAX(id) FROM login_attempts), 1));
SELECT setval(pg_get_serial_sequence('payments',       'id'), COALESCE((SELECT MAX(id) FROM payments),       1));
SELECT setval(pg_get_serial_sequence('payment_slips',  'id'), COALESCE((SELECT MAX(id) FROM payment_slips),  1));
SELECT setval(pg_get_serial_sequence('sms_logs',       'id'), COALESCE((SELECT MAX(id) FROM sms_logs),       1));
SELECT setval(pg_get_serial_sequence('status_history', 'id'), COALESCE((SELECT MAX(id) FROM status_history), 1));

COMMIT;
