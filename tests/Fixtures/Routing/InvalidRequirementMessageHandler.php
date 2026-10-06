<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Fixtures\Routing;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;

#[AsMessageHandler(path: '/invalid/{id}', requirements: ['\d+'])]
final class InvalidRequirementMessageHandler {}
