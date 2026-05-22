<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Exception;

use BabDev\WebSocket\Server\WebSocketException;

final class InvalidTokenException extends \RuntimeException implements WebSocketException {}
