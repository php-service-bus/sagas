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

use ServiceBus\Sagas\Saga;

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
