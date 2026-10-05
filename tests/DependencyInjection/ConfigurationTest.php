<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection;

use BabDev\WebSocket\Server\Server;
use BabDev\WebSocketBundle\DependencyInjection\Configuration;
use BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication\SessionAuthenticationProviderFactory;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): ConfigurationInterface
    {
        return new Configuration([new SessionAuthenticationProviderFactory()]);
    }

    public function testConfigurationIsValidWithNoUserConfiguration(): void
    {
        $this->assertConfigurationIsValid([[]]);
    }

    public function testConfigurationIsValidWithServerConfiguration(): void
    {
        $this->assertProcessedConfigurationEquals(
            [
                [
                    'server' => ['identity' => Server::VERSION, 'max_http_request_size' => 1024, 'request_timeout' => 5, 'write_buffer_limit' => 2048, 'uri' => 'tcp://127.0.0.1:8080', 'context' => ['tls' => ['verify_peer' => false]], 'allowed_origins' => ['example.com'], 'blocked_ip_addresses' => ['192.168.1.1'], 'trusted_proxies' => ['10.0.0.1'], 'trusted_headers' => ['forwarded'], 'keepalive' => ['enabled' => true, 'interval' => 60], 'periodic' => ['dbal' => ['connections' => ['database_connection'], 'interval' => 60]], 'router' => ['resource' => '%kernel.project_dir%/config/websocket_router.php'], 'session' => ['handler_service_id' => 'session.handler.test']],
                ],
            ],
            [
                'server' => ['identity' => Server::VERSION, 'max_http_request_size' => 1024, 'request_timeout' => 5, 'write_buffer_limit' => 2048, 'uri' => 'tcp://127.0.0.1:8080', 'context' => ['tls' => ['verify_peer' => false]], 'allowed_origins' => ['example.com'], 'blocked_ip_addresses' => ['192.168.1.1'], 'trusted_proxies' => ['10.0.0.1'], 'trusted_headers' => ['forwarded'], 'keepalive' => ['enabled' => true, 'interval' => 60], 'periodic' => ['dbal' => ['connections' => ['database_connection'], 'interval' => 60]], 'router' => ['resource' => '%kernel.project_dir%/config/websocket_router.php'], 'session' => ['handler_service_id' => 'session.handler.test']],
                'authentication' => [],
            ],
        );
    }

    public function testConfigurationIsValidWithEmptyStringAsIdentity(): void
    {
        $this->assertConfigurationIsValid([['server' => ['identity' => '', 'uri' => 'tcp://127.0.0.1:8080']]]);
    }

    public function testConfigurationIsInvalidWithNonStringIdentity(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['server' => ['identity' => null]]],
            'server.identity',
            'Invalid configuration for path "babdev_websocket.server.identity": The server identity must be a string',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validAllowedOrigins(): iterable
    {
        yield 'full origin' => ['https://example.com'];
        yield 'full origin with port' => ['http://localhost:8080'];
        yield 'host' => ['example.com'];
    }

    #[DataProvider('validAllowedOrigins')]
    public function testConfigurationIsValidWithAllowedOrigin(string $origin): void
    {
        $this->assertConfigurationIsValid([['server' => ['allowed_origins' => [$origin]]]], 'server.allowed_origins');
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidAllowedOrigins(): iterable
    {
        yield 'host with port' => ['localhost:8080', 'Invalid configuration for path "babdev_websocket.server.allowed_origins.0": The allowed origin "localhost:8080" is not a valid host'];
        yield 'origin with path' => ['https://example.com/path', 'Invalid configuration for path "babdev_websocket.server.allowed_origins.0": The allowed origin "https://example.com/path" is not a valid origin.'];
        yield 'non-string value' => [8080, 'Invalid type for path "babdev_websocket.server.allowed_origins.0". Expected "string", but got "int".'];
    }

    #[DataProvider('invalidAllowedOrigins')]
    public function testConfigurationIsInvalidWithAllowedOrigin(mixed $origin, string $expectedMessage): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['server' => ['allowed_origins' => [$origin]]]],
            'server.allowed_origins',
            $expectedMessage,
        );
    }

    public function testConfigurationIsValidWithTrustedProxiesAsAString(): void
    {
        $this->assertProcessedConfigurationEquals(
            [
                ['server' => ['trusted_proxies' => '127.0.0.1,REMOTE_ADDR']],
            ],
            [
                'server' => ['trusted_proxies' => ['127.0.0.1,REMOTE_ADDR']],
            ],
            'server.trusted_proxies',
        );
    }

    public function testConfigurationIsValidWithTrustedHeadersAsAString(): void
    {
        $this->assertProcessedConfigurationEquals(
            [
                ['server' => ['trusted_headers' => 'forwarded,x-forwarded-for']],
            ],
            [
                'server' => ['trusted_headers' => ['forwarded,x-forwarded-for']],
            ],
            'server.trusted_headers',
        );
    }

    public function testTheXForwardedForHeaderIsTrustedByDefault(): void
    {
        $this->assertProcessedConfigurationEquals(
            [
                [],
            ],
            [
                'server' => ['trusted_headers' => ['x-forwarded-for']],
            ],
            'server.trusted_headers',
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int|float|null}>
     */
    public static function validRequestTimeouts(): iterable
    {
        yield 'default' => [[], 10.0];
        yield 'float' => [['request_timeout' => 2.5], 2.5];
        yield 'integer' => [['request_timeout' => 5], 5];
        yield 'disabled' => [['request_timeout' => null], null];
    }

    /**
     * @param array<string, mixed> $serverConfig
     */
    #[DataProvider('validRequestTimeouts')]
    public function testConfigurationIsValidWithRequestTimeout(array $serverConfig, int|float|null $expectedTimeout): void
    {
        $this->assertProcessedConfigurationEquals(
            [['server' => $serverConfig]],
            ['server' => ['request_timeout' => $expectedTimeout]],
            'server.request_timeout',
        );
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidRequestTimeouts(): iterable
    {
        yield 'zero' => [0, 'The value 0 is too small for path "babdev_websocket.server.request_timeout". Should be greater than 0'];
        yield 'negative' => [-1.5, 'The value -1.5 is too small for path "babdev_websocket.server.request_timeout". Should be greater than 0'];
        yield 'string' => ['10 seconds', 'Invalid type for path "babdev_websocket.server.request_timeout". Expected "float", but got "string".'];
    }

    #[DataProvider('invalidRequestTimeouts')]
    public function testConfigurationIsInvalidWithRequestTimeout(mixed $timeout, string $expectedMessage): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['server' => ['request_timeout' => $timeout]]],
            'server.request_timeout',
            $expectedMessage,
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int|null}>
     */
    public static function validWriteBufferLimits(): iterable
    {
        yield 'default' => [[], 1_048_576];
        yield 'custom limit' => [['write_buffer_limit' => 512], 512];
        yield 'disabled' => [['write_buffer_limit' => null], null];
    }

    /**
     * @param array<string, mixed> $serverConfig
     */
    #[DataProvider('validWriteBufferLimits')]
    public function testConfigurationIsValidWithWriteBufferLimit(array $serverConfig, ?int $expectedLimit): void
    {
        $this->assertProcessedConfigurationEquals(
            [['server' => $serverConfig]],
            ['server' => ['write_buffer_limit' => $expectedLimit]],
            'server.write_buffer_limit',
        );
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidWriteBufferLimits(): iterable
    {
        yield 'zero' => [0, 'The value 0 is too small for path "babdev_websocket.server.write_buffer_limit". Should be greater than or equal to 1'];
        yield 'string' => ['1 MiB', 'Invalid type for path "babdev_websocket.server.write_buffer_limit". Expected "int", but got "string".'];
    }

    #[DataProvider('invalidWriteBufferLimits')]
    public function testConfigurationIsInvalidWithWriteBufferLimit(mixed $limit, string $expectedMessage): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['server' => ['write_buffer_limit' => $limit]]],
            'server.write_buffer_limit',
            $expectedMessage,
        );
    }

    public function testConfigurationIsInvalidWithNegativeRequestSize(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['server' => ['max_http_request_size' => -1]]],
            'server.max_http_request_size',
            'The value -1 is too small for path "babdev_websocket.server.max_http_request_size". Should be greater than or equal to 1',
        );
    }

    public function testConfigurationIsInvalidWithKeepaliveInterval(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['server' => ['keepalive' => ['interval' => -1]]]],
            'server.keepalive',
            'The value -1 is too small for path "babdev_websocket.server.keepalive.interval". Should be greater than or equal to 1',
        );
    }

    public function testConfigurationIsInvalidWithPeriodicDbalInterval(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['server' => ['periodic' => ['dbal' => ['interval' => -1]]]]],
            'server.periodic.dbal.interval',
            'The value -1 is too small for path "babdev_websocket.server.periodic.dbal.interval". Should be greater than or equal to 1',
        );
    }

    public function testConfigurationIsInvalidWithMultipleSessionServiceOptions(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['server' => ['session' => ['factory_service_id' => 'session.factory.test', 'handler_service_id' => 'session.handler.test']]]],
            'server.session',
            'Invalid configuration for path "babdev_websocket.server.session": You must only set one session service option',
        );
    }

    public function testConfigurationIsInvalidWithSessionAuthenticationProviderWithInvalidFirewallType(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['authentication' => ['providers' => ['session' => ['firewalls' => true]]]]],
            'authentication.providers.session.firewalls',
            'Invalid configuration for path "babdev_websocket.authentication.providers.session.firewalls": The firewalls node must be an array, a string, or null',
        );
    }
}
