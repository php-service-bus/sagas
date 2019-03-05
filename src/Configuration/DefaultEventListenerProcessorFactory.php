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

    /**
     * DefaultEventListenerProcessorFactory constructor.
     *
     * @param SagasStore        $sagaStore
     * @param MutexFactory|null $mutexFactory
     */
    public function __construct(SagasStore $sagaStore, ?MutexFactory $mutexFactory = null)
    {
        $this->sagaStore    = $sagaStore;
        $this->mutexFactory = $mutexFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createProcessor(string $event, SagaListenerOptions $listenerOptions): EventProcessor
    {
        /** @var class-string $event */
        return new DefaultEventProcessor($event, $this->sagaStore, $listenerOptions, $this->mutexFactory);
    }
}
