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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api")]
class ReactionController extends AbstractController
{
    private const MAX_PAGE_ID_LENGTH = 2048;

    public function __construct(
        private ReactionService $reactionService,
        private RateLimiterService $rateLimiter,
        private FingerprintService $fingerprint,
    ) {}

    #[Route("/reactions", name: "get_reactions", methods: ["GET"])]
    public function getReactions(Request $request): JsonResponse
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

        $fp = $this->fingerprint->getFingerprint($request);
        $reactions = $this->reactionService->getReactions($pageId);
        $userReactions = $this->reactionService->getUserReactions($fp, $pageId);

        return new JsonResponse([
            "pageId" => $pageId,
            "reactions" => $reactions,
            "userReactions" => $userReactions,
        ]);
    }

    #[Route("/reactions", name: "toggle_reaction", methods: ["POST"])]
    public function toggleReaction(Request $request): JsonResponse
    {
        if (!$this->rateLimiter->isAllowed($request)) {
            return new JsonResponse(
                ["error" => "Rate limit exceeded"],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data["pageId"]) || !isset($data["emoji"])) {
            return new JsonResponse(
                ["error" => "pageId and emoji are required"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $pageId = $data["pageId"];
        $emoji = $data["emoji"];

        if (!is_string($pageId) || !is_string($emoji)) {
            return new JsonResponse(
                ["error" => "Invalid input types"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (mb_strlen($pageId) > self::MAX_PAGE_ID_LENGTH) {
            return new JsonResponse(
                ["error" => "pageId too long"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (mb_strlen($emoji) > 10) {
            return new JsonResponse(
                ["error" => "Invalid emoji"],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $fp = $this->fingerprint->getFingerprint($request);
        $result = $this->reactionService->toggleReaction($fp, $pageId, $emoji);

        return new JsonResponse([
            "success" => true,
            "action" => $result["action"],
            "count" => $result["count"],
        ]);
    }
}
