<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests;

use PHPUnit\Framework\TestCase;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;
use ServiceBus\Sagas\SagaId;

/**
 *
 */
final class SagaIdTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     */
    public function createWithEmptyIdValue(): void
    {
        $this->expectException(InvalidSagaIdentifier::class);
        $this->expectExceptionMessage('The saga identifier can\'t be empty');

        new class('', __METHOD__) extends SagaId
        {
        };
    }

    /**
     * @test
     */
    public function createWithWrongSagaClass(): void
    {
        $this->expectException(InvalidSagaIdentifier::class);

        new class('qwerty', __METHOD__) extends SagaId
        {
        };
    }

    /**
     * @test
     */
    public function createWithEmptySagaClass(): void
    {
        $this->expectException(InvalidSagaIdentifier::class);

        new class('qwerty', '') extends SagaId
        {
        };
    }
}
