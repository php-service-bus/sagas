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
}
