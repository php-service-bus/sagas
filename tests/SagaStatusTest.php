<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
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
     */
    public function withInvalidStatus(): void
    {
        $this->expectException(InvalidSagaStatus::class);
        $this->expectExceptionMessage('Incorrect saga status specified: qwerty');

        SagaStatus::create('qwerty');
    }

    /**
     * @test
     */
    public function expired(): void
    {
        self::assertSame('expired', SagaStatus::expired()->toString());
    }

    /**
     * @test
     */
    public function failed(): void
    {
        self::assertSame('failed', SagaStatus::failed()->toString());
    }

    /**
     * @test
     */
    public function completed(): void
    {
        self::assertSame('completed', SagaStatus::completed()->toString());
    }

    /**
     * @test
     */
    public function created(): void
    {
        self::assertSame('in_progress', SagaStatus::created()->toString());
    }

    /**
     * @test
     */
    public function equals(): void
    {
        self::assertTrue(SagaStatus::created()->equals(SagaStatus::created()));
    }
}
