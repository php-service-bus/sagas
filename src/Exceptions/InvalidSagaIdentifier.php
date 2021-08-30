<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas\Exceptions;

use ServiceBus\Sagas\SagaId;

/**
 * The class of the saga in the identifier differs from the saga to which it was transmitted.
 */
final class InvalidSagaIdentifier extends \RuntimeException
{
    public static function idValueCantBeEmpty(): self
    {
        return new self('The saga identifier can\'t be empty');
    }

    public static function invalidSagaClass(string $sagaClass): self
    {
        return new self(\sprintf('Invalid saga class specified ("%s")', $sagaClass));
    }

    public static function sagaClassMismatch(string $expectedSagaClass, string $actualSagaClass): self
    {
        return new self(
            \sprintf(
                'The class of the saga in the identifier ("%s") differs from the saga to which it was transmitted ("%s")',
                $actualSagaClass,
                $expectedSagaClass
            )
        );
    }

    public static function propertyNotFound(string $propertyName, object $event): self
    {
        return new self(
            \sprintf(
                'A property that contains an identifier ("%s") was not found in class "%s"',
                $propertyName,
                \get_class($event)
            )
        );
    }

    public static function propertyCantBeEmpty(string $propertyName, object $event): self
    {
        return new self(
            \sprintf(
                'The value of the "%s" property of the "%s" event can\'t be empty, since it is the saga id',
                $propertyName,
                \get_class($event)
            )
        );
    }

    public static function headerKeyCantBeEmpty(string $property): self
    {
        return new self(
            \sprintf(
                'The value of the "%s" header key can\'t be empty, since it is the saga id',
                $property
            )
        );
    }

    public static function wrongType(object $id): self
    {
        return new self(
            \sprintf(
                'Saga identifier mus be type of "%s". "%s" type specified',
                SagaId::class,
                \get_class($id)
            )
        );
    }

    public static function typeNotFound(string $identifierClass, string $sagaClass): self
    {
        return new self(
            \sprintf(
                'Identifier class "%s" specified in the saga "%s" not found',
                $identifierClass,
                $sagaClass
            )
        );
    }
}
