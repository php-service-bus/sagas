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

use ServiceBus\Mutex\InMemory\InMemoryMutexFactory;
use ServiceBus\Mutex\MutexFactory;
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
     * @var MutexFactory
     */
    private $mutexFactory;

    public function __construct(SagasStore $sagaStore, ?MutexFactory $mutexFactory = null)
    {
        $this->sagaStore    = $sagaStore;
        $this->mutexFactory = $mutexFactory ?? new InMemoryMutexFactory();
    }

    public function createProcessor(string $event, SagaListenerOptions $listenerOptions): EventProcessor
    {
        return new DefaultEventProcessor($event, $this->sagaStore, $listenerOptions, $this->mutexFactory);
    }
}
