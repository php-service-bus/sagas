<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\stubs;

use function ServiceBus\Common\uuid;
use Amp\Promise;
use Amp\Success;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\Endpoint\DeliveryOptions;

/**
 *
 */
final class TestContext implements ServiceBusContext
{
    /**
     * @var object[]
     */
    public $messages = [];

    /**
     * @var array
     */
    public $headers = [];

    /**
     * @var TestLogger
     */
    public $logger;

    public function __construct()
    {
        $this->logger = new TestLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function violations(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function delivery(object $message, ?DeliveryOptions $deliveryOptions = null): Promise
    {
        $this->messages[] = $message;

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function logContextMessage(string $logMessage, array $extra = [], string $level = LogLevel::INFO): void
    {
        $this->logger->log($level, $logMessage, $extra);
    }

    /**
     * {@inheritdoc}
     */
    public function logContextThrowable(\Throwable $throwable, string $level = LogLevel::ERROR, array $extra = []): void
    {
        $this->logger->log($level, $throwable->getMessage(), $extra);
    }

    /**
     * {@inheritdoc}
     */
    public function operationId(): string
    {
        return uuid();
    }

    /**
     * {@inheritdoc}
     */
    public function traceId(): string
    {
        return uuid();
    }
}
