-- ═══════════════════════════════════════════════════════════
--  SMART SEMESTER — Complete MySQL Database Schema
--  File: database.sql
--
--  HOW TO IMPORT:
--  Option 1 → phpMyAdmin: Click "Import" → Choose this file → Go
--  Option 2 → Terminal:   mysql -u root -p < database.sql
-- ═══════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS smart_semester
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_semester;

-- ─────────────────────────────────
-- TABLE 1: users
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
    photo       VARCHAR(255)  DEFAULT NULL,
    department  VARCHAR(50)   DEFAULT NULL,
    semester    VARCHAR(20)   DEFAULT NULL,
    is_active   TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ─────────────────────────────────
-- TABLE 2: materials
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS materials (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    subject      VARCHAR(100) NOT NULL,
    department   VARCHAR(50)  NOT NULL,
    semester     VARCHAR(20)  NOT NULL,
    type         ENUM('pdf','video','pyq') NOT NULL,
    file_path    VARCHAR(255) NOT NULL,
    file_size    VARCHAR(30)  DEFAULT NULL,
    uploaded_by  INT          NOT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────
-- TABLE 3: tests
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS tests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    subject      VARCHAR(100) NOT NULL,
    department   VARCHAR(50)  NOT NULL,
    semester     VARCHAR(20)  NOT NULL,
    duration     INT          NOT NULL DEFAULT 30,
    total_marks  INT          NOT NULL DEFAULT 20,
    scheduled_at DATE         DEFAULT NULL,
    status       ENUM('draft','active','completed') DEFAULT 'draft',
    created_by   INT          NOT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────
-- TABLE 4: questions
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS questions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    test_id     INT          NOT NULL,
    question    TEXT         NOT NULL,
    option_a    VARCHAR(255) NOT NULL,
    option_b    VARCHAR(255) NOT NULL,
    option_c    VARCHAR(255) NOT NULL,
    option_d    VARCHAR(255) NOT NULL,
    correct_ans ENUM('A','B','C','D') NOT NULL,
    marks       INT          NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
);

-- ─────────────────────────────────
-- TABLE 5: test_attempts
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS test_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    test_id      INT NOT NULL,
    student_id   INT NOT NULL,
    score        INT NOT NULL DEFAULT 0,
    total_marks  INT NOT NULL DEFAULT 0,
    percentage   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    time_taken   INT DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attempt (test_id, student_id),
    FOREIGN KEY (test_id)    REFERENCES tests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────
-- TABLE 6: attempt_answers
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS attempt_answers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id   INT NOT NULL,
    question_id  INT NOT NULL,
    selected_ans ENUM('A','B','C','D') DEFAULT NULL,
    is_correct   TINYINT(1) DEFAULT 0,
    FOREIGN KEY (attempt_id)  REFERENCES test_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)     ON DELETE CASCADE
);

-- ─────────────────────────────────
-- TABLE 7: messages
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT  NOT NULL,
    receiver_id INT  NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    sent_at     TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────
-- TABLE 8: attendance
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS attendance (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT  NOT NULL,
    subject     VARCHAR(100) NOT NULL,
    date        DATE         NOT NULL,
    status      ENUM('present','absent','late') DEFAULT 'present',
    marked_by   INT NOT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by)  REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────
-- DEMO DATA — 2 users to test with
-- password for both = "demo123"
-- ─────────────────────────────────
INSERT INTO users (name, email, password, role, department, semester) VALUES
('Rahul Sharma',    'student@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'CSE', 'Semester 5'),
('Prof. Anita Verma','teacher@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'CSE', NULL);
