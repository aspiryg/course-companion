-- =============================================================================
-- Migration 002: Courses, Modules, Enrollments, Completions
-- =============================================================================
-- This migration sets up the core learning data model.
--
-- ENTITY RELATIONSHIP:
--
--   users ──── (instructor_id) ──── courses
--     │                                │
--     │    (enrollments join table)    │ (has many)
--     └──────────── enrollments        modules
--                                        │
--   users ─── (completions join table) ──┘
--
-- MANY-TO-MANY RELATIONSHIPS:
-- A student can enroll in many courses. A course can have many students.
-- This is a many-to-many relationship. We resolve it with a JOIN TABLE:
--   enrollments(user_id, course_id) — one row per student+course combination
--
-- Similarly: a student can complete many modules; a module can be completed
-- by many students → completions(user_id, module_id)
-- =============================================================================

USE course_companion;

-- =============================================================================
-- TABLE: courses
-- =============================================================================

CREATE TABLE IF NOT EXISTS courses (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    title         VARCHAR(500)     NOT NULL,
    description   TEXT             NULL,       -- TEXT can hold ~65KB of text
    instructor_id BIGINT UNSIGNED  NOT NULL,   -- FK → users.id (must be instructor/admin)
    is_published  TINYINT(1)       NOT NULL DEFAULT 0,  -- Draft until published
    created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_courses_instructor (instructor_id),
    INDEX idx_courses_published  (is_published),

    -- FOREIGN KEY constraint:
    -- Guarantees that instructor_id always refers to a real user in the users table.
    -- ON DELETE RESTRICT = if you try to delete a user who owns courses, MySQL
    -- BLOCKS it. You must delete/reassign courses first. This prevents orphaned data.
    CONSTRAINT fk_courses_instructor
        FOREIGN KEY (instructor_id)
        REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE       -- If user's id changes (rare), cascade the update

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Courses created by instructors';

-- =============================================================================
-- TABLE: modules
-- =============================================================================
-- A module is a unit within a course (lesson, quiz, assignment, etc.)
-- Modules belong to exactly one course.
-- order_index determines the sequence within the course.

CREATE TABLE IF NOT EXISTS modules (
    id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    course_id    BIGINT UNSIGNED  NOT NULL,
    title        VARCHAR(500)     NOT NULL,
    description  TEXT             NULL,
    type         ENUM('lesson','quiz','assignment','resource') NOT NULL DEFAULT 'lesson',
    order_index  SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- Sort order within course
    created_at   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_modules_course (course_id),

    -- Composite index on (course_id, order_index) speeds up
    -- "get all modules for course X sorted by order" — a very common query
    INDEX idx_modules_course_order (course_id, order_index),

    CONSTRAINT fk_modules_course
        FOREIGN KEY (course_id)
        REFERENCES courses (id)
        ON DELETE CASCADE   -- If a course is deleted, delete its modules too (cascade)
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Learning units (lessons, quizzes, etc.) within a course';

-- =============================================================================
-- TABLE: enrollments
-- =============================================================================
-- JOIN TABLE resolving the many-to-many between users and courses.
-- One row = one student enrolled in one course.

CREATE TABLE IF NOT EXISTS enrollments (
    id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED  NOT NULL,
    course_id   BIGINT UNSIGNED  NOT NULL,
    enrolled_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- We could add: status ENUM('active','dropped','completed'), grade, etc.

    PRIMARY KEY (id),

    -- UNIQUE on (user_id, course_id) prevents enrolling the same student twice
    UNIQUE KEY uq_enrollments (user_id, course_id),

    INDEX idx_enrollments_user   (user_id),
    INDEX idx_enrollments_course (course_id),

    CONSTRAINT fk_enrollments_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE,   -- Delete student → remove their enrollments

    CONSTRAINT fk_enrollments_course
        FOREIGN KEY (course_id)
        REFERENCES courses (id)
        ON DELETE CASCADE    -- Delete course → remove all enrollments

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks which students are enrolled in which courses';

-- =============================================================================
-- TABLE: completions
-- =============================================================================
-- Records when a student marks a module as complete.
-- JOIN TABLE between users and modules.

CREATE TABLE IF NOT EXISTS completions (
    id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED  NOT NULL,
    module_id    BIGINT UNSIGNED  NOT NULL,
    completed_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- A student can only complete a module once
    UNIQUE KEY uq_completions (user_id, module_id),

    INDEX idx_completions_user   (user_id),
    INDEX idx_completions_module (module_id),

    CONSTRAINT fk_completions_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE,

    CONSTRAINT fk_completions_module
        FOREIGN KEY (module_id)
        REFERENCES modules (id)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks module completion per student';