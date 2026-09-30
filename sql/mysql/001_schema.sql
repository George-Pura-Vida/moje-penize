SET NAMES utf8mb4;
SET time_zone = '+00:00';
CREATE TABLE IF NOT EXISTS schema_migrations(version varchar(100) PRIMARY KEY,applied_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS users(id bigint unsigned AUTO_INCREMENT PRIMARY KEY,email varchar(254) NOT NULL UNIQUE,name varchar(160) NOT NULL,role enum('ADMIN','MANAGER','ADVISOR','CLIENT') NOT NULL,password_hash varchar(255) NULL,totp_secret_enc text NULL,is_active tinyint(1) NOT NULL DEFAULT 1,created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS clients(id bigint unsigned AUTO_INCREMENT PRIMARY KEY,advisor_id bigint unsigned NULL,name varchar(180) NOT NULL,email varchar(254) NULL,phone varchar(40) NULL,created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_clients_advisor FOREIGN KEY(advisor_id) REFERENCES users(id) ON DELETE SET NULL,INDEX(advisor_id),INDEX(name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS money_cases(id bigint unsigned AUTO_INCREMENT PRIMARY KEY,client_id bigint unsigned NOT NULL,advisor_id bigint unsigned NOT NULL,title varchar(220) NOT NULL,status enum('DRAFT','DISCOVERY','ANALYSIS','ASSESSMENT','REVIEW','PROPOSAL','PRESENTED','DECISION','ACCEPTED','CONTRACTING','AWAITING_PAYMENT','COMPLETED','FOLLOW_UP','DECLINED','CANCELLED') NOT NULL DEFAULT 'DRAFT',current_step varchar(100),created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(client_id) REFERENCES clients(id),FOREIGN KEY(advisor_id) REFERENCES users(id),INDEX(client_id),INDEX(advisor_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS safety_cases LIKE money_cases;
-- CREATE TABLE LIKE does not copy foreign keys. Add each missing key independently
-- so a retry after partially completed MySQL DDL does not fail on duplicate names.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='safety_cases' AND CONSTRAINT_NAME='fk_safety_client'), 'SELECT 1', 'ALTER TABLE safety_cases ADD CONSTRAINT fk_safety_client FOREIGN KEY(client_id) REFERENCES clients(id)');
PREPARE safety_ddl FROM @ddl;
EXECUTE safety_ddl;
DEALLOCATE PREPARE safety_ddl;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='safety_cases' AND CONSTRAINT_NAME='fk_safety_advisor'), 'SELECT 1', 'ALTER TABLE safety_cases ADD CONSTRAINT fk_safety_advisor FOREIGN KEY(advisor_id) REFERENCES users(id)');
PREPARE safety_ddl FROM @ddl;
EXECUTE safety_ddl;
DEALLOCATE PREPARE safety_ddl;
