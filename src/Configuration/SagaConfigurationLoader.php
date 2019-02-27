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
     * @param string $sagaClass
     *
     * @throws \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     *
     * @return SagaConfiguration
     */
    public function load(string $sagaClass): SagaConfiguration;
}
