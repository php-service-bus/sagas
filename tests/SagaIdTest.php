<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests;

use PHPUnit\Framework\TestCase;
use ServiceBus\Sagas\SagaId;

/**
 *
 */
final class SagaIdTest extends TestCase
{
    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     * @expectedExceptionMessage The saga identifier can't be empty
     *
     * @return void
     */
    public function createWithEmptyIdValue(): void
    {
        new class('', __METHOD__) extends SagaId
        {

        };
    }

    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     *
     * @return void
     */
    public function createWithWrongSagaClass(): void
    {
        new class('qwerty', __METHOD__) extends SagaId
        {

        };
    }

    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     *
     * @return void
     */
    public function createWithEmptySagaClass(): void
    {
        new class('qwerty', '') extends SagaId
        {

        };
    }
}
