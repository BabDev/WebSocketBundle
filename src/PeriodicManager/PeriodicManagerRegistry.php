<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\PeriodicManager;

use BabDev\WebSocketBundle\PeriodicManager\Exception\ManagerAlreadyRegistered;

interface PeriodicManagerRegistry
{
    /**
     * @throws ManagerAlreadyRegistered if a manager with the same name as the given manager is already registered
     */
    public function addManager(PeriodicManager $manager): void;

    /**
     * @return array<string, PeriodicManager>
     */
    public function getManagers(): array;

    public function removeManager(PeriodicManager $manager): void;
}
