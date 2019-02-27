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
     *
     * @return string
     */
    public function event(): string;

    /**
     * Invoke saga listener.
     *
     * @param object             $event
     * @param ServiceBusContext $context
     *
     * @return Promise Doesn't return result
     */
    public function __invoke(object $event, ServiceBusContext $context): Promise;
}
