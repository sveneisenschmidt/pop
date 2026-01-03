<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VisitControllerTest extends WebTestCase
{
    private static int $testCounter = 0;

    private function uniquePageId(string $base): string
    {
        return $base . "-" . ++self::$testCounter . "-" . uniqid();
    }

    public function testGetVisitsRequiresPageId(): void
    {
        $client = static::createClient();
        $client->request(
            "GET",
            "/api/visits",
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

    public function testGetVisitsReturnsZeroForNewPage(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("https://example.com/empty-visits");

        $client->request(
            "GET",
            "/api/visits",
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
        $this->assertSame(0, $data["uniqueVisitors"]);
        $this->assertSame(0, $data["totalVisits"]);
    }

    public function testRecordVisitRequiresPageId(): void
    {
        $client = static::createClient();
        $client->request(
            "POST",
            "/api/visits",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            "{}",
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame("pageId is required", $data["error"]);
    }

    public function testRecordVisitRecordsNewVisit(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("https://example.com/visit-test");

        $client->request(
            "POST",
            "/api/visits",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
            ]),
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data["success"]);
        $this->assertTrue($data["recorded"]);
        $this->assertSame(1, $data["uniqueVisitors"]);
    }

    public function testRecordVisitDeduplicates(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("https://example.com/dedup-test");

        // First visit
        $client->request(
            "POST",
            "/api/visits",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
            ]),
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data["recorded"]);
        $this->assertSame(1, $data["uniqueVisitors"]);

        // Second visit (same fingerprint, should be deduplicated)
        $client->request(
            "POST",
            "/api/visits",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
            ]),
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data["recorded"]);
        $this->assertSame(1, $data["uniqueVisitors"]);
    }

    public function testRecordVisitRejectsLongPageId(): void
    {
        $client = static::createClient();
        $longPageId = str_repeat("a", 2049);

        $client->request(
            "POST",
            "/api/visits",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $longPageId,
            ]),
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame("pageId too long", $data["error"]);
    }

    public function testGetVisitsRejectsLongPageId(): void
    {
        $client = static::createClient();
        $longPageId = str_repeat("a", 2049);

        $client->request(
            "GET",
            "/api/visits",
            [
                "pageId" => $longPageId,
            ],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
            ],
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame("pageId too long", $data["error"]);
    }

    public function testGetVisitsReturnsCorrectCountAfterRecording(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("https://example.com/count-test");

        // Record a visit
        $client->request(
            "POST",
            "/api/visits",
            [],
            [],
            [
                "HTTP_ORIGIN" => "http://localhost:3000",
                "CONTENT_TYPE" => "application/json",
            ],
            json_encode([
                "pageId" => $pageId,
            ]),
        );

        // Get visits
        $client->request(
            "GET",
            "/api/visits",
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
        $this->assertSame(1, $data["uniqueVisitors"]);
        $this->assertSame(1, $data["totalVisits"]);
    }
}
