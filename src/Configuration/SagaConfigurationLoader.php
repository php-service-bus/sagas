<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Configuration;

use ServiceBus\Sagas\Configuration\Metadata\SagaConfiguration;

/**
 * Retrieving saga configuration and event handlers.
 */
interface SagaConfigurationLoader
{
    final public const INITIAL_COMMAND_METHOD = 'start';
    final public const EVENT_LISTENER_PREFIX  = 'on';

    /**
     * Retrieving saga configuration and event handlers.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     *
     * @throws \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     */
    public function load(string $sagaClass): SagaConfiguration;
}
