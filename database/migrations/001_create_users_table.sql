USE course_companion;
CREATE TABLE IF NOT EXISTS users (
	id						BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name 				  VARCHAR(255) 		NOT NULL,
	email					VARCHAR(320)		NOT NULL,
	password_hash	VARCHAR(255)		NOT NULL,
	role					ENUM('admin','instructor','student') NOT NULL DEFAULT 'student',
	avatar_url		VARCHAR(500)		NULL,
	is_active			TINYINT(1)			NOT NULL DEFAULT 1,
	created_at		TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at		TIMESTAMP				NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	
	PRIMARY KEY (id),

	UNIQUE KEY uq_users_email (email),
	
	INDEX idx_users_role (role),
	INDEX idx_users_active (is_active)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci
COMMENT='All user accounts: admins, instructors, and students';

INSERT IGNORE  INTO users (name, email, password_hash, role) VALUES
(
	'Admin User',
	'admin@coursecompanion.dev',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
	'admin'
),
(
	'Jane Instructor',
	'instructor@coursecompanion.dev',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
	'instructor'
)
