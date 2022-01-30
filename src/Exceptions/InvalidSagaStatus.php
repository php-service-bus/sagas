<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Exceptions;

/**
 * Incorrect saga status indicated.
 */
class InvalidSagaStatus extends \InvalidArgumentException
{
    public static function create(string $status): self
    {
        return new self(\sprintf('Incorrect saga status specified: %s', $status));
    }
}
