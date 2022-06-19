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
            [['server' => ['uri' => 'tcp://localhost:8080', 'context' => ['tls' => ['verify_peer' => false]]]]],
            ['server' => ['uri' => 'tcp://localhost:8080', 'context' => ['tls' => ['verify_peer' => false]]]],
        );
    }
}
