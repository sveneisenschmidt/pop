<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\VisitService;
use PDO;
use PHPUnit\Framework\TestCase;

class VisitServiceTest extends TestCase
{
    private VisitService $service;

    protected function setUp(): void
    {
        $pdo = new PDO("sqlite::memory:");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Use 5 second dedup for faster tests
        $this->service = new VisitService($pdo, 5);
    }

    public function testRecordVisitReturnsTrue(): void
    {
        $result = $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );

        $this->assertTrue($result);
    }

    public function testRecordVisitReturnsFalseForDuplicate(): void
    {
        $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );
        $result = $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );

        $this->assertFalse($result);
    }

    public function testRecordVisitAllowsDifferentFingerprints(): void
    {
        $result1 = $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );
        $result2 = $this->service->recordVisit(
            "fingerprint2",
            "https://example.com/page1",
        );

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    public function testRecordVisitAllowsSameFingerprintDifferentPages(): void
    {
        $result1 = $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );
        $result2 = $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page2",
        );

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    public function testGetVisitCountReturnsZeroForNewPage(): void
    {
        $count = $this->service->getVisitCount("https://example.com/new-page");

        $this->assertSame(0, $count);
    }

    public function testGetVisitCountReturnsUniqueVisitors(): void
    {
        $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );
        $this->service->recordVisit(
            "fingerprint2",
            "https://example.com/page1",
        );
        $this->service->recordVisit(
            "fingerprint3",
            "https://example.com/page1",
        );

        $count = $this->service->getVisitCount("https://example.com/page1");

        $this->assertSame(3, $count);
    }

    public function testGetTotalVisitsReturnsZeroForNewPage(): void
    {
        $count = $this->service->getTotalVisits("https://example.com/new-page");

        $this->assertSame(0, $count);
    }

    public function testGetTotalVisitsReturnsTotalCount(): void
    {
        $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );
        $this->service->recordVisit(
            "fingerprint2",
            "https://example.com/page1",
        );

        $count = $this->service->getTotalVisits("https://example.com/page1");

        $this->assertSame(2, $count);
    }

    public function testVisitsAreSeparatedByPageId(): void
    {
        $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );
        $this->service->recordVisit(
            "fingerprint2",
            "https://example.com/page1",
        );
        $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page2",
        );

        $page1Count = $this->service->getVisitCount(
            "https://example.com/page1",
        );
        $page2Count = $this->service->getVisitCount(
            "https://example.com/page2",
        );

        $this->assertSame(2, $page1Count);
        $this->assertSame(1, $page2Count);
    }

    public function testDedupWindowPreventsMultipleCountsForSameVisitor(): void
    {
        // First visit
        $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );

        // Try to visit again immediately (within 5 second dedup window)
        $result = $this->service->recordVisit(
            "fingerprint1",
            "https://example.com/page1",
        );

        $this->assertFalse($result);
        $this->assertSame(
            1,
            $this->service->getVisitCount("https://example.com/page1"),
        );
    }

    public function testGetGlobalStatsGroupedByDayReturnsCorrectStructure(): void
    {
        $this->service->recordVisit("fp1", "page1");
        $this->service->recordVisit("fp2", "page1");
        $this->service->recordVisit("fp1", "page2");

        $result = $this->service->getGlobalStatsGrouped("day", 7);

        $this->assertCount(7, $result);
        $this->assertArrayHasKey("day_0", $result);
        $this->assertArrayHasKey("day_6", $result);

        // Today should have our visits
        $this->assertSame(2, $result["day_0"]["unique_visitors"]);
        $this->assertSame(3, $result["day_0"]["total_visits"]);
        $this->assertSame(2, $result["day_0"]["total_pages"]);

        // Yesterday should be empty
        $this->assertSame(0, $result["day_1"]["unique_visitors"]);
        $this->assertSame(0, $result["day_1"]["total_visits"]);
    }

    public function testGetGlobalStatsGroupedByWeekReturnsCorrectStructure(): void
    {
        $this->service->recordVisit("fp1", "page1");

        $result = $this->service->getGlobalStatsGrouped("week", 4);

        $this->assertCount(4, $result);
        $this->assertArrayHasKey("week_0", $result);
        $this->assertArrayHasKey("week_3", $result);

        // This week should have our visit
        $this->assertSame(1, $result["week_0"]["unique_visitors"]);
        $this->assertSame(1, $result["week_0"]["total_visits"]);
    }

    public function testGetGlobalStatsGroupedByMonthReturnsCorrectStructure(): void
    {
        $this->service->recordVisit("fp1", "page1");

        $result = $this->service->getGlobalStatsGrouped("month", 3);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey("month_0", $result);
        $this->assertArrayHasKey("month_2", $result);

        // This month should have our visit
        $this->assertSame(1, $result["month_0"]["unique_visitors"]);
        $this->assertSame(1, $result["month_0"]["total_visits"]);
    }

    public function testGetAllStatsGroupedByDayReturnsCorrectStructure(): void
    {
        $this->service->recordVisit("fp1", "page1");
        $this->service->recordVisit("fp2", "page1");
        $this->service->recordVisit("fp1", "page2");

        $result = $this->service->getAllStatsGrouped("day", 7);

        $this->assertCount(7, $result);
        $this->assertArrayHasKey("day_0", $result);

        // Today should have 2 pages
        $this->assertCount(2, $result["day_0"]);
        $this->assertSame("page1", $result["day_0"][0]["page_id"]);
        $this->assertSame(2, (int) $result["day_0"][0]["unique_visitors"]);
        $this->assertSame(2, (int) $result["day_0"][0]["total_visits"]);

        // Yesterday should be empty
        $this->assertCount(0, $result["day_1"]);
    }

    public function testGetGlobalStatsGroupedWithUrlFilter(): void
    {
        $this->service->recordVisit("fp1", "/blog/post1");
        $this->service->recordVisit("fp2", "/blog/post2");
        $this->service->recordVisit("fp3", "/about");

        $result = $this->service->getGlobalStatsGrouped("day", 1, "blog");

        $this->assertSame(2, $result["day_0"]["unique_visitors"]);
        $this->assertSame(2, $result["day_0"]["total_visits"]);
        $this->assertSame(2, $result["day_0"]["total_pages"]);
    }

    public function testGetAllStatsGroupedWithUrlFilter(): void
    {
        $this->service->recordVisit("fp1", "/blog/post1");
        $this->service->recordVisit("fp2", "/blog/post2");
        $this->service->recordVisit("fp3", "/about");

        $result = $this->service->getAllStatsGrouped("day", 1, "blog");

        $this->assertCount(2, $result["day_0"]);
    }
}
