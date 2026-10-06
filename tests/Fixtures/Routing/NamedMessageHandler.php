<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Fixtures\Routing;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;

#[AsMessageHandler(path: '/chat/{room}', name: 'chat_room', requirements: ['room' => '\d+'], priority: 10)]
final class NamedMessageHandler {}
