<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas;

/**
 * Create event listener method name by event class.
 *
 * @internal
 *
 * @psalm-param class-string|object $event
 */
function createEventListenerName(string|object $event): string
{
    $event                        = \is_object($event) ? \get_class($event) : $event;
    $eventListenerMethodNameParts = \explode('\\', $event);

    /** @var string $latestPart */
    $latestPart = \end($eventListenerMethodNameParts);

    return \sprintf(
        '%s%s',
        Configuration\SagaConfigurationLoader::EVENT_LISTENER_PREFIX,
        $latestPart
    );
}

/**
 * Create mutex key for saga.
 *
 * @internal
 *
 * @psalm-return non-empty-string
 */
function createMutexKey(SagaId $id): string
{
    /** @psalm-var non-empty-string $key */
    $key = \sha1(\sprintf('%s:%s', $id->id, $id->sagaClass));

    return $key;
}
