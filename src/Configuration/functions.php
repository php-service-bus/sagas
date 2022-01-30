<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Configuration;

use phpDocumentor\Reflection\Types\Scalar;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaId;
use function ServiceBus\Common\readReflectionPropertyValue;

/**
 * @internal
 *
 * @psalm-return \Closure(object, \ServiceBus\Common\Context\ServiceBusContext):\Amp\Promise<void>
 */
function createClosure(Saga $saga, \ReflectionMethod $method): \Closure
{
    /** @psalm-var \Closure(object, \ServiceBus\Common\Context\ServiceBusContext):\Amp\Promise<void>|null $closure */
    $closure = $method->getClosure($saga);

    /** @noinspection PhpConditionAlreadyCheckedInspection */
    // @codeCoverageIgnoreStart
    if ($closure === null)
    {
        throw new \LogicException(
            \sprintf(
                'Unable to create a closure for the "%s" method',
                $method->getName()
            )
        );
    }

    return $closure;
}

/**
 * @internal
 *
 * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $identifierClass
 * @psalm-param class-string<\ServiceBus\Sagas\Saga>   $sagaClass
 * @psalm-param non-empty-string                       $headerKey
 */
function searchSagaIdentifierInHeaders(
    string $identifierClass,
    string $sagaClass,
    string $headerKey,
    array  $headers
): SagaId {
    $headerKeyValue = (string) ($headers[$headerKey] ?? '');

    if ($headerKeyValue !== '')
    {
        return identifierInstantiator(
            idClass: $identifierClass,
            idValue: $headerKeyValue,
            sagaClass: $sagaClass
        );
    }

    throw InvalidSagaIdentifier::headerKeyCantBeEmpty($headerKey);
}

/**
 * @internal
 *
 * @psalm-param non-empty-string                       $propertyName
 * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $identifierClass
 * @psalm-param class-string<\ServiceBus\Sagas\Saga>   $sagaClass
 */
function searchSagaIdentifierInMessageObject(
    object $message,
    string $propertyName,
    string $identifierClass,
    string $sagaClass
): SagaId {
    return identifierInstantiator(
        idClass: $identifierClass,
        idValue: readMessagePropertyValue($message, $propertyName),
        sagaClass: $sagaClass
    );
}

/**
 * @internal
 *
 * @psalm-param non-empty-string $propertyName
 *
 * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
 */
function readMessagePropertyValue(object $message, string $propertyName): string
{
    try
    {
        /** @psalm-var object|string|int|float $value */
        $value = $message->{$propertyName} ?? readReflectionPropertyValue($message, $propertyName);
    }
    catch (\Throwable)
    {
        throw InvalidSagaIdentifier::propertyNotFound($propertyName, $message);
    }

    if (\is_string($value) && $value !== '')
    {
        return $value;
    }

    if (\is_object($value) && \method_exists($value, 'toString'))
    {
        $value = (string) $value->toString();

        if ($value !== '')
        {
            return $value;
        }
    }

    throw InvalidSagaIdentifier::propertyCantBeEmpty($propertyName, $message);
}

/**
 * Create identifier instance.
 *
 * @internal
 *
 * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $idClass
 * @psalm-param class-string<\ServiceBus\Sagas\Saga>   $sagaClass
 *
 * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
 */
function identifierInstantiator(
    string $idClass,
    string $idValue,
    string $sagaClass
): SagaId {
    /** @var object|SagaId $identifier */
    $identifier = new $idClass($idValue, $sagaClass);

    if ($identifier instanceof SagaId)
    {
        return $identifier;
    }

    throw InvalidSagaIdentifier::wrongType($identifier);
}
