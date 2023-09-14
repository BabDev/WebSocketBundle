<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Storage\Exception;

use BabDev\WebSocket\Server\WebSocketException;

/**
 * General exception for token storage errors.
 */
class StorageError extends \RuntimeException implements WebSocketException {}
