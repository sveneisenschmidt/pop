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
        return hash(
            "sha256",
            implode("|", [
                $request->getClientIp() ?? "",
                $request->headers->get("User-Agent", ""),
                $request->headers->get("Accept-Language", ""),
            ]),
        );
    }
}
