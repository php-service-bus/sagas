<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Configuration\MessageProcessor;

use ServiceBus\ArgumentResolver\ChainArgumentResolver;
use ServiceBus\Mutex\InMemory\InMemoryMutexService;
use ServiceBus\Mutex\MutexService;
use ServiceBus\Sagas\Configuration\Metadata\SagaHandlerOptions;
use ServiceBus\Sagas\Configuration\SagaIdLocator;
use ServiceBus\Sagas\Store\SagasStore;

final class DefaultSagaMessageProcessorFactory implements SagaMessageProcessorFactory
{
    /**
     * @var SagasStore
     */
    private $sagaStore;

    /**
     * @var ChainArgumentResolver
     */
    private $argumentResolver;

    /**
     * @var MutexService
     */
    private $mutexService;

    /**
     * @var SagaIdLocator
     */
    private $sagaIdLocator;

    public function __construct(
        SagasStore            $sagaStore,
        ChainArgumentResolver $argumentResolver,
        SagaIdLocator         $sagaIdLocator,
        ?MutexService         $mutexService = null
    ) {
        $this->sagaStore        = $sagaStore;
        $this->argumentResolver = $argumentResolver;
        $this->sagaIdLocator    = $sagaIdLocator;
        $this->mutexService     = $mutexService ?? new InMemoryMutexService();
    }

    public function createListener(string $event, SagaHandlerOptions $handlerOptions): MessageProcessor
    {
        return new DefaultEventListenerProcessor(
            forEvent: $event,
            sagasStore: $this->sagaStore,
            sagaListenerOptions: $handlerOptions,
            mutexService: $this->mutexService,
            argumentResolver: $this->argumentResolver,
            sagaIdLocator: $this->sagaIdLocator
        );
    }

    public function createHandler(string $command, SagaHandlerOptions $handlerOptions): MessageProcessor
    {
        return new DefaultInitialCommandHandlerMessageProcessor(
            forCommand: $command,
            sagasStore: $this->sagaStore,
            sagaListenerOptions: $handlerOptions,
            mutexService: $this->mutexService,
            argumentResolver: $this->argumentResolver,
            sagaIdLocator: $this->sagaIdLocator
        );
    }
}
