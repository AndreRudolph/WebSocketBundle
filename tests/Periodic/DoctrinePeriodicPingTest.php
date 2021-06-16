<?php declare(strict_types=1);

namespace Gos\Bundle\WebSocketBundle\Tests\Periodic;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Gos\Bundle\WebSocketBundle\Periodic\DoctrinePeriodicPing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

final class DoctrinePeriodicPingTest extends TestCase
{
    public function testTheDatabaseIsPingedWithAConnection(): void
    {
        $logger = new TestLogger();

        $query = 'SELECT 1';

        /** @var MockObject&AbstractPlatform $platform */
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::once())
            ->method('getDummySelectSQL')
            ->willReturn($query);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection->expects(self::once())
            ->method('executeQuery')
            ->with($query);

        $ping = new DoctrinePeriodicPing($connection);
        $ping->setLogger($logger);
        $ping->tick();

        self::assertTrue($logger->hasInfoThatContains('Successfully pinged database server '));
    }

    public function testAConnectionErrorIsLogged(): void
    {
        $logger = new TestLogger();

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('getDatabasePlatform')
            ->willThrowException(new DBALException('Testing'));

        $ping = new DoctrinePeriodicPing($connection);
        $ping->setLogger($logger);

        try {
            $ping->tick();

            self::fail(sprintf('A %s should have been thrown.', DBALException::class));
        } catch (DBALException $exception) {
            self::assertSame('Testing', $exception->getMessage());

            self::assertTrue($logger->hasEmergencyThatContains('Could not ping database server'));
        }
    }
}
