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

/**
 * Retrieving saga configuration and event handlers
 */
interface SagaConfigurationLoader
{
    /**
     * Retrieving saga configuration and event handlers
     *
     * @param string $sagaClass
     *
     * @return SagaConfiguration
     *
     * @throws \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     */
    public function load(string $sagaClass): SagaConfiguration;
}
