<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Configuration\Attributes\Exceptions;

/**
 *
 */
final class InvalidSagaHandlerMethod extends \LogicException
{
    public static function wrongEventArgument(\ReflectionMethod $reflectionMethod): self
    {
        return new self(
            \sprintf(
                'The event handler "%s:%s" should take as the first argument an object',
                $reflectionMethod->getDeclaringClass()->getName(),
                $reflectionMethod->getName()
            )
        );
    }

    public static function unexpectedName(string $expected, string $actual): self
    {
        return new self(\sprintf(
            'Invalid method name of the method: "%s". Expected: %s',
            $actual,
            $expected
        ));
    }
}
