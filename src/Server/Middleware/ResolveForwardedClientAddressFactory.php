<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server\Middleware;

use BabDev\WebSocket\Server\Http\Middleware\ResolveForwardedClientAddress;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Exception\InvalidConfiguration;
use Symfony\Component\HttpFoundation\Request;

/**
 * The resolve forwarded client address factory creates the {@see ResolveForwardedClientAddress} middleware from the
 * bundle configuration, which uses the same formats as the trusted proxy configuration from the FrameworkBundle.
 *
 * @internal
 */
final class ResolveForwardedClientAddressFactory
{
    /**
     * @param list<string> $trustedProxies A list of trusted proxies, where each entry may be a comma-separated list
     * @param list<string> $trustedHeaders A list of trusted header names, where each entry may be a comma-separated list
     *
     * @throws InvalidConfiguration if a trusted header is not supported
     */
    public static function create(ServerMiddleware $middleware, array $trustedProxies, array $trustedHeaders): ResolveForwardedClientAddress
    {
        $trustForwarded = false;
        $trustXForwardedFor = false;

        foreach (self::splitList($trustedHeaders) as $header) {
            // Header names are validated against the Request::HEADER_* constants in the same way as the HttpKernel
            if (!\defined(Request::class.'::HEADER_'.strtr(strtoupper($header), '-', '_'))) {
                throw new InvalidConfiguration(\sprintf('The trusted header "%s" is not supported.', $header));
            }

            // The middleware only uses these headers, any other supported header is accepted for consistency with the FrameworkBundle configuration
            match (strtolower($header)) {
                'forwarded' => $trustForwarded = true,
                'x-forwarded-for' => $trustXForwardedFor = true,
                default => null,
            };
        }

        return new ResolveForwardedClientAddress(
            $middleware,
            self::splitList($trustedProxies),
            ($trustForwarded ? Request::HEADER_FORWARDED : 0) | ($trustXForwardedFor ? Request::HEADER_X_FORWARDED_FOR : 0),
        );
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function splitList(array $values): array
    {
        $items = [];

        foreach ($values as $value) {
            foreach (explode(',', $value) as $item) {
                if ('' !== ($item = trim($item))) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }
}
