<?php

/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

declare(strict_types=1);

namespace App\Controller;

use App\Service\FingerprintService;
use App\Service\RateLimiterService;
use App\Service\ReactionService;
use App\Service\VisitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api")]
class VisitController extends AbstractController
{
    private const MAX_PAGE_ID_LENGTH = 2048;

    public function __construct(
        private VisitService $visitService,
        private ReactionService $reactionService,
        private RateLimiterService $rateLimiter,
        private FingerprintService $fingerprint,
    ) {}

    #[Route("/stats", name: "get_stats", methods: ["GET"])]
    public function getStats(Request $request): JsonResponse
    {
        $pageIdFilter = $request->query->get("pageIdFilter");

        if (
            $pageIdFilter !== null &&
            mb_strlen($pageIdFilter) > self::MAX_PAGE_ID_LENGTH
        ) {
            return new JsonResponse(
                ["error" => "pageIdFilter too long"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $global = $this->visitService->getGlobalStats($pageIdFilter);
        $pages = $this->visitService->getAllStats($pageIdFilter);

        return new JsonResponse([
            "global" => [
                "uniqueVisitors" => (int) $global["unique_visitors"],
                "totalVisits" => (int) $global["total_visits"],
                "totalPages" => (int) $global["total_pages"],
                "totalReactions" => $this->reactionService->getGlobalReactionCount(
                    $pageIdFilter,
                ),
            ],
            "pages" => array_map(
                fn($page) => [
                    "pageId" => $page["page_id"],
                    "uniqueVisitors" => (int) $page["unique_visitors"],
                    "totalVisits" => (int) $page["total_visits"],
                    "totalReactions" => $this->reactionService->getTotalReactions(
                        $page["page_id"],
                    ),
                ],
                $pages,
            ),
        ]);
    }

    #[Route("/visits", name: "get_visits", methods: ["GET"])]
    public function getVisits(Request $request): JsonResponse
    {
        $pageId = $request->query->get("pageId");

        if (!$pageId) {
            return new JsonResponse(
                ["error" => "pageId is required"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (mb_strlen($pageId) > self::MAX_PAGE_ID_LENGTH) {
            return new JsonResponse(
                ["error" => "pageId too long"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse([
            "pageId" => $pageId,
            "uniqueVisitors" => $this->visitService->getVisitCount($pageId),
            "totalVisits" => $this->visitService->getTotalVisits($pageId),
        ]);
    }

    #[Route("/visits", name: "record_visit", methods: ["POST"])]
    public function recordVisit(Request $request): JsonResponse
    {
        if (!$this->rateLimiter->isAllowed($request)) {
            return new JsonResponse(
                ["error" => "Rate limit exceeded"],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data["pageId"])) {
            return new JsonResponse(
                ["error" => "pageId is required"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $pageId = $data["pageId"];

        if (!is_string($pageId)) {
            return new JsonResponse(
                ["error" => "Invalid input type"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (mb_strlen($pageId) > self::MAX_PAGE_ID_LENGTH) {
            return new JsonResponse(
                ["error" => "pageId too long"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $fp = $this->fingerprint->getFingerprint($request);
        $recorded = $this->visitService->recordVisit($fp, $pageId);

        return new JsonResponse([
            "success" => true,
            "recorded" => $recorded,
            "uniqueVisitors" => $this->visitService->getVisitCount($pageId),
        ]);
    }
}
