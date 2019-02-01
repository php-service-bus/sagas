<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration;

use Amp\Promise;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\Messages\Event;

/**
 * Saga event listener processor
 */
interface EventProcessor
{
    /**
     * Receipt of the event for which the handler was created
     *
     * @return string
     */
    public function event(): string;

    /**
     * Invoke saga listener
     *
     * @param Event             $event
     * @param ServiceBusContext $context
     *
     * @return Promise Doesn't return result
     */
    public function __invoke(Event $event, ServiceBusContext $context): Promise;
}
