<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication\Storage\Driver;

use BabDev\WebSocketBundle\Authentication\Storage\Driver\PsrCacheStorageDriver;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class PsrCacheStorageDriverTest extends TestCase
{
    private readonly ArrayAdapter $cache;

    private readonly PsrCacheStorageDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new ArrayAdapter();

        $this->driver = new PsrCacheStorageDriver($this->cache);
    }

    public function testTokenIsManagedInStorage(): void
    {
        $user = new InMemoryUser('user', 'password');
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);

        self::assertFalse($this->driver->has('abc'));
        self::assertTrue($this->driver->store('abc', $token));
        self::assertTrue($this->driver->has('abc'));

        $storedToken = $this->driver->get('abc');

        self::assertSame($token->getUserIdentifier(), $storedToken->getUserIdentifier(), 'The token retrieved from storage should be comparable to the originally saved token.');

        self::assertTrue($this->driver->delete('abc'));

        try {
            $this->driver->get('abc');

            self::fail('The get() method should throw an exception when the ID is not present.');
        } catch (TokenNotFound) {
            // Successful test case
        }

        self::assertTrue($this->driver->store('abc', $token));
        self::assertTrue($this->driver->has('abc'));

        $this->driver->clear();

        self::assertFalse($this->driver->has('abc'));
    }
}
