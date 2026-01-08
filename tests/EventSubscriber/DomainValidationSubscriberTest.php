<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\DomainValidationSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class DomainValidationSubscriberTest extends TestCase
{
    private function createRequestEvent(string $path, ?string $origin = null, ?string $referer = null): RequestEvent
    {
        $request = Request::create($path);
        if ($origin) {
            $request->headers->set('Origin', $origin);
        }
        if ($referer) {
            $request->headers->set('Referer', $referer);
        }

        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testSubscribesToRequestEvent(): void
    {
        $events = DomainValidationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function testAllowsRequestWithValidOrigin(): void
    {
        $subscriber = new DomainValidationSubscriber(['https://blog.example.com']);
        $event = $this->createRequestEvent('/api/reactions', 'https://blog.example.com');

        $subscriber->onKernelRequest($event);

        $this->assertTrue(true); // No exception thrown
    }

    public function testAllowsRequestWithValidReferer(): void
    {
        $subscriber = new DomainValidationSubscriber(['https://blog.example.com']);
        $event = $this->createRequestEvent('/api/reactions', null, 'https://blog.example.com/some/page');

        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }

    public function testRejectsRequestWithInvalidOrigin(): void
    {
        $subscriber = new DomainValidationSubscriber(['https://blog.example.com']);
        $event = $this->createRequestEvent('/api/reactions', 'https://evil.com');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Domain not allowed');

        $subscriber->onKernelRequest($event);
    }

    public function testRejectsRequestWithNoOriginOrReferer(): void
    {
        $subscriber = new DomainValidationSubscriber(['https://blog.example.com']);
        $event = $this->createRequestEvent('/api/reactions');

        $this->expectException(AccessDeniedHttpException::class);

        $subscriber->onKernelRequest($event);
    }

    public function testIgnoresNonApiPaths(): void
    {
        $subscriber = new DomainValidationSubscriber(['https://blog.example.com']);
        $event = $this->createRequestEvent('/other/path');

        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }

    public function testHandlesRefererWithPort(): void
    {
        $subscriber = new DomainValidationSubscriber(['http://localhost:3000']);
        $event = $this->createRequestEvent('/api/reactions', null, 'http://localhost:3000/page');

        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }

    public function testPrefersOriginOverReferer(): void
    {
        $subscriber = new DomainValidationSubscriber(['https://allowed.com']);
        $event = $this->createRequestEvent(
            '/api/reactions',
            'https://allowed.com',
            'https://other.com/page'
        );

        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }
}
