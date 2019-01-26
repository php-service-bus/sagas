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
use ServiceBus\Sagas\SagaStatus;

/**
 *
 */
final class SagaStatusTest extends TestCase
{
    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Exceptions\InvalidSagaStatus
     * @expectedExceptionMessage Incorrect saga status specified: qwerty
     *
     * @return void
     */
    public function withInvalidStatus(): void
    {
        SagaStatus::create('qwerty');
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function expired(): void
    {
        static::assertEquals('expired', (string) SagaStatus::expired());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function failed(): void
    {
        static::assertEquals('failed', (string) SagaStatus::failed());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function completed(): void
    {
        static::assertEquals('completed', (string) SagaStatus::completed());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function created(): void
    {
        static::assertEquals('in_progress', (string) SagaStatus::created());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function equals(): void
    {
        static::assertTrue(SagaStatus::created()->equals(SagaStatus::created()));
    }
}
