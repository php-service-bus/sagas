<?php

/**
 * Saga pattern implementation
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
     * @return void
     */
    public function successCreate(): void
    {
        SagaConfiguration::create(
            SagaMetadata::create(
                Saga::class,
                SagaId::class,
                'correlationId',
                '+1 days'
            ),
            new \SplObjectStorage()
        );
    }
}
