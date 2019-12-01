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
use ServiceBus\Sagas\Configuration\SagaMetadata;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;

/**
 *
 */
final class SagaMetadataTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     */
    public function createWithIncorrectContainingIdentifierSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new  SagaMetadata(
            CorrectSaga::class,
            TestSagaId::class,
            'qwerty',
            'correlationId',
            '+1 day'
        );
    }
}
