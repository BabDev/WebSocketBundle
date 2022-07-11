<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Attribute;

use Symfony\Component\Routing\Annotation\Route;

/**
 * Attribute used to configure message handlers.
 *
 * This attribute serves two purposes:
 *
 * 1) Register the message handler as a service within the container
 * 2) Configure the route definition for the message handler to be used with the websocket server's router
 *
 * Because of the second purpose, this attribute purposefully inherits from the {@see Route} annotation/attribute class
 * from Symfony's Routing component to allow using its annotation/attribute loaders.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsMessageHandler extends Route
{
}
