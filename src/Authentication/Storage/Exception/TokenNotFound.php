<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Storage\Exception;

use BabDev\WebSocket\Server\WebSocketException;

/**
 * Exception thrown when a token cannot be found in storage.
 */
class TokenNotFound extends \InvalidArgumentException implements WebSocketException {}
