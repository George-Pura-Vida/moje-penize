BEGIN;
CREATE TABLE users(id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,email text NOT NULL UNIQUE,name text NOT NULL,role user_role NOT NULL,created_at timestamptz NOT NULL DEFAULT now());
CREATE TABLE clients(id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,advisor_id bigint REFERENCES users(id) ON DELETE SET NULL,name text NOT NULL,email text,created_at timestamptz NOT NULL DEFAULT now());
CREATE TABLE money_cases(id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,client_id bigint NOT NULL REFERENCES clients(id) ON DELETE RESTRICT,advisor_id bigint NOT NULL REFERENCES users(id) ON DELETE RESTRICT,title text NOT NULL,status case_state NOT NULL DEFAULT 'DRAFT',current_step text,created_at timestamptz NOT NULL DEFAULT now(),updated_at timestamptz NOT NULL DEFAULT now());
CREATE TABLE safety_cases(LIKE money_cases INCLUDING ALL);
ALTER TABLE safety_cases ADD CONSTRAINT safety_cases_client_fk FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE RESTRICT;
ALTER TABLE safety_cases ADD CONSTRAINT safety_cases_advisor_fk FOREIGN KEY(advisor_id) REFERENCES users(id) ON DELETE RESTRICT;
COMMIT;
