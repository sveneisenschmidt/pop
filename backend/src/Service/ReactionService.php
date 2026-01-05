<?php

/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

declare(strict_types=1);

namespace App\Service;

use PDO;

class ReactionService
{
    public function __construct(private PDO $pdo)
    {
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS reactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_id TEXT NOT NULL,
                emoji TEXT NOT NULL,
                count INTEGER DEFAULT 0,
                UNIQUE(page_id, emoji)
            )
        ');
        $this->pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_page_id ON reactions(page_id)",
        );

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS user_reactions (
                fingerprint TEXT NOT NULL,
                page_id TEXT NOT NULL,
                emoji TEXT NOT NULL,
                PRIMARY KEY(fingerprint, page_id, emoji)
            )
        ');
        $this->pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_user_page ON user_reactions(fingerprint, page_id)",
        );
    }

    public function getReactions(string $pageId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT emoji, count FROM reactions WHERE page_id = ?",
        );
        $stmt->execute([$pageId]);

        $reactions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reactions[$row["emoji"]] = (int) $row["count"];
        }

        return $reactions;
    }

    public function getUserReactions(string $fingerprint, string $pageId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT emoji FROM user_reactions WHERE fingerprint = ? AND page_id = ?",
        );
        $stmt->execute([$fingerprint, $pageId]);

        $emojis = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $emojis[] = $row["emoji"];
        }

        return $emojis;
    }

    public function toggleReaction(
        string $fingerprint,
        string $pageId,
        string $emoji,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $hasReacted = $this->hasUserReacted($fingerprint, $pageId, $emoji);

            if ($hasReacted) {
                $this->removeUserReaction($fingerprint, $pageId, $emoji);
                $this->decrementCount($pageId, $emoji);
                $action = "removed";
            } else {
                $this->addUserReaction($fingerprint, $pageId, $emoji);
                $this->incrementCount($pageId, $emoji);
                $action = "added";
            }

            $count = $this->getCount($pageId, $emoji);
            $this->pdo->commit();

            return [
                "action" => $action,
                "count" => $count,
            ];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function hasUserReacted(
        string $fingerprint,
        string $pageId,
        string $emoji,
    ): bool {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM user_reactions WHERE fingerprint = ? AND page_id = ? AND emoji = ?",
        );
        $stmt->execute([$fingerprint, $pageId, $emoji]);
        return $stmt->fetch() !== false;
    }

    private function addUserReaction(
        string $fingerprint,
        string $pageId,
        string $emoji,
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT OR IGNORE INTO user_reactions (fingerprint, page_id, emoji) VALUES (?, ?, ?)",
        );
        $stmt->execute([$fingerprint, $pageId, $emoji]);
    }

    private function removeUserReaction(
        string $fingerprint,
        string $pageId,
        string $emoji,
    ): void {
        $stmt = $this->pdo->prepare(
            "DELETE FROM user_reactions WHERE fingerprint = ? AND page_id = ? AND emoji = ?",
        );
        $stmt->execute([$fingerprint, $pageId, $emoji]);
    }

    private function incrementCount(string $pageId, string $emoji): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO reactions (page_id, emoji, count) VALUES (?, ?, 1)
            ON CONFLICT(page_id, emoji) DO UPDATE SET count = count + 1
        ');
        $stmt->execute([$pageId, $emoji]);
    }

    private function decrementCount(string $pageId, string $emoji): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE reactions SET count = MAX(0, count - 1) WHERE page_id = ? AND emoji = ?",
        );
        $stmt->execute([$pageId, $emoji]);
    }

    private function getCount(string $pageId, string $emoji): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT count FROM reactions WHERE page_id = ? AND emoji = ?",
        );
        $stmt->execute([$pageId, $emoji]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row["count"] : 0;
    }

    public function getTotalReactions(string $pageId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(count), 0) FROM reactions WHERE page_id = ?",
        );
        $stmt->execute([$pageId]);
        return (int) $stmt->fetchColumn();
    }

    public function getGlobalReactionCount(?string $urlFilter = null): int
    {
        if ($urlFilter !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(count), 0) FROM reactions WHERE page_id LIKE ?",
            );
            $stmt->execute(["%" . $urlFilter . "%"]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT COALESCE(SUM(count), 0) FROM reactions",
            );
        }
        return (int) $stmt->fetchColumn();
    }
}
