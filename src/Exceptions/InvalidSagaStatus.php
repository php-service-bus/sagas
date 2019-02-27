<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Exceptions;

/**
 * Incorrect saga status indicated.
 */
class InvalidSagaStatus extends \InvalidArgumentException
{
    /**
     * @param string $status
     *
     * @return self
     */
    public static function create(string $status): self
    {
        return new self(\sprintf('Incorrect saga status specified: %s', $status));
    }
}
