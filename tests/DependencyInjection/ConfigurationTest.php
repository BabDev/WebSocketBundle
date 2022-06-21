<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection;

use BabDev\WebSocketBundle\DependencyInjection\Configuration;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): ConfigurationInterface
    {
        return new Configuration();
    }

    public function testConfigurationIsValidWithNoUserConfiguration(): void
    {
        $this->assertConfigurationIsValid([[]]);
    }

    public function testConfigurationIsValidWithServerConfiguration(): void
    {
        $this->assertProcessedConfigurationEquals(
            [['server' => ['uri' => 'tcp://127.0.0.1:8080', 'context' => ['tls' => ['verify_peer' => false]], 'allowed_origins' => ['example.com'], 'blocked_ip_addresses' => ['192.168.1.1'], 'session' => ['handler_service_id' => 'session.handler.test']]]],
            ['server' => ['uri' => 'tcp://127.0.0.1:8080', 'context' => ['tls' => ['verify_peer' => false]], 'allowed_origins' => ['example.com'], 'blocked_ip_addresses' => ['192.168.1.1'], 'session' => ['handler_service_id' => 'session.handler.test']]],
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
}
