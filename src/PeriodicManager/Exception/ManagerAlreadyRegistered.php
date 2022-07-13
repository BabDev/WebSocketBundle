<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\PeriodicManager\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class ManagerAlreadyRegistered extends \RuntimeException implements WebSocketException
{
}
