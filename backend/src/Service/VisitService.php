<?php

/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

declare(strict_types=1);

namespace App\Service;

use PDO;

class VisitService
{
    private const DEFAULT_DEDUP_SECONDS = 1800; // 30 minutes

    public function __construct(
        private PDO $pdo,
        private int $dedupSeconds = self::DEFAULT_DEDUP_SECONDS,
    ) {
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS visits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fingerprint TEXT NOT NULL,
                page_id TEXT NOT NULL,
                visited_at INTEGER NOT NULL
            )
        ');
        $this->pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_visits_page ON visits(page_id)",
        );
        $this->pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_visits_dedup ON visits(fingerprint, page_id, visited_at)",
        );
    }

    public function recordVisit(string $fingerprint, string $pageId): bool
    {
        $now = time();
        $dedupWindow = $now - $this->dedupSeconds;

        // Check if visitor was already counted in dedup window
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM visits WHERE fingerprint = ? AND page_id = ? AND visited_at > ?",
        );
        $stmt->execute([$fingerprint, $pageId, $dedupWindow]);

        if ($stmt->fetch() !== false) {
            return false; // Already visited recently
        }

        // Record new visit
        $stmt = $this->pdo->prepare(
            "INSERT INTO visits (fingerprint, page_id, visited_at) VALUES (?, ?, ?)",
        );
        $stmt->execute([$fingerprint, $pageId, $now]);

        return true;
    }

    public function getVisitCount(string $pageId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT fingerprint) FROM visits WHERE page_id = ?",
        );
        $stmt->execute([$pageId]);

        return (int) $stmt->fetchColumn();
    }

    public function getTotalVisits(string $pageId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM visits WHERE page_id = ?",
        );
        $stmt->execute([$pageId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array{page_id: string, unique_visitors: int, total_visits: int}>
     */
    public function getAllStats(?string $urlFilter = null): array
    {
        if ($urlFilter !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT
                    page_id,
                    COUNT(DISTINCT fingerprint) as unique_visitors,
                    COUNT(*) as total_visits
                FROM visits
                WHERE page_id LIKE ?
                GROUP BY page_id
                ORDER BY total_visits DESC",
            );
            $stmt->execute(["%" . $urlFilter . "%"]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT
                    page_id,
                    COUNT(DISTINCT fingerprint) as unique_visitors,
                    COUNT(*) as total_visits
                FROM visits
                GROUP BY page_id
                ORDER BY total_visits DESC",
            );
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGlobalStats(?string $urlFilter = null): array
    {
        if ($urlFilter !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT
                    COUNT(DISTINCT fingerprint) as unique_visitors,
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT page_id) as total_pages
                FROM visits
                WHERE page_id LIKE ?",
            );
            $stmt->execute(["%" . $urlFilter . "%"]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT
                    COUNT(DISTINCT fingerprint) as unique_visitors,
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT page_id) as total_pages
                FROM visits",
            );
        }

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
