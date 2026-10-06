<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Fixtures\Routing;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;

#[AsMessageHandler(path: '/echo')]
final class SimpleMessageHandler {}
