<?php declare(strict_types=1);

namespace Gos\Bundle\WebSocketBundle\Periodic;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

final class PeriodicMemoryUsage implements PeriodicInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function tick(): void
    {
        $this->logger?->info('Memory usage : '.round((memory_get_usage() / (1024 * 1024)), 4).'Mo');
    }

    public function getInterval(): int
    {
        return 5;
    }
}
