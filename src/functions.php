<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas;

/**
 * Create event listener method name by event class.
 *
 * @param string $event
 *
 * @return string
 */
function createEventListenerName(string $event): string
{
    $eventListenerMethodNameParts = \explode('\\', $event);

    return \sprintf(
        '%s%s',
        Saga::EVENT_APPLY_PREFIX,
        \end($eventListenerMethodNameParts)
    );
}
