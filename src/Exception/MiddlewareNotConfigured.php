<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class MiddlewareNotConfigured extends \RuntimeException implements WebSocketException
{
}
