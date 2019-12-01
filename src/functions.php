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
 */
function createEventListenerName(string $event): string
{
    $eventListenerMethodNameParts = \explode('\\', $event);

    /** @var string $latestPart */
    $latestPart = \end($eventListenerMethodNameParts);

    return \sprintf(
        '%s%s',
        Saga::EVENT_APPLY_PREFIX,
        $latestPart
    );
}

/**
 * Create mutex key for saga.
 */
function createMutexKey(SagaId $id): string
{
    return \sha1(\sprintf('%s:%s', $id->id, $id->sagaClass));
}
