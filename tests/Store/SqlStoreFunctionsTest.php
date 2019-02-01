<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Store;

use PHPUnit\Framework\TestCase;
use function ServiceBus\Sagas\Store\Sql\unserializeSaga;

/**
 *
 */
final class SqlStoreFunctionsTest extends TestCase
{
    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function incorrectBase64(): void
    {
        unserializeSaga('qwerty');
    }

    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
     * @expectedExceptionMessage Content must be a serialized saga object
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function incorrectSerializedObject(): void
    {
        unserializeSaga(base64_encode(serialize(new \stdClass())));
    }
}
