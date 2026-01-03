<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\VisitService;
use PHPUnit\Framework\TestCase;

class VisitServiceTest extends TestCase
{
    private VisitService $service;

    protected function setUp(): void
    {
        // Use 5 second dedup for faster tests
        $this->service = new VisitService(":memory:", 5);
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
        $count = $this->service->getTotalVisits(
            "https://example.com/new-page",
        );

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
}
