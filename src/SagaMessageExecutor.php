<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas;

use ServiceBus\Common\EntryPoint\Retry\RetryStrategy;
use ServiceBus\Common\MessageExecutor\MessageExecutor;
use Amp\Promise;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\MessageHandler\MessageHandler;
use function Amp\call;

final class SagaMessageExecutor implements MessageExecutor
{
    /**
     * @var MessageHandler
     */
    private $messageHandler;

    public function __construct(MessageHandler $messageHandler)
    {
        $this->messageHandler = $messageHandler;
    }

    public function id(): string
    {
        /** @psalm-var non-empty-string $id */
        $id = \sha1(
            \sprintf('%s:%s', $this->messageHandler->messageClass, $this->messageHandler->methodName)
        );

        return $id;
    }

    public function retryStrategy(): ?RetryStrategy
    {
        return null;
    }

    public function __invoke(object $message, ServiceBusContext $context): Promise
    {
        return call($this->messageHandler->closure, $message, $context);
    }
}
