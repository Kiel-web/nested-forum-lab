<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;


final class User
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

  
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, email FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }


    public function findForLogin(string $usernameOrEmail): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, email, password_hash FROM users WHERE username = :u OR email = :e LIMIT 1'
        );
        $stmt->execute([':u' => $usernameOrEmail, ':e' => $usernameOrEmail]);
        return $stmt->fetch() ?: null;
    }

    public function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        return (bool) $stmt->fetchColumn();
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    /** @throws \PDOException with SQLSTATE 23000 if username/email is already taken (race condition) */
    public function create(string $username, string $email, string $passwordHash): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash) VALUES (:u, :e, :h)'
        );
        $stmt->execute([':u' => $username, ':e' => $email, ':h' => $passwordHash]);
        return (int) $this->db->lastInsertId();
    }
}
