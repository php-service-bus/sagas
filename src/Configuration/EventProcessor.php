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
     * @return Promise<bool> Has the saga been preserved?
     */
    public function __invoke(object $event, ServiceBusContext $context): Promise;
}
