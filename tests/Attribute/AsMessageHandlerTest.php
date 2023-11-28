<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Attribute;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;
use PHPUnit\Framework\TestCase;

final class AsMessageHandlerTest extends TestCase
{
    public function testProxiesMethodCallsToRouteAttributeClass(): void
    {
        $attribute = new AsMessageHandler(
            path: $path = '/testing/1/2/3',
            priority: $priority = 25,
        );

        self::assertSame($path, $attribute->getPath());
        self::assertSame($priority, $attribute->getPriority());

        $attribute->setName($name = 'test_handler');

        self::assertSame($name, $attribute->getName());
    }

    public function testRaisesErrorCallingUnknownMethod(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(sprintf('Call to undefined method %s::test().', AsMessageHandler::class));

        /** @phpstan-ignore-next-line */
        (new AsMessageHandler())->test();
    }
}
