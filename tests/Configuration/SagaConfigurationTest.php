<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use ServiceBus\Sagas\Configuration\SagaConfiguration;
use ServiceBus\Sagas\Configuration\SagaMetadata;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaId;

/**
 *
 */
final class SagaConfigurationTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     */
    public function successCreate(): void
    {
        new SagaConfiguration(
            new SagaMetadata(
                Saga::class,
                SagaId::class,
                'event',
                'correlationId',
                '+1 days'
            ),
            new \SplObjectStorage()
        );
    }
}
