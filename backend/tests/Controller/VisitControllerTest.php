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

    public function testGetStatsWithDayGrouping(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("day-group-test");

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
            json_encode(["pageId" => $pageId]),
        );

        // Get grouped stats
        $client->request(
            "GET",
            "/api/stats",
            ["group" => "day", "limit" => "7"],
            [],
            ["HTTP_ORIGIN" => "http://localhost:3000"],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey("day_0", $data);
        $this->assertArrayHasKey("day_6", $data);
        $this->assertCount(7, $data);

        $this->assertArrayHasKey("global", $data["day_0"]);
        $this->assertArrayHasKey("pages", $data["day_0"]);
        $this->assertArrayHasKey("uniqueVisitors", $data["day_0"]["global"]);
        $this->assertArrayHasKey("totalVisits", $data["day_0"]["global"]);
        $this->assertArrayHasKey("totalPages", $data["day_0"]["global"]);
    }

    public function testGetStatsWithWeekGrouping(): void
    {
        $client = static::createClient();

        $client->request(
            "GET",
            "/api/stats",
            ["group" => "week", "limit" => "4"],
            [],
            ["HTTP_ORIGIN" => "http://localhost:3000"],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey("week_0", $data);
        $this->assertArrayHasKey("week_3", $data);
        $this->assertCount(4, $data);
    }

    public function testGetStatsWithMonthGrouping(): void
    {
        $client = static::createClient();

        $client->request(
            "GET",
            "/api/stats",
            ["group" => "month", "limit" => "3"],
            [],
            ["HTTP_ORIGIN" => "http://localhost:3000"],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey("month_0", $data);
        $this->assertArrayHasKey("month_2", $data);
        $this->assertCount(3, $data);
    }

    public function testGetStatsGroupingLimitIsCapped(): void
    {
        $client = static::createClient();

        // Day max is 28
        $client->request(
            "GET",
            "/api/stats",
            ["group" => "day", "limit" => "100"],
            [],
            ["HTTP_ORIGIN" => "http://localhost:3000"],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(28, $data);

        // Week max is 52
        $client->request(
            "GET",
            "/api/stats",
            ["group" => "week", "limit" => "100"],
            [],
            ["HTTP_ORIGIN" => "http://localhost:3000"],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(52, $data);

        // Month max is 12
        $client->request(
            "GET",
            "/api/stats",
            ["group" => "month", "limit" => "100"],
            [],
            ["HTTP_ORIGIN" => "http://localhost:3000"],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(12, $data);
    }

    public function testGetStatsWithoutGroupReturnsNormalResponse(): void
    {
        $client = static::createClient();

        $client->request(
            "GET",
            "/api/stats",
            [],
            [],
            ["HTTP_ORIGIN" => "http://localhost:3000"],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey("global", $data);
        $this->assertArrayHasKey("pages", $data);
        $this->assertArrayHasKey("totalReactions", $data["global"]);
    }

    public function testGetStatsGroupingWithPageIdFilter(): void
    {
        $client = static::createClient();
        $pageId = $this->uniquePageId("/blog/filter-test");

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
            json_encode(["pageId" => $pageId]),
        );

        // Get grouped stats with filter
        $client->request(
            "GET",
            "/api/stats",
            ["group" => "day", "limit" => "1", "pageIdFilter" => "filter-test"],
            [],
            ["HTTP_ORIGIN" => "http://localhost:3000"],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey("day_0", $data);
        $this->assertGreaterThanOrEqual(
            1,
            $data["day_0"]["global"]["uniqueVisitors"],
        );
    }
}
