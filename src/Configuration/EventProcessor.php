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
     * @return Promise<void>
     *
     * @throws \Throwable In case of error while preserving the saga.
     */
    public function __invoke(object $event, ServiceBusContext $context): Promise;
}
