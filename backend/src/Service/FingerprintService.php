<?php

/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class FingerprintService
{
    public function getFingerprint(Request $request): string
    {
        $tlsFingerprint = $this->getTlsFingerprint($request);

        return hash(
            "sha256",
            implode("|", [
                $request->getClientIp() ?? "",
                $request->headers->get("User-Agent", ""),
                $request->headers->get("Accept-Language", ""),
                $tlsFingerprint ?? "",
            ]),
        );
    }

    private function getTlsFingerprint(Request $request): ?string
    {
        // Cloudflare JA4 (preferred, newer format)
        $ja4 = $request->headers->get("Cf-Ja4");
        if ($ja4) {
            return hash("sha256", $ja4);
        }

        // Cloudflare JA3 hash
        $ja3Hash = $request->headers->get("Cf-Ja3-Hash");
        if ($ja3Hash) {
            return hash("sha256", $ja3Hash);
        }

        // Cloudflare raw JA3
        $ja3 = $request->headers->get("Cf-Ja3");
        if ($ja3) {
            return hash("sha256", $ja3);
        }

        return null;
    }
}
