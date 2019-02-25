<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Exceptions;

/**
 * The class of the saga in the identifier differs from the saga to which it was transmitted
 */
final class InvalidSagaIdentifier extends \RuntimeException
{
    /**
     * @return self
     */
    public static function idValueCantBeEmpty(): self
    {
        return new self('The saga identifier can\'t be empty');
    }

    /**
     * @param string $sagaClass
     *
     * @return self
     */
    public static function invalidSagaClass(string $sagaClass): self
    {
        return new self(\sprintf('Invalid saga class specified ("%s")', $sagaClass));
    }
}
