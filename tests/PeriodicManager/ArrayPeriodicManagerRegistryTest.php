<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\PeriodicManager;

use BabDev\WebSocketBundle\PeriodicManager\ArrayPeriodicManagerRegistry;
use BabDev\WebSocketBundle\PeriodicManager\Exception\ManagerAlreadyRegistered;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManager;
use PHPUnit\Framework\TestCase;

final class ArrayPeriodicManagerRegistryTest extends TestCase
{
    public function testAddsAndRemovesAManager(): void
    {
        $registry = new ArrayPeriodicManagerRegistry();

        $manager = self::createStub(PeriodicManager::class);
        $manager->method('getName')
            ->willReturn('test');

        $registry->addManager($manager);

        self::assertCount(1, $registry->getManagers());

        $registry->removeManager($manager);

        self::assertEmpty($registry->getManagers());
    }

    public function testMultipleManagersWithTheSameNameAreNotAllowed(): void
    {
        $this->expectException(ManagerAlreadyRegistered::class);
        $this->expectExceptionMessage('A manager named "test" is already registered.');

        $registry = new ArrayPeriodicManagerRegistry();

        $manager1 = self::createStub(PeriodicManager::class);
        $manager1->method('getName')
            ->willReturn('test');

        $manager2 = self::createStub(PeriodicManager::class);
        $manager2->method('getName')
            ->willReturn('test');

        $registry->addManager($manager1);
        $registry->addManager($manager2);
    }
}
