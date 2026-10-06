<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication\Storage;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocketBundle\Authentication\Storage\Driver\StorageDriver;
use BabDev\WebSocketBundle\Authentication\Storage\DriverBackedTokenStorage;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\StorageError;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class DriverBackedTokenStorageTest extends TestCase
{
    public function testAStorageIdentifierForAConnectionIsGenerated(): void
    {
        $storage = $this->createStorage();

        $clientId = '42';

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects(self::once())
            ->method('get')
            ->with(AttributeKey::RESOURCE_ID)
            ->willReturn($clientId);

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        self::assertSame($clientId, $storage->generateStorageId($connection));
    }

    public function testTheTokenIsAddedToStorage(): void
    {
        /** @var MockObject&StorageDriver $driver */
        $driver = $this->createMock(StorageDriver::class);

        $storage = $this->createStorage(driver: $driver);

        /** @var Stub&TokenInterface $token */
        $token = self::createStub(TokenInterface::class);

        $driver->expects(self::once())
            ->method('store')
            ->willReturn(true);

        $storage->addToken('42', $token);
    }

    public function testAnExceptionIsThrownIfTheTokenIsNotAddedToStorage(): void
    {
        /** @var MockObject&StorageDriver $driver */
        $driver = $this->createMock(StorageDriver::class);

        $storage = $this->createStorage(driver: $driver);

        $this->expectException(StorageError::class);
        $this->expectExceptionMessageIs('Unable to add client "user" to storage');

        /** @var MockObject&TokenInterface $token */
        $token = $this->createMock(TokenInterface::class);
        $token->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn('user');

        $driver->expects(self::once())
            ->method('store')
            ->willReturn(false);

        $storage->addToken('42', $token);
    }

    public function testTheTokenIsRetrieved(): void
    {
        /** @var MockObject&StorageDriver $driver */
        $driver = $this->createMock(StorageDriver::class);

        $storage = $this->createStorage(driver: $driver);

        $storageId = '42';

        /** @var Stub&TokenInterface $token */
        $token = self::createStub(TokenInterface::class);

        $driver->expects(self::once())
            ->method('get')
            ->with($storageId)
            ->willReturn($token);

        self::assertEquals($token, $storage->getToken($storageId));
    }

    public function testTheStorageCanBeCheckedToDetermineIfATokenExists(): void
    {
        /** @var MockObject&StorageDriver $driver */
        $driver = $this->createMock(StorageDriver::class);

        $storage = $this->createStorage(driver: $driver);

        $driver->expects(self::once())
            ->method('has')
            ->willReturn(true);

        self::assertTrue($storage->hasToken('42'));
    }

    public function testATokenCanBeRemovedFromStorage(): void
    {
        /** @var MockObject&StorageDriver $driver */
        $driver = $this->createMock(StorageDriver::class);

        $storage = $this->createStorage(driver: $driver);

        $driver->expects(self::once())
            ->method('delete')
            ->willReturn(true);

        self::assertTrue($storage->removeToken('42'));
    }

    public function testAllTokensCanBeRemovedFromStorage(): void
    {
        /** @var MockObject&StorageDriver $driver */
        $driver = $this->createMock(StorageDriver::class);

        $storage = $this->createStorage(driver: $driver);

        $driver->expects(self::once())
            ->method('clear');

        $storage->removeAllTokens();
    }

    private function createStorage(?StorageDriver $driver = null): DriverBackedTokenStorage
    {
        return new DriverBackedTokenStorage($driver ?? self::createStub(StorageDriver::class));
    }
}
