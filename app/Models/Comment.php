<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;


final class Comment
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

    public function forDiscussion(int $discussionId, int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.parent_id, c.body, c.created_at, u.username AS author
               FROM comments c
               JOIN users u ON u.id = c.user_id
              WHERE c.discussion_id = :d
              ORDER BY c.created_at ASC, c.id ASC
              LIMIT :lim'
        );
        $stmt->bindValue(':d', $discussionId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit + 1, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }


    public function findInDiscussion(int $commentId, int $discussionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, parent_id FROM comments WHERE id = :id AND discussion_id = :d'
        );
        $stmt->execute([':id' => $commentId, ':d' => $discussionId]);
        return $stmt->fetch() ?: null;
    }

    public function depthOf(int $commentId, int $maxSteps): ?int
    {
        $stmt = $this->db->prepare('SELECT parent_id FROM comments WHERE id = :id');
        $depth = 0;
        $current = $commentId;
        while (true) {
            $stmt->execute([':id' => $current]);
            $row = $stmt->fetch();
            if ($row === false) {
                return null;
            }
            if ($row['parent_id'] === null) {
                return $depth;
            }
            if (++$depth > $maxSteps) {
                return null;
            }
            $current = (int) $row['parent_id'];
        }
    }

    public function create(int $discussionId, int $userId, ?int $parentId, string $body): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO comments (discussion_id, parent_id, user_id, body)
             VALUES (:d, :p, :u, :b)'
        );
        $stmt->bindValue(':d', $discussionId, PDO::PARAM_INT);
        $stmt->bindValue(':p', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':u', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':b', $body, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }
}
