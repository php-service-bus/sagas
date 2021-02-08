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

/**
 * Retrieving saga configuration and event handlers.
 */
interface SagaConfigurationLoader
{
    /**
     * Retrieving saga configuration and event handlers.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     *
     * @throws \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     */
    public function load(string $sagaClass): SagaConfiguration;
}
