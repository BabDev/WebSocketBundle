<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication\Storage;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocketBundle\Authentication\Storage\Driver\StorageDriver;
use BabDev\WebSocketBundle\Authentication\Storage\DriverBackedTokenStorage;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\StorageError;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class DriverBackedTokenStorageTest extends TestCase
{
    private readonly MockObject&StorageDriver $driver;

    private readonly DriverBackedTokenStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = $this->createMock(StorageDriver::class);

        $this->storage = new DriverBackedTokenStorage($this->driver);
    }

    public function testAStorageIdentifierForAConnectionIsGenerated(): void
    {
        $clientId = '42';

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects(self::once())
            ->method('get')
            ->with('resource_id')
            ->willReturn($clientId);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        self::assertSame($clientId, $this->storage->generateStorageId($connection));
    }

    public function testTheTokenIsAddedToStorage(): void
    {
        /** @var MockObject&TokenInterface $token */
        $token = $this->createMock(TokenInterface::class);

        $this->driver->expects(self::once())
            ->method('store')
            ->willReturn(true);

        $this->storage->addToken('42', $token);
    }

    public function testAnExceptionIsThrownIfTheTokenIsNotAddedToStorage(): void
    {
        $this->expectException(StorageError::class);
        $this->expectExceptionMessage('Unable to add client "user" to storage');

        /** @var MockObject&TokenInterface $token */
        $token = $this->createMock(TokenInterface::class);
        $token->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn('user');

        $this->driver->expects(self::once())
            ->method('store')
            ->willReturn(false);

        $this->storage->addToken('42', $token);
    }

    public function testTheTokenIsRetrieved(): void
    {
        $storageId = '42';

        /** @var MockObject&TokenInterface $token */
        $token = $this->createMock(TokenInterface::class);

        $this->driver->expects(self::once())
            ->method('get')
            ->with($storageId)
            ->willReturn($token);

        self::assertEquals($token, $this->storage->getToken($storageId));
    }

    public function testTheStorageCanBeCheckedToDetermineIfATokenExists(): void
    {
        $this->driver->expects(self::once())
            ->method('has')
            ->willReturn(true);

        self::assertTrue($this->storage->hasToken('42'));
    }

    public function testATokenCanBeRemovedFromStorage(): void
    {
        $this->driver->expects(self::once())
            ->method('delete')
            ->willReturn(true);

        self::assertTrue($this->storage->removeToken('42'));
    }

    public function testAllTokensCanBeRemovedFromStorage(): void
    {
        $this->driver->expects(self::once())
            ->method('clear');

        $this->storage->removeAllTokens();
    }
}
