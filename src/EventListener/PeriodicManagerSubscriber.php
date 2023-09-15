<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\EventListener;

use BabDev\WebSocketBundle\Event\AfterServerClosed;
use BabDev\WebSocketBundle\Event\BeforeRunServer;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final readonly class PeriodicManagerSubscriber implements EventSubscriberInterface
{
    public function __construct(private PeriodicManagerRegistry $registry) {}

    public static function getSubscribedEvents(): array
    {
        return [
            AfterServerClosed::class => 'onAfterServerClosed',
            BeforeRunServer::class => 'onBeforeRunServer',
        ];
    }

    public function onAfterServerClosed(AfterServerClosed $event): void
    {
        foreach ($this->registry->getManagers() as $manager) {
            $manager->cancelTimers();
        }
    }

    public function onBeforeRunServer(BeforeRunServer $event): void
    {
        $loop = $event->loop;

        foreach ($this->registry->getManagers() as $manager) {
            $manager->setLoop($loop);
            $manager->register();
        }
    }
}
