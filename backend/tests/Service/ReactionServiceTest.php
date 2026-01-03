<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ReactionService;
use PHPUnit\Framework\TestCase;

class ReactionServiceTest extends TestCase
{
    private ReactionService $service;
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = ":memory:";
        $this->service = new ReactionService($this->dbPath);
    }

    public function testGetReactionsReturnsEmptyArrayForNewPage(): void
    {
        $reactions = $this->service->getReactions("https://example.com/page1");

        $this->assertSame([], $reactions);
    }

    public function testToggleReactionAddsNewReaction(): void
    {
        $result = $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );

        $this->assertSame("added", $result["action"]);
        $this->assertSame(1, $result["count"]);
    }

    public function testToggleReactionRemovesExistingReaction(): void
    {
        $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );
        $result = $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );

        $this->assertSame("removed", $result["action"]);
        $this->assertSame(0, $result["count"]);
    }

    public function testDifferentFingerprintsCanReact(): void
    {
        $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );
        $result = $this->service->toggleReaction(
            "fingerprint2",
            "https://example.com/page1",
            "👋",
        );

        $this->assertSame("added", $result["action"]);
        $this->assertSame(2, $result["count"]);
    }

    public function testGetUserReactionsReturnsUserEmojis(): void
    {
        $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );
        $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "🔥",
        );

        $userReactions = $this->service->getUserReactions(
            "fingerprint1",
            "https://example.com/page1",
        );

        $this->assertCount(2, $userReactions);
        $this->assertContains("👋", $userReactions);
        $this->assertContains("🔥", $userReactions);
    }

    public function testGetUserReactionsIsEmptyAfterToggleOff(): void
    {
        $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );
        $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );

        $userReactions = $this->service->getUserReactions(
            "fingerprint1",
            "https://example.com/page1",
        );

        $this->assertSame([], $userReactions);
    }

    public function testReactionsAreSeparatedByPageId(): void
    {
        $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );
        $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page2",
            "👋",
        );
        $this->service->toggleReaction(
            "fingerprint2",
            "https://example.com/page2",
            "👋",
        );

        $page1Reactions = $this->service->getReactions(
            "https://example.com/page1",
        );
        $page2Reactions = $this->service->getReactions(
            "https://example.com/page2",
        );

        $this->assertSame(1, $page1Reactions["👋"]);
        $this->assertSame(2, $page2Reactions["👋"]);
    }

    public function testCountCannotGoBelowZero(): void
    {
        $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );
        $result = $this->service->toggleReaction(
            "fingerprint1",
            "https://example.com/page1",
            "👋",
        );

        $this->assertSame(0, $result["count"]);
        $this->assertGreaterThanOrEqual(0, $result["count"]);
    }
}
