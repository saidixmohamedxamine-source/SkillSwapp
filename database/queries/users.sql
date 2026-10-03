CREATE DATABASE IF NOT EXISTS ISMO_SkillSwap_V2;
USE ISMO_SkillSwap_V2;

-- ============================================================
-- Database Schema: ISMO_SkillSwap_V2
-- ============================================================

-- 1. USERS
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    last_name VARCHAR(45) NOT NULL,
    first_name VARCHAR(45) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    photo VARCHAR(255),
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_profile_public TINYINT(1) DEFAULT 1,
    notifications_enabled TINYINT(1) DEFAULT 1
);

-- 2. LEARNERS
CREATE TABLE IF NOT EXISTS learners (
    learner_id INT PRIMARY KEY,
    specialization VARCHAR(100),
    score INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (learner_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 4. SUPERVISORS
CREATE TABLE IF NOT EXISTS supervisors (
    supervisor_id INT PRIMARY KEY,
    filiere VARCHAR(100),
    FOREIGN KEY (supervisor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 5. ADMINISTRATORS
CREATE TABLE IF NOT EXISTS administrators (
    admin_id INT PRIMARY KEY,
    access_level INT DEFAULT 1,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 6. SKILLS
CREATE TABLE IF NOT EXISTS skills (
    skill_id INT AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(100) NOT NULL,
    category VARCHAR(50)
);

-- 7. LEARNER_SKILLS
CREATE TABLE IF NOT EXISTS learner_skills (
    possession_id INT AUTO_INCREMENT PRIMARY KEY,
    learner_id INT NOT NULL,
    skill_id INT NOT NULL,
    skill_level ENUM('beginner', 'intermediate', 'advanced', 'expert'),
    FOREIGN KEY (learner_id) REFERENCES learners(learner_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
);

-- 8. HELP_REQUESTS
CREATE TABLE IF NOT EXISTS help_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    learner_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    request_status ENUM('open', 'in_progress', 'resolved') DEFAULT 'open',
    published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (learner_id) REFERENCES learners(learner_id) ON DELETE CASCADE
);

-- 9. HELP_PROPOSALS
CREATE TABLE IF NOT EXISTS help_proposals (
    proposal_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    mentor_id INT NOT NULL,
    proposal_status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (request_id) REFERENCES help_requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id) REFERENCES learners(learner_id) ON DELETE CASCADE
);

-- 10. SKILL_VALIDATIONS
CREATE TABLE IF NOT EXISTS skill_validations (
    validation_id INT AUTO_INCREMENT PRIMARY KEY,
    possession_id INT NOT NULL,
    supervisor_id INT NOT NULL,
    validation_status ENUM('validated', 'rejected') DEFAULT 'validated',
    validated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (possession_id) REFERENCES learner_skills(possession_id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES supervisors(supervisor_id) ON DELETE CASCADE
);

-- 11. BADGES
CREATE TABLE IF NOT EXISTS badges (
    badge_id INT AUTO_INCREMENT PRIMARY KEY,
    badge_name VARCHAR(100) NOT NULL,
    required_points INT NOT NULL,
    image VARCHAR(255),
    role VARCHAR(20) NOT NULL DEFAULT 'learner'
);

-- 12. LEARNER_BADGES
CREATE TABLE IF NOT EXISTS learner_badges (
    learner_id INT NOT NULL,
    badge_id INT NOT NULL,
    supervisor_id INT NOT NULL,
    awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (learner_id, badge_id),
    FOREIGN KEY (learner_id) REFERENCES learners(learner_id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(badge_id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES supervisors(supervisor_id) ON DELETE CASCADE
);

-- REVIEWS (kept for backward compatibility)
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reviewer_id INT NOT NULL,
    reviewed_user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
