<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas\Configuration;

use Amp\Promise;
use ServiceBus\Common\Context\ServiceBusContext;

/**
 * Saga event listener processor.
 */
interface EventProcessor
{
    /**
     * Receipt of the event for which the handler was created.
     */
    public function event(): string;

    /**
     * Invoke saga listener.
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed
     * @throws \Throwable Reflection fails
     *
     * @return Promise<void>
     */
    public function __invoke(object $event, ServiceBusContext $context): Promise;
}
