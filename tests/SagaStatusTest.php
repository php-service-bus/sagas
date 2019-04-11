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
use ServiceBus\Sagas\Exceptions\InvalidSagaStatus;
use ServiceBus\Sagas\SagaStatus;

/**
 *
 */
final class SagaStatusTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function withInvalidStatus(): void
    {
        $this->expectException(InvalidSagaStatus::class);
        $this->expectExceptionMessage('Incorrect saga status specified: qwerty');

        SagaStatus::create('qwerty');
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function expired(): void
    {
        static::assertSame('expired', SagaStatus::expired()->toString());
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function failed(): void
    {
        static::assertSame('failed', SagaStatus::failed()->toString());
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function completed(): void
    {
        static::assertSame('completed', SagaStatus::completed()->toString());
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function created(): void
    {
        static::assertSame('in_progress', SagaStatus::created()->toString());
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function equals(): void
    {
        static::assertTrue(SagaStatus::created()->equals(SagaStatus::created()));
    }
}
