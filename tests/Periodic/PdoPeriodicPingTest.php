<?php declare(strict_types=1);

namespace Gos\Bundle\WebSocketBundle\Tests\Periodic;

use Gos\Bundle\WebSocketBundle\Periodic\PdoPeriodicPing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PdoPeriodicPingTest extends TestCase
{
    /**
     * @requires extension pdo
     */
    public function testTheDatabaseIsPinged(): void
    {
        /** @var MockObject&\PDO $connection */
        $connection = $this->createMock(\PDO::class);
        $connection->expects(self::once())
            ->method('getAttribute')
            ->with(\PDO::ATTR_PERSISTENT)
            ->willReturn(null);

        $connection->expects(self::once())
            ->method('query')
            ->with('SELECT 1');

        (new PdoPeriodicPing($connection))->tick();
    }

    /**
     * @requires extension pdo
     */
    public function testTheDatabaseIsNotPingedForAPersistentConnection(): void
    {
        /** @var MockObject&\PDO $connection */
        $connection = $this->createMock(\PDO::class);
        $connection->expects(self::once())
            ->method('getAttribute')
            ->with(\PDO::ATTR_PERSISTENT)
            ->willReturn(true);

        $connection->expects(self::never())
            ->method('query');

        (new PdoPeriodicPing($connection))->tick();
    }

    /**
     * @requires extension pdo
     */
    public function testAConnectionErrorIsLogged(): void
    {
        $logger = new NullLogger();

        /** @var MockObject&\PDO $connection */
        $connection = $this->createMock(\PDO::class);
        $connection->expects(self::once())
            ->method('getAttribute')
            ->with(\PDO::ATTR_PERSISTENT)
            ->willReturn(null);

        $connection->expects(self::once())
            ->method('query')
            ->willThrowException(new \PDOException('Testing'));

        $ping = new PdoPeriodicPing($connection);
        $ping->setLogger($logger);

        try {
            $ping->tick();

            self::fail(sprintf('A %s should have been thrown.', \PDOException::class));
        } catch (\PDOException $exception) {
            self::assertSame('Testing', $exception->getMessage());
        }
    }
}
