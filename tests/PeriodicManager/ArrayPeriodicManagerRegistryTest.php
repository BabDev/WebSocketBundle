<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\PeriodicManager;

use BabDev\WebSocketBundle\PeriodicManager\ArrayPeriodicManagerRegistry;
use BabDev\WebSocketBundle\PeriodicManager\Exception\ManagerAlreadyRegistered;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ArrayPeriodicManagerRegistryTest extends TestCase
{
    public function testAddsAndRemovesAManager(): void
    {
        $registry = new ArrayPeriodicManagerRegistry();

        /** @var MockObject&PeriodicManager $manager */
        $manager = $this->createMock(PeriodicManager::class);
        $manager->method('getName')
            ->willReturn('test');

        $registry->addManager($manager);

        self::assertCount(1, $registry->getManagers());

        $registry->removeManager($manager);

        self::assertCount(0, $registry->getManagers());
    }

    public function testMultipleManagersWithTheSameNameAreNotAllowed(): void
    {
        $this->expectException(ManagerAlreadyRegistered::class);
        $this->expectExceptionMessage('A manager named "test" is already registered.');

        $registry = new ArrayPeriodicManagerRegistry();

        /** @var MockObject&PeriodicManager $manager1 */
        $manager1 = $this->createMock(PeriodicManager::class);
        $manager1->method('getName')
            ->willReturn('test');

        /** @var MockObject&PeriodicManager $manager2 */
        $manager2 = $this->createMock(PeriodicManager::class);
        $manager2->method('getName')
            ->willReturn('test');

        $registry->addManager($manager1);
        $registry->addManager($manager2);
    }
}
