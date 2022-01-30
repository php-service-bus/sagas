<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests\stubs;

use Psr\Log\NullLogger;
use ServiceBus\Common\Context\ContextLogger;
use ServiceBus\Common\Context\IncomingMessageMetadata;
use ServiceBus\Common\Context\OutcomeMessageMetadata;
use ServiceBus\Common\Context\ValidationViolations;
use Amp\Promise;
use Amp\Success;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\Endpoint\DeliveryOptions;
use function ServiceBus\Common\uuid;

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
     * @var NullLogger
     */
    public $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function violations(): ?ValidationViolations
    {
        return null;
    }

    public function delivery(object $message, ?DeliveryOptions $deliveryOptions = null, ?OutcomeMessageMetadata $withMetadata = null): Promise
    {
        $this->messages[] = $message;

        return new Success();
    }

    public function deliveryBulk(array $messages, ?DeliveryOptions $deliveryOptions = null, ?OutcomeMessageMetadata $withMetadata = null): Promise
    {
        $this->messages = \array_merge($this->messages, $messages);

        return new Success();
    }

    public function return(int $secondsDelay = 3, ?OutcomeMessageMetadata $withMetadata = null): Promise
    {
        return new Success();
    }

    public function logger(): ContextLogger
    {
        return new TestContextLogger($this->logger, new \stdClass(), $this->metadata());
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function metadata(): IncomingMessageMetadata
    {
        return new TestIncomingMessageMetadata(uuid(), []);
    }
}
