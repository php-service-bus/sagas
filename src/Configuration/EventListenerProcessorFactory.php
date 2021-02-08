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

/**
 *
 */
interface EventListenerProcessorFactory
{
    /**
     * Create handler for event.
     *
     * @psalm-param class-string $event
     */
    public function createProcessor(string $event, SagaListenerOptions $listenerOptions): EventProcessor;
}
