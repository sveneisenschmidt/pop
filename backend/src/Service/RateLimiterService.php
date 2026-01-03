<?php

/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

declare(strict_types=1);

namespace App\Service;

use PDO;
use Symfony\Component\HttpFoundation\Request;

class RateLimiterService
{
    private const MAX_REQUESTS = 10;
    private const WINDOW_SECONDS = 60;

    private PDO $pdo;
    private bool $enabled;

    public function __construct(
        string $databasePath,
        private FingerprintService $fingerprint,
        bool $enabled = true,
    ) {
        $this->enabled = $enabled;
        $this->pdo = new PDO("sqlite:" . $databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS rate_limits (
                fingerprint TEXT PRIMARY KEY,
                request_count INTEGER DEFAULT 0,
                window_start INTEGER NOT NULL
            )
        ');
    }

    public function isAllowed(Request $request): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $fp = $this->fingerprint->getFingerprint($request);
        $now = time();
        $windowStart = $now - self::WINDOW_SECONDS;

        // Clean old entries
        $stmt = $this->pdo->prepare(
            "DELETE FROM rate_limits WHERE window_start < ?",
        );
        $stmt->execute([$windowStart]);

        $stmt = $this->pdo->prepare(
            "SELECT request_count, window_start FROM rate_limits WHERE fingerprint = ?",
        );
        $stmt->execute([$fp]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO rate_limits (fingerprint, request_count, window_start) VALUES (?, 1, ?)",
            );
            $stmt->execute([$fp, $now]);
            return true;
        }

        if ($row["request_count"] >= self::MAX_REQUESTS) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE rate_limits SET request_count = request_count + 1 WHERE fingerprint = ?",
        );
        $stmt->execute([$fp]);

        return true;
    }
}
