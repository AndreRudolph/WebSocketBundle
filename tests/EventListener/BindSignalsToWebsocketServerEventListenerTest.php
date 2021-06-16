<?php declare(strict_types=1);

namespace Gos\Bundle\WebSocketBundle\Tests\EventListener;

use Gos\Bundle\WebSocketBundle\Client\ClientStorageInterface;
use Gos\Bundle\WebSocketBundle\Event\ServerLaunchedEvent;
use Gos\Bundle\WebSocketBundle\EventListener\BindSignalsToWebsocketServerEventListener;
use Gos\Bundle\WebSocketBundle\Server\App\Registry\PeriodicRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;

final class BindSignalsToWebsocketServerEventListenerTest extends TestCase
{
    /**
     * @var PeriodicRegistry
     */
    private $periodicRegistry;

    /**
     * @var MockObject&ClientStorageInterface
     */
    private $clientStorage;

    /**
     * @var BindSignalsToWebsocketServerEventListener
     */
    private $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->periodicRegistry = new PeriodicRegistry();
        $this->clientStorage = $this->createMock(ClientStorageInterface::class);

        $this->listener = new BindSignalsToWebsocketServerEventListener($this->periodicRegistry, $this->clientStorage);
    }

    /**
     * @requires extension pcntl
     */
    public function testTheUserIsAuthenticatedWhenTheClientConnectEventIsDispatched(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects(self::exactly(2))
            ->method('addSignal')
            ->withConsecutive(
                [\SIGINT, self::isInstanceOf(\Closure::class)],
                [\SIGTERM, self::isInstanceOf(\Closure::class)]
            );

        $event = new ServerLaunchedEvent(
            $loop,
            $this->createMock(ServerInterface::class),
            false
        );

        $listener = $this->listener;
        $listener($event);
    }
}
