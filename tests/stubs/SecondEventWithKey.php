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
 *
 */
final class SecondEventWithKey
{
    /** @var string */
    public $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }
}
