

CREATE DATABASE IF NOT EXISTS nested_forum
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE nested_forum;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS discussions;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(30)  NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE discussions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(200) NOT NULL,
    body       TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_discussions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------

CREATE TABLE comments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    discussion_id INT UNSIGNED NOT NULL,
    parent_id     INT UNSIGNED NULL,
    user_id       INT UNSIGNED NOT NULL,
    body          TEXT         NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comments_discussion_id (discussion_id, id),
    KEY idx_comments_discussion_parent (discussion_id, parent_id),
    KEY idx_comments_user (user_id),
    CONSTRAINT fk_comments_discussion
        FOREIGN KEY (discussion_id) REFERENCES discussions (id),
    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_comments_parent_same_discussion
        FOREIGN KEY (discussion_id, parent_id) REFERENCES comments (discussion_id, id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Seed data (fictional people, example.test addresses, NULL password hashes)
-- ---------------------------------------------------------------------
INSERT INTO users (id, username, email, password_hash, created_at) VALUES
    (1, 'alice', 'alice@example.test', NULL, '2026-11-01 08:00:00'),
    (2, 'ben',   'ben@example.test',   NULL, '2026-11-01 08:05:00'),
    (3, 'cara',  'cara@example.test',  NULL, '2026-11-01 08:10:00'),
    (4, 'dee',   'dee@example.test',   NULL, '2026-11-01 08:15:00'),
    (5, 'eli',   'eli@example.test',   NULL, '2026-11-01 08:20:00');

INSERT INTO discussions (id, user_id, title, body, created_at) VALUES
    (1, 1, 'How do I learn PHP?',          'Share your learning tips.',                   '2026-11-02 09:00:00'),
    (2, 2, 'Which database should I try?', 'MySQL, MariaDB or something else? Thoughts?', '2026-11-02 10:00:00');


INSERT INTO comments (id, discussion_id, parent_id, user_id, body, created_at) VALUES
    (1, 1, NULL, 1, 'Start with the PHP manual.',          '2026-11-02 09:10:00'),
    (2, 1, 1,    2, 'Try the examples as you read.',       '2026-11-02 09:20:00'),
    (3, 1, 2,    3, 'Build a small project too.',          '2026-11-02 09:30:00'),
    (4, 1, 1,    4, 'Which manual section first?',         '2026-11-02 09:40:00'),
    (5, 1, NULL, 5, 'Practice SQL as well.',               '2026-11-02 09:50:00'),
    (6, 1, 3,    2, 'Start with a forum.',                 '2026-11-02 10:00:00'),
    (7, 1, 6,    5, 'Add replies next.',                   '2026-11-02 10:10:00');


INSERT INTO comments (id, discussion_id, parent_id, user_id, body, created_at) VALUES
    (8, 2, NULL, 3, 'MariaDB works fine for this lab.',    '2026-11-02 10:30:00');
