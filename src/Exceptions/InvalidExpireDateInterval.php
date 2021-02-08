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
 *
 */
final class InvalidExpireDateInterval extends \InvalidArgumentException
{
    public static function create(SagaId $id): self
    {
        return new self(
            \sprintf('The expiration date of the saga `%s` can not be less than the current date', $id->toString())
        );
    }
}
