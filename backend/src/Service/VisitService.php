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

    /**
     * @return array{start: int, end: int}
     */
    private function getTimeRange(string $group, int $offset): array
    {
        return match ($group) {
            "day" => [
                "start" => strtotime("today midnight -$offset days"),
                "end" => strtotime("tomorrow midnight -$offset days"),
            ],
            "week" => [
                "start" => strtotime(
                    "monday this week midnight -$offset weeks",
                ),
                "end" => strtotime("monday next week midnight -$offset weeks"),
            ],
            "month" => [
                "start" => strtotime(
                    "first day of this month midnight -$offset months",
                ),
                "end" => strtotime(
                    "first day of next month midnight -$offset months",
                ),
            ],
            default => throw new \InvalidArgumentException(
                "Invalid group: $group",
            ),
        };
    }

    /**
     * @return array<string, array{unique_visitors: int, total_visits: int, total_pages: int}>
     */
    public function getGlobalStatsGrouped(
        string $group,
        int $limit,
        ?string $urlFilter = null,
    ): array {
        $results = [];

        for ($i = 0; $i < $limit; $i++) {
            $range = $this->getTimeRange($group, $i);

            if ($urlFilter !== null) {
                $stmt = $this->pdo->prepare(
                    "SELECT
                        COUNT(DISTINCT fingerprint) as unique_visitors,
                        COUNT(*) as total_visits,
                        COUNT(DISTINCT page_id) as total_pages
                    FROM visits
                    WHERE page_id LIKE ? AND visited_at >= ? AND visited_at < ?",
                );
                $stmt->execute([
                    "%" . $urlFilter . "%",
                    $range["start"],
                    $range["end"],
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    "SELECT
                        COUNT(DISTINCT fingerprint) as unique_visitors,
                        COUNT(*) as total_visits,
                        COUNT(DISTINCT page_id) as total_pages
                    FROM visits
                    WHERE visited_at >= ? AND visited_at < ?",
                );
                $stmt->execute([$range["start"], $range["end"]]);
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $results[$group . "_" . $i] = [
                "unique_visitors" => (int) $row["unique_visitors"],
                "total_visits" => (int) $row["total_visits"],
                "total_pages" => (int) $row["total_pages"],
            ];
        }

        return $results;
    }

    /**
     * @return array<string, array<int, array{page_id: string, unique_visitors: int, total_visits: int}>>
     */
    public function getAllStatsGrouped(
        string $group,
        int $limit,
        ?string $urlFilter = null,
    ): array {
        $results = [];

        for ($i = 0; $i < $limit; $i++) {
            $range = $this->getTimeRange($group, $i);

            if ($urlFilter !== null) {
                $stmt = $this->pdo->prepare(
                    "SELECT
                        page_id,
                        COUNT(DISTINCT fingerprint) as unique_visitors,
                        COUNT(*) as total_visits
                    FROM visits
                    WHERE page_id LIKE ? AND visited_at >= ? AND visited_at < ?
                    GROUP BY page_id
                    ORDER BY total_visits DESC",
                );
                $stmt->execute([
                    "%" . $urlFilter . "%",
                    $range["start"],
                    $range["end"],
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    "SELECT
                        page_id,
                        COUNT(DISTINCT fingerprint) as unique_visitors,
                        COUNT(*) as total_visits
                    FROM visits
                    WHERE visited_at >= ? AND visited_at < ?
                    GROUP BY page_id
                    ORDER BY total_visits DESC",
                );
                $stmt->execute([$range["start"], $range["end"]]);
            }

            $results[$group . "_" . $i] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $results;
    }
}
