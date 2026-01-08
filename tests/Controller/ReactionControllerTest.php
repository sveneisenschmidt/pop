<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReactionControllerTest extends WebTestCase
{
    private static int $testCounter = 0;

    private function uniquePageId(string $base): string
    {
        return $base . "-" . ++self::$testCounter . "-" . uniqid();
    }

    public function testGetReactionsRequiresPageId(): void
    {
        $client = static::createClient();
        $client->request(
            "GET",
            "/api/reactions",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
            ],
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame("pageId is required", $data["error"]);
    }

    public function testGetReactionsReturnsEmptyForNewPage(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("https://example.com/empty-test");

        $client->request(
            "GET",
            "/api/reactions",
            [
                "pageId" => $pageId,
            ],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
            ],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($pageId, $data["pageId"]);
        $this->assertSame([], $data["reactions"]);
        $this->assertSame([], $data["userReactions"]);
    }

    public function testToggleReactionRequiresPageIdAndEmoji(): void
    {
        $client = static::createClient();
        $client->request(
            "POST",
            "/api/reactions",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            "{}",
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testToggleReactionAddsReaction(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("https://example.com/add-test");

        $client->request(
            "POST",
            "/api/reactions",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
                "emoji" => "👋",
            ]),
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data["success"]);
        $this->assertSame("added", $data["action"]);
        $this->assertSame(1, $data["count"]);
    }

    public function testToggleReactionRemovesReaction(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("https://example.com/toggle-test");

        // First toggle - adds
        $client->request(
            "POST",
            "/api/reactions",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
                "emoji" => "🔥",
            ]),
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame("added", $data["action"]);
        $this->assertSame(1, $data["count"]);

        // Second toggle - removes
        $client->request(
            "POST",
            "/api/reactions",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
                "emoji" => "🔥",
            ]),
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame("removed", $data["action"]);
        $this->assertSame(0, $data["count"]);
    }

    public function testToggleReactionRejectsInvalidEmoji(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("https://example.com/invalid-test");

        $client->request(
            "POST",
            "/api/reactions",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
                "emoji" => "this is way too long to be an emoji string",
            ]),
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetReactionsIncludesUserReactions(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId(
            "https://example.com/user-reactions-test",
        );

        // Add a reaction
        $client->request(
            "POST",
            "/api/reactions",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
                "emoji" => "👋",
            ]),
        );

        // Get reactions
        $client->request(
            "GET",
            "/api/reactions",
            [
                "pageId" => $pageId,
            ],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
            ],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(["👋" => 1], $data["reactions"]);
        $this->assertSame(["👋"], $data["userReactions"]);
    }

    public function testCountCannotGoBelowZero(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("https://example.com/zero-test");

        // Add reaction
        $client->request(
            "POST",
            "/api/reactions",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
                "emoji" => "❤️",
            ]),
        );

        // Remove reaction
        $client->request(
            "POST",
            "/api/reactions",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
                "emoji" => "❤️",
            ]),
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(0, $data["count"]);
        $this->assertGreaterThanOrEqual(0, $data["count"]);
    }
}
