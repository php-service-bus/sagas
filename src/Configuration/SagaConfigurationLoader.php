<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
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
     */
    public function load(string $sagaClass): SagaConfiguration;
}
