<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\stubs;

/**
 * @property-read string $key
 */
final class EventWithKey
{
    public string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }
}
