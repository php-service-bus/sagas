<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Store;

use function ServiceBus\Sagas\Store\Sql\unserializeSaga;
use PHPUnit\Framework\TestCase;
use ServiceBus\Sagas\Store\Exceptions\SagaSerializationError;

/**
 *
 */
final class SqlStoreFunctionsTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function incorrectBase64(): void
    {
        $this->expectException(SagaSerializationError::class);

        unserializeSaga('qwerty');
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function incorrectSerializedObject(): void
    {
        $this->expectException(SagaSerializationError::class);
        $this->expectExceptionMessage('Content must be a serialized saga object');

        unserializeSaga(base64_encode(serialize(new \stdClass())));
    }
}
