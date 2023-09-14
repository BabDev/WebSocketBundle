<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection;

use BabDev\WebSocket\Server\Server;
use BabDev\WebSocketBundle\DependencyInjection\Configuration;
use BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication\SessionAuthenticationProviderFactory;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
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
                    'server' => ['identity' => Server::VERSION, 'uri' => 'tcp://127.0.0.1:8080', 'context' => ['tls' => ['verify_peer' => false]], 'allowed_origins' => ['example.com'], 'blocked_ip_addresses' => ['192.168.1.1'], 'keepalive' => ['enabled' => true, 'interval' => 60], 'periodic' => ['dbal' => ['connections' => ['database_connection'], 'interval' => 60]], 'router' => ['resource' => '%kernel.project_dir%/config/websocket_router.php'], 'session' => ['handler_service_id' => 'session.handler.test']],
                ],
            ],
            [
                'server' => ['identity' => Server::VERSION, 'uri' => 'tcp://127.0.0.1:8080', 'context' => ['tls' => ['verify_peer' => false]], 'allowed_origins' => ['example.com'], 'blocked_ip_addresses' => ['192.168.1.1'], 'keepalive' => ['enabled' => true, 'interval' => 60], 'periodic' => ['dbal' => ['connections' => ['database_connection'], 'interval' => 60]], 'router' => ['resource' => '%kernel.project_dir%/config/websocket_router.php'], 'session' => ['handler_service_id' => 'session.handler.test']],
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
