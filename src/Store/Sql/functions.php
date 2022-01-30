<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Store\Sql;

use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Store\Exceptions\SagaSerializationError;

/**
 * @internal
 *
 * Serialize saga payload
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
 * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
 */
function unserializeSaga(string $serializedContent): Saga
{
    try
    {
        /** @var string|bool $decoded */
        $decoded = \base64_decode($serializedContent);

        // @codeCoverageIgnoreStart
        if (\is_string($decoded) === false)
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
