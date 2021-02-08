<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas;

use ServiceBus\Common\MessageExecutor\MessageExecutor;
use function Amp\call;
use Amp\Promise;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\MessageHandler\MessageHandler;

/**
 *
 */
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

    public function __invoke(object $message, ServiceBusContext $context): Promise
    {
        return call($this->messageHandler->closure, $message, $context);
    }
}
