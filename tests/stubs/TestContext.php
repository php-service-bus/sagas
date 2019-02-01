<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\stubs;

use Amp\Promise;
use Amp\Success;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\Endpoint\DeliveryOptions;
use ServiceBus\Common\Messages\Message;
use function ServiceBus\Common\uuid;

/**
 *
 */
final class TestContext implements ServiceBusContext
{
    /**
     * @var Message[]
     */
    public $messages = [];

    /**
     * @var TestLogger
     */
    public $logger;

    public function __construct()
    {
        $this->logger = new TestLogger();
    }

    /**
     * @inheritDoc
     */
    public function isValid(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function violations(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function delivery(Message $message, ?DeliveryOptions $deliveryOptions = null): Promise
    {
        $this->messages[] = $message;

        return new Success();
    }

    /**
     * @inheritDoc
     */
    public function logContextMessage(string $logMessage, array $extra = [], string $level = LogLevel::INFO): void
    {
        $this->logger->log($level, $logMessage, $extra);
    }

    /**
     * @inheritDoc
     */
    public function logContextThrowable(\Throwable $throwable, string $level = LogLevel::ERROR, array $extra = []): void
    {
        $this->logger->log($level, $throwable->getMessage(), $extra);
    }

    /**
     * @inheritDoc
     */
    public function operationId(): string
    {
        return uuid();
    }

    /**
     * @inheritDoc
     */
    public function traceId(): string
    {
        return uuid();
    }
}
