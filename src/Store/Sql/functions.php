<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
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
 * @return Saga
 *
 * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
 */
function unserializeSaga(string $serializedContent): Saga
{
    try
    {
        $decoded = \base64_decode($serializedContent);

        if(false === $decoded)
        {
            throw new \LogicException('Incorrect base64 content');
        }

        /** @var Saga|bool $unserialized */
        $unserialized = \unserialize($decoded, ['allowed_classes' => true]);

        if($unserialized instanceof Saga)
        {
            return $unserialized;
        }

        throw new \LogicException('Content must be a serialized saga object');
    }
    catch(\Throwable $throwable)
    {
        throw new SagaSerializationError($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
    }
}
