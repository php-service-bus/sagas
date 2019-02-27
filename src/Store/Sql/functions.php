<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Store\Sql;

use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Store\Exceptions\SagaSerializationError;

/**
 * @internal
 *
 * Serialize saga payload
 *
 * @param Saga $saga
 *
 * @return string
 */
function serializeSaga(Saga $saga): string
{
    return \base64_encode(\serialize($saga));
}

/**
 * @internal
 *
 * Unserialize saga
 *
 * @param string $serializedContent
 *
 * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
 *
 * @return Saga
 *
 */
function unserializeSaga(string $serializedContent): Saga
{
    try
    {
        $decoded = \base64_decode($serializedContent);

        // @codeCoverageIgnoreStart
        if (false === $decoded)
        {
            throw new \LogicException('Incorrect base64 content');
        }
        // @codeCoverageIgnoreEnd

        /** @var bool|Saga $unserialized */
        $unserialized = \unserialize($decoded, ['allowed_classes' => true]);

        if ($unserialized instanceof Saga)
        {
            return $unserialized;
        }

        throw new \LogicException('Content must be a serialized saga object');
    }
    catch (\Throwable $throwable)
    {
        throw SagaSerializationError::fromThrowable($throwable);
    }
}
