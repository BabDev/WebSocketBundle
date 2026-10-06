<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Fixtures\Routing;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;

#[AsMessageHandler(path: '/debug', env: 'dev')]
final class DevelopmentMessageHandler {}
