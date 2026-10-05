<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Server\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Exception\InvalidConfiguration;
use BabDev\WebSocketBundle\Server\Middleware\ResolveForwardedClientAddressFactory;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResolveForwardedClientAddressFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, list<string>, array<string, string>, string}>
     */
    public static function trustedProxyConfigurations(): iterable
    {
        yield 'list of proxies' => [['192.0.2.1', '10.0.0.1'], ['x-forwarded-for'], ['X-Forwarded-For' => '203.0.113.9'], '203.0.113.9'];

        yield 'comma-separated proxies' => [['192.0.2.1, 10.0.0.1'], ['x-forwarded-for'], ['X-Forwarded-For' => '203.0.113.9'], '203.0.113.9'];

        yield 'trusts the remote address' => [['REMOTE_ADDR'], ['x-forwarded-for'], ['X-Forwarded-For' => '203.0.113.9'], '203.0.113.9'];

        yield 'trusts private subnets' => [['PRIVATE_SUBNETS'], ['x-forwarded-for'], ['X-Forwarded-For' => '203.0.113.9'], '203.0.113.9'];

        yield 'forwarded header' => [['10.0.0.1'], ['forwarded'], ['Forwarded' => 'for=203.0.113.9'], '203.0.113.9'];

        yield 'comma-separated headers' => [['10.0.0.1'], ['x-forwarded-proto,forwarded'], ['Forwarded' => 'for=203.0.113.9'], '203.0.113.9'];

        yield 'untrusted header is ignored' => [['10.0.0.1'], ['forwarded'], ['X-Forwarded-For' => '203.0.113.9'], '10.0.0.1'];

        yield 'untrusted proxy is ignored' => [['192.0.2.1'], ['x-forwarded-for'], ['X-Forwarded-For' => '203.0.113.9'], '10.0.0.1'];
    }

    /**
     * @param list<string>          $trustedProxies
     * @param list<string>          $trustedHeaders
     * @param array<string, string> $requestHeaders
     */
    #[DataProvider('trustedProxyConfigurations')]
    public function testTheMiddlewareIsCreatedFromTheConfiguration(array $trustedProxies, array $trustedHeaders, array $requestHeaders, string $expectedAddress): void
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::HTTP_REQUEST, new Request('GET', '/', $requestHeaders));
        $attributeStore->set(AttributeKey::REMOTE_ADDRESS, '10.0.0.1');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $decoratedMiddleware->expects(self::once())
            ->method('onOpen')
            ->with($connection);

        ResolveForwardedClientAddressFactory::create($decoratedMiddleware, $trustedProxies, $trustedHeaders)->onOpen($connection);

        self::assertSame($expectedAddress, $attributeStore->get(AttributeKey::REMOTE_ADDRESS));
    }

    public function testAnUnsupportedTrustedHeaderIsRejected(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('The trusted header "x-real-ip" is not supported.');

        ResolveForwardedClientAddressFactory::create(self::createStub(ServerMiddleware::class), ['10.0.0.1'], ['x-real-ip']);
    }
}
