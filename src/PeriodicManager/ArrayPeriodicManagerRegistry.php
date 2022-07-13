<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\PeriodicManager;

use BabDev\WebSocketBundle\PeriodicManager\Exception\ManagerAlreadyRegistered;

final class ArrayPeriodicManagerRegistry implements PeriodicManagerRegistry
{
    /**
     * @var array<string, PeriodicManager>
     */
    private array $managers = [];

    /**
     * @param iterable<PeriodicManager> $managers
     */
    public function __construct(iterable $managers = [])
    {
        foreach ($managers as $manager) {
            $this->addManager($manager);
        }
    }

    /**
     * @throws ManagerAlreadyRegistered if a manager with the same name as the given manager is already registered
     */
    public function addManager(PeriodicManager $manager): void
    {
        if (isset($this->managers[$manager->getName()])) {
            throw new ManagerAlreadyRegistered(sprintf('A manager named "%s" is already registered.', $manager->getName()));
        }

        $this->managers[$manager->getName()] = $manager;
    }

    /**
     * @return array<string, PeriodicManager>
     */
    public function getManagers(): array
    {
        return $this->managers;
    }

    public function removeManager(PeriodicManager $manager): void
    {
        unset($this->managers[$manager->getName()]);
    }
}
