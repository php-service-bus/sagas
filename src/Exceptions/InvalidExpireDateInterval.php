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
 *
 */
final class InvalidExpireDateInterval extends \InvalidArgumentException
{
    public static function create(): self
    {
        return new self('The expiration date of the saga can not be less than the current date');
    }
}
