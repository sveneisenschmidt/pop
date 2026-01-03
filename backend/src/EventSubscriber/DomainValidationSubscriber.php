<?php

/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class DomainValidationSubscriber implements EventSubscriberInterface
{
    private array $allowedDomains;

    public function __construct(array $allowedDomains)
    {
        $this->allowedDomains = $allowedDomains;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ["onKernelRequest", 250],
            KernelEvents::RESPONSE => ["onKernelResponse", 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isApiPath($request->getPathInfo())) {
            return;
        }

        // Handle CORS preflight
        if ($request->getMethod() === "OPTIONS") {
            $origin = $request->headers->get("Origin");
            if ($origin && $this->isAllowedDomain($origin)) {
                $response = new Response("", 204);
                $response->headers->set("Access-Control-Allow-Origin", $origin);
                $response->headers->set(
                    "Access-Control-Allow-Methods",
                    "GET, POST, OPTIONS",
                );
                $response->headers->set(
                    "Access-Control-Allow-Headers",
                    "Content-Type",
                );
                $response->headers->set("Access-Control-Max-Age", "3600");
                $event->setResponse($response);
            }
            return;
        }

        $origin = $request->headers->get("Origin");
        $referer = $request->headers->get("Referer");
        $checkDomain = $origin ?? $this->extractOriginFromReferer($referer);

        if (!$checkDomain || !$this->isAllowedDomain($checkDomain)) {
            throw new AccessDeniedHttpException("Domain not allowed");
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isApiPath($request->getPathInfo())) {
            return;
        }

        $origin = $request->headers->get("Origin");
        if ($origin && $this->isAllowedDomain($origin)) {
            $response = $event->getResponse();
            $response->headers->set("Access-Control-Allow-Origin", $origin);
        }
    }

    private function extractOriginFromReferer(?string $referer): ?string
    {
        if (!$referer) {
            return null;
        }

        $parsed = parse_url($referer);
        if (!isset($parsed["scheme"], $parsed["host"])) {
            return null;
        }

        $origin = $parsed["scheme"] . "://" . $parsed["host"];
        if (isset($parsed["port"])) {
            $origin .= ":" . $parsed["port"];
        }

        return $origin;
    }

    private function isAllowedDomain(string $origin): bool
    {
        return in_array($origin, $this->allowedDomains, true);
    }

    private function isApiPath(string $path): bool
    {
        return str_starts_with($path, "/api/reactions") ||
            str_starts_with($path, "/api/visits");
    }
}
