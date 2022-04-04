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

use ServiceBus\Sagas\Configuration\Metadata\SagaHandlerOptions;

interface SagaMessageProcessorFactory
{
    /**
     * Create handler for event.
     *
     * @psalm-param class-string $event
     */
    public function createListener(string $event, SagaHandlerOptions $handlerOptions): MessageProcessor;

    /**
     * Create handler for initial command.
     *
     * @psalm-param class-string $command
     */
    public function createHandler(string $command, SagaHandlerOptions $handlerOptions): MessageProcessor;
}
