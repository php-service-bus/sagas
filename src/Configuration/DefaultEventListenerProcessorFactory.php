<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration;

use ServiceBus\Sagas\Store\SagasStore;

/**
 *
 */
final class DefaultEventListenerProcessorFactory implements EventListenerProcessorFactory
{
    /**
     * @var SagasStore
     */
    private $sagaStore;

    /**
     * @param SagasStore $sagaStore
     */
    public function __construct(SagasStore $sagaStore)
    {
        $this->sagaStore = $sagaStore;
    }

    /**
     * {@inheritdoc}
     */
    public function createProcessor(string $event, SagaListenerOptions $listenerOptions): EventProcessor
    {
        /** @var class-string $event */
        return new DefaultEventProcessor($event, $this->sagaStore, $listenerOptions);
    }
}
